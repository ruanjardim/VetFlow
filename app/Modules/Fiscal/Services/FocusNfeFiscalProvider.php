<?php

namespace App\Modules\Fiscal\Services;

use App\Modules\Fiscal\Contracts\FiscalCredentialResolver;
use App\Modules\Fiscal\Contracts\FiscalProvider;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalNfceInutilization;
use App\Modules\Fiscal\Support\FiscalDocumentType;
use App\Modules\Fiscal\Support\FiscalMunicipalityCatalog;
use App\Modules\Fiscal\Support\FiscalProviderCatalog;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use LogicException;

class FocusNfeFiscalProvider implements FiscalProvider
{
    public function __construct(private readonly FiscalCredentialResolver $credentials) {}

    public function submit(FiscalDocument $document): array
    {
        $this->assertAvailable($document);
        $payload = data_get($document->request_payload, $this->payloadRoot($document).'.payload');

        if (! is_array($payload) || $payload === []) {
            throw new LogicException('O payload Focus NFe ainda não foi gerado e revisado para este documento.');
        }

        // A Focus aceita a data da NFC-e com uma diferença máxima de cinco
        // minutos. O restante do payload permanece exatamente como revisado.
        if ($document->type === FiscalDocumentType::NFCE) {
            $payload['data_emissao'] = $this->nfceEmissionTimestamp($payload['data_emissao'] ?? null);
        }

        $response = $this->request($document)
            ->post($this->path($document).'?'.http_build_query(['ref' => $this->reference($document)]), $payload);
        $response = $this->recoverDocumentConflict($document, $response);
        $response = $response->throw()->json();

        return $this->sanitizeResponse(is_array($response) ? $response : []);
    }

    public function status(FiscalDocument $document): array
    {
        $this->assertAvailable($document);
        $response = $this->request($document)
            ->get($this->path($document).'/'.rawurlencode($this->reference($document)))
            ->throw()
            ->json();

        return $this->sanitizeResponse(is_array($response) ? $response : []);
    }

    public function cancel(FiscalDocument $document, string $reason): array
    {
        $this->assertAvailable($document);
        $reason = trim($reason);

        if (Str::length($reason) < 15 || Str::length($reason) > 255) {
            throw new LogicException('A justificativa do cancelamento deve ter entre 15 e 255 caracteres.');
        }

        $response = $this->request($document)
            ->delete($this->path($document).'/'.rawurlencode($this->reference($document)), [
                'justificativa' => $reason,
            ]);
        $response = $this->recoverDocumentConflict($document, $response);
        $response = $response->throw()->json();

        return $this->sanitizeResponse(is_array($response) ? $response : []);
    }

    public function inutilizeNfce(FiscalNfceInutilization $inutilization): array
    {
        $this->assertInutilizationAvailable($inutilization);
        $payload = $inutilization->request_payload;

        if (! is_array($payload) || $payload === []) {
            throw new LogicException('A solicitação de inutilização da NFC-e não possui um payload revisado.');
        }

        $expectedPayload = [
            'cnpj' => (string) $inutilization->issuer_document,
            'serie' => (string) $inutilization->series,
            'numero_inicial' => (string) $inutilization->number_start,
            'numero_final' => (string) $inutilization->number_end,
            'justificativa' => (string) $inutilization->justification,
        ];
        foreach ($expectedPayload as $field => $expected) {
            if (! array_key_exists($field, $payload) || ! hash_equals($expected, (string) $payload[$field])) {
                throw new LogicException('O payload da inutilização não corresponde à faixa fiscal congelada.');
            }
        }

        $context = new FiscalDocument([
            'provider' => $inutilization->provider,
            'environment' => $inutilization->environment,
            'type' => FiscalDocumentType::NFCE,
            'issuer_snapshot' => [
                'provider_credential_reference' => $inutilization->credential_reference,
                'address' => ['state' => 'RJ'],
            ],
        ]);
        $existing = $this->findNfceInutilization($context, $payload);
        if ($existing !== null) {
            return $existing;
        }

        $providerResponse = $this->request($context)
            ->post('/v2/nfce/inutilizacao', $payload);
        if ($providerResponse->status() === 422) {
            $existing = $this->findNfceInutilization($context, $payload);
            if ($existing !== null) {
                return $existing;
            }
        }
        $response = $providerResponse->throw()->json();

        $sanitized = $this->sanitizeInutilizationResponse(is_array($response) ? $response : []);
        if (Str::lower((string) ($sanitized['status'] ?? '')) !== 'autorizado') {
            return $this->findNfceInutilization($context, $payload) ?? $sanitized;
        }

        return $sanitized;
    }

