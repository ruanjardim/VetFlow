<?php

namespace App\Modules\PurchaseEntries\Services;

use App\Modules\PurchaseEntries\Exceptions\NfeAccessKeyLookupException;
use DirectoryIterator;
use FilesystemIterator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

class NfeAccessKeyImportService
{
    public function __construct(private readonly NfeXmlImportService $xmlImporter) {}

    public function import(
        string $accessKey,
        bool $createMissingProducts = true,
        bool $createMissingSupplier = true,
        ?int $clinicId = null
    ): array {
        $accessKey = $this->normalizeAccessKey($accessKey);

        if (strlen($accessKey) !== 44) {
            throw new InvalidArgumentException('Informe uma chave de acesso da NF-e com 44 digitos.');
        }

        $diagnostics = [];
        $resolved = $this->resolveXml($accessKey, $diagnostics);

        if (! $resolved) {
            throw new NfeAccessKeyLookupException(
                'NF-e completa nao encontrada pela chave.',
                $diagnostics
            );
        }

        $payload = $this->xmlImporter->import(
            $resolved['xml'],
            $createMissingProducts,
            $createMissingSupplier,
            $clinicId,
            $accessKey
        );
        $payload['message'] = 'NF-e carregada pela chave. '.$payload['message'];
        $payload['lookup'] = [
            'source' => $resolved['source'],
            'file' => isset($resolved['path']) ? basename((string) $resolved['path']) : null,
            'diagnostics' => $this->publicDiagnostics($diagnostics),
        ];

        $this->cacheXml($accessKey, $resolved['xml']);

        return $payload;
    }

    public function rememberXml(string $accessKey, string $xml): void
    {
        $accessKey = $this->normalizeAccessKey($accessKey);

        if (
            strlen($accessKey) === 44
            && trim($xml) !== ''
            && strlen($xml) <= $this->maxXmlBytes()
        ) {
            $this->cacheXml($accessKey, $xml);
        }
    }

    private function resolveXml(string $accessKey, array &$diagnostics): ?array
    {
        return $this->findCachedXml($accessKey, $diagnostics)
            ?: $this->findLocalXml($accessKey, $diagnostics)
            ?: $this->fetchExternalXml($accessKey, $diagnostics);
    }

    private function findCachedXml(string $accessKey, array &$diagnostics): ?array
    {
        $path = storage_path('app/nfe-xml-cache/'.$accessKey.'.xml');
        $diagnostic = [
            'source' => 'Cache VetFlow',
            'path' => $path,
            'status' => 'not_found',
            'message' => 'XML ainda nao esta no cache interno.',
        ];

        if (! is_file($path) || ! is_readable($path)) {
            $diagnostics[] = $diagnostic;

            return null;
        }

        if (filesize($path) > $this->maxXmlBytes()) {
            $diagnostic['status'] = 'too_large';
            $diagnostic['message'] = 'Arquivo de cache excede o limite permitido.';
            $diagnostics[] = $diagnostic;

            return null;
        }

        $xml = file_get_contents($path);

        if (is_string($xml) && str_contains($xml, $accessKey)) {
            $diagnostic['status'] = 'found';
            $diagnostic['message'] = 'XML encontrado no cache interno.';
            $diagnostics[] = $diagnostic;

            return [
                'xml' => $xml,
                'source' => 'vetflow_key_cache',
                'path' => $path,
            ];
        }

        $diagnostic['status'] = 'invalid';
        $diagnostic['message'] = 'Arquivo de cache existe, mas nao corresponde a chave.';
        $diagnostics[] = $diagnostic;

        return null;
    }

