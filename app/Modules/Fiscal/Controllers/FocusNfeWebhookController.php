<?php

namespace App\Modules\Fiscal\Controllers;

use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Services\FiscalDocumentLifecycleService;
use App\Modules\Fiscal\Support\FiscalDocumentType;
use App\Modules\Fiscal\Support\FiscalProviderCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class FocusNfeWebhookController
{
    public function __construct(private readonly FiscalDocumentLifecycleService $lifecycle) {}

    public function __invoke(Request $request): Response|JsonResponse
    {
        $this->authenticate($request);

        $maximumBytes = max(1024, (int) config('fiscal.focus_nfe.webhook.max_payload_bytes', 131072));
        if (strlen($request->getContent()) > $maximumBytes) {
            return response()->json(['message' => 'Payload excede o limite permitido.'], 413);
        }

        $validator = Validator::make($request->json()->all(), [
            'ref' => ['required', 'string', 'max:160'],
            'status' => ['required', 'string', 'max:60'],
            'protocolo' => ['nullable', 'string', 'max:80'],
            'cnpj_prestador' => ['nullable', 'string', 'max:30'],
            'chave_nfse' => ['nullable', 'string', 'max:60'],
            'numero' => ['nullable', 'string', 'max:30'],
            'codigo_verificacao' => ['nullable', 'string', 'max:80'],
            'status_sefaz' => ['nullable', 'string', 'max:60'],
            'mensagem_sefaz' => ['nullable', 'string', 'max:2000'],
            'caminho_xml_nota_fiscal' => ['nullable', 'string', 'max:2000'],
            'caminho_danfe' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Webhook fiscal inválido.'], 422);
        }

        $payload = $validator->validated();
        $document = FiscalDocument::withoutGlobalScopes()
            ->where('provider_reference', $payload['ref'])
            ->where('provider', FiscalProviderCatalog::FOCUS_NFE)
            ->where('type', FiscalDocumentType::NFSE)
            ->whereIn('environment', ['homologation', 'production'])
            ->first();

        if (! $document) {
            return response()->noContent();
        }

        $receivedCnpj = $this->digits($payload['cnpj_prestador'] ?? null);
        $expectedCnpj = $this->digits(data_get($document->issuer_snapshot, 'document'));
        if ($receivedCnpj !== '' && ! hash_equals($expectedCnpj, $receivedCnpj)) {
            return response()->json(['message' => 'Emitente do webhook não corresponde ao documento.'], 422);
        }

        $this->lifecycle->applyProviderResponse($document->id, $payload, 'webhook');

        return response()->noContent();
    }

    private function authenticate(Request $request): void
    {
        $enabled = (bool) config('fiscal.focus_nfe.webhook.enabled', false);
        $token = (string) config('fiscal.focus_nfe.webhook.authorization_token', '');
        $header = (string) config('fiscal.focus_nfe.webhook.authorization_header', '');

        if (! $enabled || $token === '' || ! preg_match('/^[A-Za-z0-9-]+$/', $header)) {
            abort(404);
        }

        $provided = (string) $request->header($header, '');
        abort_unless($provided !== '' && hash_equals($token, $provided), 401);
    }

    private function digits(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?: '';
    }
}