    public function resendWebhook(FiscalDocument $document): void
    {
        $this->assertAvailable($document);

        if ($document->type !== FiscalDocumentType::NFSE) {
            throw new LogicException('O reenvio de webhook está habilitado somente para a NFS-e Nacional revisada.');
        }

        $this->client($document)
            ->acceptJson()
            ->send('POST', $this->path($document).'/'.rawurlencode($this->reference($document)).'/hook')
            ->throw();
    }

    public function downloadArtifact(FiscalDocument $document, string $artifact): array
    {
        $this->assertAvailable($document);
        $field = match ($artifact) {
            'xml' => 'caminho_xml_nota_fiscal',
            'danfe', 'danfse' => 'caminho_danfe',
            default => throw new LogicException('Tipo de arquivo fiscal não suportado.'),
        };
        $path = $this->artifactPath((string) data_get($document->response_payload, $field), $artifact);
        $accept = $artifact === 'xml' ? 'application/xml' : 'application/pdf, text/html;q=0.9';
        $response = $this->client($document)
            ->accept($accept)
            ->withOptions(['allow_redirects' => false])
            ->get($path);

        if (in_array($response->status(), [301, 302, 303, 307, 308], true)) {
            $response = $this->downloadRedirect($response, $accept);
        }

        $maximumBytes = max(1024, (int) config('fiscal.recovery.artifact_max_bytes', 10485760));
        $declaredBytes = (int) $response->header('Content-Length');

        if ($declaredBytes > $maximumBytes) {
            throw new LogicException('O arquivo fiscal retornado excede o limite configurado.');
        }

        $response->throw();
        $content = $response->body();

        if ($content === '' || strlen($content) > $maximumBytes) {
            throw new LogicException('O arquivo fiscal retornado é vazio ou excede o limite configurado.');
        }

        [$contentType, $extension] = $this->artifactFormat($content, $artifact);

        return [
            'content' => $content,
            'content_type' => $contentType,
            'extension' => $extension,
        ];
    }

    private function assertAvailable(FiscalDocument $document): void
    {
        if (! config('fiscal.external_http_enabled', false)) {
            throw new LogicException('O tráfego HTTP fiscal externo está bloqueado pela configuração do VetFlow.');
        }

        if ($document->environment !== 'homologation') {
            throw new LogicException('O adaptador Focus NFe está liberado somente para homologação.');
        }

        if ($document->provider !== FiscalProviderCatalog::FOCUS_NFE) {
            throw new LogicException('O documento não foi preparado para a Focus NFe.');
        }

        $supportedNfse = $document->type === FiscalDocumentType::NFSE
            && data_get($document->issuer_snapshot, 'municipality_code') === FiscalMunicipalityCatalog::NITEROI_IBGE_CODE;
        $supportedNfce = $document->type === FiscalDocumentType::NFCE
            && strtoupper((string) data_get($document->issuer_snapshot, 'address.state')) === 'RJ';

        if (! $supportedNfse && ! $supportedNfce) {
            throw new LogicException('O adaptador Focus NFe aceita somente NFS-e Nacional de Niterói ou NFC-e modelo 65 do RJ em homologação.');
        }
    }

    private function request(FiscalDocument $document): PendingRequest
    {
        return $this->client($document)->acceptJson()->asJson();
    }

    private function recoverDocumentConflict(FiscalDocument $document, Response $response): Response
    {
        if ($response->status() !== 422) {
            return $response;
        }

        $lookup = $this->request($document)
            ->get($this->path($document).'/'.rawurlencode($this->reference($document)));

        return $lookup->successful() ? $lookup : $response;
    }