    private function findLocalXml(string $accessKey, array &$diagnostics): ?array
    {
        $maxFiles = max(1, (int) config('nfe_import.max_local_xml_files', 1000));
        $totalChecked = 0;

        foreach ($this->xmlSearchRoots() as $root) {
            $checked = 0;
            $rootDiagnostic = [
                'source' => 'Arquivo local',
                'path' => $root['path'],
                'status' => 'not_found',
                'message' => 'Nenhum XML correspondente encontrado nesta origem.',
            ];

            if (! is_file($root['path']) && ! is_dir($root['path'])) {
                $rootDiagnostic['status'] = 'missing';
                $rootDiagnostic['message'] = 'Pasta ou arquivo nao existe neste ambiente.';
                $diagnostics[] = $rootDiagnostic;

                continue;
            }

            foreach ($this->xmlFiles($root['path'], $root['recursive']) as $file) {
                $checked++;
                $totalChecked++;
                $xml = $this->readXmlIfMatches($file, $accessKey);

                if ($xml !== null) {
                    $rootDiagnostic['status'] = 'found';
                    $rootDiagnostic['path'] = $file->getPathname();
                    $rootDiagnostic['message'] = 'XML encontrado em arquivo local.';
                    $rootDiagnostic['checked_files'] = $checked;
                    $diagnostics[] = $rootDiagnostic;

                    return [
                        'xml' => $xml,
                        'source' => 'local_xml_archive',
                        'path' => $file->getPathname(),
                    ];
                }

                if ($totalChecked >= $maxFiles) {
                    $rootDiagnostic['status'] = 'limit_reached';
                    $rootDiagnostic['message'] = "A busca local foi interrompida apos {$maxFiles} XMLs.";
                    $rootDiagnostic['checked_files'] = $checked;
                    $diagnostics[] = $rootDiagnostic;

                    return null;
                }
            }

            $rootDiagnostic['checked_files'] = $checked;

            if ($checked === 0 && is_dir($root['path'])) {
                $rootDiagnostic['status'] = 'empty_or_unreadable';
                $rootDiagnostic['message'] = 'Nao consegui listar XMLs nesta pasta ou ela esta vazia.';
            }

            $diagnostics[] = $rootDiagnostic;
        }

        return null;
    }

    private function fetchExternalXml(string $accessKey, array &$diagnostics): ?array
    {
        $url = trim((string) config('nfe_import.key_lookup.url', ''));
        $diagnostic = [
            'source' => 'Integracao fiscal',
            'path' => $url ?: null,
            'status' => 'not_configured',
            'message' => 'Nenhuma API fiscal configurada em NFE_KEY_LOOKUP_URL.',
        ];

        if ($url === '') {
            $diagnostics[] = $diagnostic;

            return null;
        }

        try {
            $request = Http::timeout(max(1, (int) config('nfe_import.key_lookup.timeout_seconds', 10)))
                ->connectTimeout(max(1, (int) config('nfe_import.key_lookup.connect_timeout_seconds', 3)))
                ->accept('*/*');
            $token = trim((string) config('nfe_import.key_lookup.token', ''));

            if ($token !== '') {
                $request = $request->withToken($token);
            }

            $response = $this->externalRequest($request, $url, $accessKey);

            $diagnostic['status'] = match (true) {
                $response->successful() => 'connected',
                $response->status() === 404 => 'not_found',
                default => 'http_error',
            };
            $diagnostic['http_status'] = $response->status();

            if (! $response->successful()) {
                $diagnostic['message'] = $response->status() === 404
                    ? 'A integracao fiscal nao encontrou XML para esta chave.'
                    : 'A integracao fiscal respondeu sem sucesso.';
                $diagnostics[] = $diagnostic;

                return null;
            }

            $body = trim($response->body());

            if (strlen($body) > ($this->maxXmlBytes() * 2)) {
                $diagnostic['status'] = 'too_large';
                $diagnostic['message'] = 'API fiscal retornou um conteudo acima do limite permitido.';
                $diagnostics[] = $diagnostic;

                return null;
            }

            $xml = $this->xmlFromExternalValue($body);

            if (! $xml) {
                $data = $response->json();
                $xml = is_array($data) ? $this->xmlFromExternalPayload($data) : null;
            }

            if (is_string($xml) && trim($xml) !== '' && strlen($xml) <= $this->maxXmlBytes()) {
                $diagnostic['status'] = 'found';
                $diagnostic['message'] = 'XML retornado pela integracao fiscal.';
                $diagnostics[] = $diagnostic;

                return ['xml' => $xml, 'source' => 'external_key_lookup'];
            }

            $diagnostic['status'] = is_string($xml) ? 'too_large' : 'empty';
            $diagnostic['message'] = is_string($xml)
                ? 'API fiscal retornou XML acima do limite permitido.'
                : 'API respondeu, mas nao retornou XML reconhecido.';
            $diagnostics[] = $diagnostic;

            return null;
        } catch (Throwable $exception) {
            $diagnostic['status'] = 'error';
            $diagnostic['message'] = 'Nao foi possivel consultar a integracao fiscal agora.';
            $diagnostic['exception'] = $exception::class;
            $diagnostics[] = $diagnostic;

            return null;
        }
    }

