<?php

namespace App\Modules\PurchaseEntries\Services;

use App\Modules\PurchaseEntries\Exceptions\NfeAccessKeyLookupException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use SplFileInfo;
use Throwable;

class NfeAccessKeyImportService
{
    private const MAX_XML_SIZE = 10 * 1024 * 1024;

    public function __construct(private readonly NfeXmlImportService $xmlImporter)
    {
    }

    public function import(string $accessKey, bool $createMissingProducts = true, bool $createMissingSupplier = true): array
    {
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

        $payload = $this->xmlImporter->import($resolved['xml'], $createMissingProducts, $createMissingSupplier);
        $payload['message'] = 'NF-e carregada pela chave. '.$payload['message'];
        $payload['lookup'] = [
            'source' => $resolved['source'],
            'path' => $resolved['path'] ?? null,
            'diagnostics' => $diagnostics,
        ];

        $this->cacheXml($accessKey, $resolved['xml']);

        return $payload;
    }

    public function rememberXml(string $accessKey, string $xml): void
    {
        $accessKey = $this->normalizeAccessKey($accessKey);

        if (strlen($accessKey) === 44 && trim($xml) !== '') {
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
        $url = trim((string) env('NFE_KEY_LOOKUP_URL', ''));
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
            $request = Http::timeout(20)->accept('*/*');
            $token = trim((string) env('NFE_KEY_LOOKUP_TOKEN', ''));

            if ($token !== '') {
                $request = $request->withToken($token);
            }

            $response = str_contains($url, '{key}')
                ? $request->get(str_replace('{key}', $accessKey, $url))
                : $request->get($url, ['key' => $accessKey]);

            $diagnostic['status'] = $response->successful() ? 'connected' : 'http_error';
            $diagnostic['http_status'] = $response->status();

            if (! $response->successful()) {
                $diagnostic['message'] = 'API fiscal respondeu sem sucesso.';
                $diagnostics[] = $diagnostic;
                return null;
            }

            $body = trim($response->body());
            $xml = $this->xmlFromExternalValue($body);

            if (! $xml) {
                $data = $response->json();
                $xml = is_array($data) ? $this->xmlFromExternalPayload($data) : null;
            }

            if (is_string($xml) && trim($xml) !== '') {
                $diagnostic['status'] = 'found';
                $diagnostic['message'] = 'XML retornado pela integracao fiscal.';
                $diagnostics[] = $diagnostic;

                return ['xml' => $xml, 'source' => 'external_key_lookup'];
            }

            $diagnostic['status'] = 'empty';
            $diagnostic['message'] = 'API respondeu, mas nao retornou XML reconhecido.';
            $diagnostics[] = $diagnostic;

            return null;
        } catch (Throwable $exception) {
            $diagnostic['status'] = 'error';
            $diagnostic['message'] = 'Erro ao consultar integracao fiscal: '.$exception->getMessage();
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

        $configured = trim((string) env('NFE_XML_ARCHIVE_PATH', ''));

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
                ? File::allFiles($path)
                : File::files($path);

            foreach ($iterator as $file) {
                if (strtolower($file->getExtension()) === 'xml') {
                    yield $file;
                }
            }
        } catch (Throwable) {
            return;
        }
    }

    private function readXmlIfMatches(SplFileInfo $file, string $accessKey): ?string
    {
        if (! $file->isReadable() || $file->getSize() > self::MAX_XML_SIZE) {
            return null;
        }

        $content = file_get_contents($file->getPathname());

        return is_string($content) && str_contains($content, $accessKey) ? $content : null;
    }

    private function cacheXml(string $accessKey, string $xml): void
    {
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
}