    /** @param array<string, mixed> $payload @return array<string, mixed>|null */
    private function findNfceInutilization(FiscalDocument $context, array $payload): ?array
    {
        $response = $this->request($context)->get('/v2/nfce/inutilizacoes', [
            'cnpj' => $payload['cnpj'] ?? null,
            'numero_inicial' => $payload['numero_inicial'] ?? null,
            'numero_final' => $payload['numero_final'] ?? null,
        ])->throw()->json();

        if (! is_array($response)) {
            return null;
        }

        foreach ($response as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $candidate = $this->sanitizeInutilizationResponse($candidate);
            if (Str::lower((string) ($candidate['status'] ?? '')) === 'autorizado'
                && (string) ($candidate['cnpj'] ?? '') === (string) ($payload['cnpj'] ?? '')
                && (string) ($candidate['serie'] ?? '') === (string) ($payload['serie'] ?? '')
                && (string) ($candidate['numero_inicial'] ?? '') === (string) ($payload['numero_inicial'] ?? '')
                && (string) ($candidate['numero_final'] ?? '') === (string) ($payload['numero_final'] ?? '')) {
                return $candidate;
            }
        }

        return null;
    }

    private function assertInutilizationAvailable(FiscalNfceInutilization $inutilization): void
    {
        if (! config('fiscal.external_http_enabled', false)) {
            throw new LogicException('O tráfego HTTP fiscal externo está bloqueado pela configuração do VetFlow.');
        }

        if ($inutilization->environment !== 'homologation'
            || $inutilization->provider !== FiscalProviderCatalog::FOCUS_NFE) {
            throw new LogicException('A inutilização de NFC-e está liberada somente para a Focus NFe em homologação.');
        }

        if (! preg_match('/^\d{14}$/', (string) $inutilization->issuer_document)
            || trim((string) $inutilization->credential_reference) === '') {
            throw new LogicException('O emitente ou a referência externa da credencial da inutilização é inválido.');
        }
    }

    private function client(FiscalDocument $document): PendingRequest
    {
        $token = trim($this->credentials->tokenFor($document));

        if ($token === '') {
            throw new LogicException('A credencial de homologação da Focus NFe não foi localizada.');
        }

        return Http::baseUrl((string) config('fiscal.focus_nfe.homologation_url'))
            ->withBasicAuth($token, '')
            ->timeout((int) config('fiscal.focus_nfe.timeout_seconds', 15))
            ->connectTimeout((int) config('fiscal.focus_nfe.connect_timeout_seconds', 5));
    }

    private function artifactPath(string $path, string $artifact): string
    {
        $path = trim($path);
        $parts = parse_url($path);

        if ($path === '' || strlen($path) > 2000 || ! str_starts_with($path, '/') || str_starts_with($path, '//')
            || $parts === false || isset($parts['scheme']) || isset($parts['host']) || isset($parts['user'])
            || isset($parts['pass']) || isset($parts['fragment']) || str_contains($path, '\\')) {
            throw new LogicException('O caminho do arquivo fiscal retornado pelo provedor não é seguro.');
        }

        $decodedPath = rawurldecode((string) ($parts['path'] ?? ''));
        if (in_array('..', explode('/', $decodedPath), true)) {
            throw new LogicException('O caminho do arquivo fiscal retornado pelo provedor não é seguro.');
        }

        $extension = Str::lower((string) pathinfo($decodedPath, PATHINFO_EXTENSION));
        $allowed = $artifact === 'xml' ? ['xml'] : ['pdf', 'html', 'htm'];
        if (! in_array($extension, $allowed, true)) {
            throw new LogicException('O formato do arquivo fiscal retornado pelo provedor não é suportado.');
        }

        return $path;
    }