    private function xmlSearchRoots(): array
    {
        $paths = [
            ['path' => storage_path('app/nfe-xml-cache'), 'recursive' => false],
            ['path' => storage_path('app/nfe-xml'), 'recursive' => true],
            ['path' => storage_path('app/nfe_xml'), 'recursive' => true],
            ['path' => storage_path('app/nfe-xml-archive'), 'recursive' => true],
            ['path' => storage_path('app/private/nfe-xml'), 'recursive' => true],
            ['path' => base_path('nfe-xml'), 'recursive' => true],
        ];

        $configured = trim((string) config('nfe_import.archive_paths', ''));

        if ($configured !== '') {
            foreach (preg_split('/[;|]/', $configured) ?: [] as $path) {
                $path = trim($path);

                if ($path !== '') {
                    array_unshift($paths, ['path' => $path, 'recursive' => true]);
                }
            }
        }

        if ($downloads = $this->downloadsPath()) {
            $paths[] = ['path' => $downloads, 'recursive' => false];
        }

        return array_values(array_filter($paths, fn (array $root) => is_string($root['path']) && $root['path'] !== ''));
    }

    private function xmlFiles(string $path, bool $recursive): iterable
    {
        if (is_file($path)) {
            if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'xml') {
                yield new SplFileInfo($path);
            }

            return;
        }

        if (! is_dir($path) || ! is_readable($path)) {
            return;
        }

        try {
            $iterator = $recursive
                ? new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
                )
                : new DirectoryIterator($path);

            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->isFile() && strtolower($file->getExtension()) === 'xml') {
                    yield $file;
                }
            }
        } catch (Throwable) {
            return;
        }
    }

    private function readXmlIfMatches(SplFileInfo $file, string $accessKey): ?string
    {
        if (! $file->isReadable() || $file->getSize() > $this->maxXmlBytes()) {
            return null;
        }

        $content = file_get_contents($file->getPathname());

        return is_string($content) && str_contains($content, $accessKey) ? $content : null;
    }

    private function cacheXml(string $accessKey, string $xml): void
    {
        if (strlen($xml) > $this->maxXmlBytes() || ! str_contains($xml, $accessKey)) {
            return;
        }

        try {
            $directory = storage_path('app/nfe-xml-cache');
            File::ensureDirectoryExists($directory);
            File::put($directory.DIRECTORY_SEPARATOR.$accessKey.'.xml', $xml);
        } catch (Throwable) {
            //
        }
    }

    private function xmlFromExternalPayload(array $payload): ?string
    {
        foreach (['xml', 'nfe_xml', 'content', 'body', 'data'] as $key) {
            $value = $payload[$key] ?? null;

            if (is_array($value)) {
                $xml = $this->xmlFromExternalPayload($value);

                if ($xml) {
                    return $xml;
                }
            }

            if (is_string($value) && ($xml = $this->xmlFromExternalValue($value))) {
                return $xml;
            }
        }

        return null;
    }

    private function xmlFromExternalValue(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '<')) {
            return $value;
        }

        $decoded = base64_decode($value, true);

        return is_string($decoded) && str_starts_with(trim($decoded), '<')
            ? trim($decoded)
            : null;
    }

    private function downloadsPath(): ?string
    {
        $home = getenv('USERPROFILE') ?: getenv('HOME') ?: ($_SERVER['USERPROFILE'] ?? null) ?: ($_SERVER['HOME'] ?? null);

        if (! $home) {
            return null;
        }

        $downloads = rtrim($home, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'Downloads';

        return is_dir($downloads) ? $downloads : null;
    }

    private function normalizeAccessKey(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function maxXmlBytes(): int
    {
        return max(1, (int) config('nfe_import.max_xml_bytes', 5 * 1024 * 1024));
    }

    private function publicDiagnostics(array $diagnostics): array
    {
        return array_map(
            fn (array $diagnostic) => array_filter([
                'source' => $diagnostic['source'] ?? null,
                'status' => $diagnostic['status'] ?? null,
                'message' => $diagnostic['message'] ?? null,
                'http_status' => $diagnostic['http_status'] ?? null,
                'checked_files' => $diagnostic['checked_files'] ?? null,
            ], fn ($value) => $value !== null),
            $diagnostics
        );
    }

    private function externalRequest(PendingRequest $request, string $url, string $accessKey): Response
    {
        $attempts = max(1, min(3, (int) config('nfe_import.key_lookup.attempts', 2)));
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = str_contains($url, '{key}')
                    ? $request->get(str_replace('{key}', $accessKey, $url))
                    : $request->get($url, ['key' => $accessKey]);

                if (! in_array($response->status(), [429, 500, 502, 503, 504], true) || $attempt === $attempts) {
                    return $response;
                }
            } catch (Throwable $exception) {
                $lastException = $exception;

                if ($attempt === $attempts) {
                    throw $exception;
                }
            }
        }

        throw $lastException ?? new NfeAccessKeyLookupException(
            'Nao foi possivel consultar a integracao fiscal.'
        );
    }
}