    private function downloadRedirect(Response $response, string $accept): Response
    {
        $location = trim((string) $response->header('Location'));
        if (str_starts_with($location, '/')) {
            $location = rtrim((string) config('fiscal.focus_nfe.homologation_url'), '/').$location;
        }

        $parts = parse_url($location);
        if ($parts === false) {
            throw new LogicException('O redirecionamento do arquivo fiscal não pertence a um host autorizado.');
        }

        $baseHost = Str::lower((string) parse_url((string) config('fiscal.focus_nfe.homologation_url'), PHP_URL_HOST));
        $allowedHosts = collect(config('fiscal.recovery.artifact_redirect_hosts', []))
            ->map(fn (mixed $host): string => Str::lower(trim((string) $host)))
            ->filter()
            ->push($baseHost)
            ->unique();
        $host = Str::lower((string) ($parts['host'] ?? ''));

        if (($parts['scheme'] ?? null) !== 'https' || $host === '' || ! $allowedHosts->contains($host)
            || (int) ($parts['port'] ?? 443) !== 443 || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['fragment'])) {
            throw new LogicException('O redirecionamento do arquivo fiscal não pertence a um host autorizado.');
        }

        $download = Http::accept($accept)
            ->timeout((int) config('fiscal.focus_nfe.timeout_seconds', 15))
            ->connectTimeout((int) config('fiscal.focus_nfe.connect_timeout_seconds', 5))
            ->withOptions(['allow_redirects' => false])
            ->get($location);

        if (in_array($download->status(), [301, 302, 303, 307, 308], true)) {
            throw new LogicException('O arquivo fiscal excedeu o limite de redirecionamentos permitido.');
        }

        return $download;
    }

    /** @return array{string, string} */
    private function artifactFormat(string $content, string $artifact): array
    {
        $sample = ltrim(substr($content, 0, 1024), "\xEF\xBB\xBF\x00\x09\x0A\x0D\x20");

        if ($artifact === 'xml' && str_starts_with($sample, '<')) {
            return ['application/xml; charset=UTF-8', 'xml'];
        }

        if ($artifact !== 'xml' && str_starts_with($sample, '%PDF-')) {
            return ['application/pdf', 'pdf'];
        }

        if ($artifact !== 'xml' && str_starts_with($sample, '<')) {
            return ['text/html; charset=UTF-8', 'html'];
        }

        throw new LogicException('O conteúdo do arquivo fiscal retornado pelo provedor é inválido.');
    }

    private function path(FiscalDocument $document): string
    {
        return $document->type === FiscalDocumentType::NFCE ? '/v2/nfce' : '/v2/nfsen';
    }

    private function payloadRoot(FiscalDocument $document): string
    {
        return $document->type === FiscalDocumentType::NFCE ? 'focus_nfce' : 'focus_nfe';
    }

    private function nfceEmissionTimestamp(mixed $value): string
    {
        $now = now('America/Sao_Paulo');

        try {
            $emission = Carbon::parse((string) $value);
            if ($emission->betweenIncluded($now->copy()->subMinutes(4), $now->copy()->addMinute())) {
                return (string) $value;
            }
        } catch (\Throwable) {
            // A data inválida é substituída pela data corrente de homologação.
        }

        return $now->toIso8601String();
    }

    private function reference(FiscalDocument $document): string
    {
        return $document->provider_reference ?: 'vf-'.$document->ulid;
    }

    /** @param array<string, mixed> $response @return array<string, mixed> */
    private function sanitizeResponse(array $response): array
    {
        return collect($response)->only([
            'status', 'ref', 'protocolo', 'cnpj_prestador', 'chave_nfse',
            'numero', 'codigo_verificacao', 'status_sefaz', 'mensagem_sefaz',
            'caminho_xml_nota_fiscal', 'caminho_danfe', 'cnpj_emitente',
            'chave_nfe', 'serie', 'qrcode_url', 'url_consulta_nf',
            'caminho_xml_cancelamento', 'numero_protocolo',
        ])->all();
    }

    /** @param array<string, mixed> $response @return array<string, mixed> */
    private function sanitizeInutilizationResponse(array $response): array
    {
        return collect($response)->only([
            'status', 'status_sefaz', 'mensagem_sefaz', 'serie',
            'numero_inicial', 'numero_final', 'modelo', 'cnpj',
            'caminho_xml', 'protocolo_sefaz',
        ])->all();
    }
}
