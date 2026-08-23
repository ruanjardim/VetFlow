<?php

namespace App\Console\Commands;

use App\Support\Operations\DatabaseRestoreVerificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class CaptureBackupManifestCommand extends Command
{
    protected $signature = 'vetflow:backup:snapshot
        {--identifier= : Identificador operacional do backup exportado}
        {--connection= : Conexao de origem; usa a conexao padrao quando omitida}
        {--output= : Caminho do manifesto JSON sem dados pessoais}';

    protected $description = 'Captura totais de controle para comprovar depois uma restauracao isolada.';

    public function __construct(
        private readonly DatabaseRestoreVerificationService $verification
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $identifier = trim((string) $this->option('identifier'));

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,119}$/', $identifier) !== 1) {
            $this->error('Informe --identifier com 3 a 120 caracteres seguros (letras, numeros, ponto, hifen ou sublinhado).');

            return self::FAILURE;
        }

        $connection = trim((string) $this->option('connection')) ?: (string) config('database.default');
        $output = trim((string) $this->option('output')) ?: storage_path(
            'app/private/backup-drills/'.$identifier.'-'.now()->format('Ymd-His').'-manifest.json'
        );

        try {
            $manifest = $this->verification->capture($connection, $identifier);
            File::ensureDirectoryExists(dirname($output));
            File::put($output, json_encode(
                $manifest,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ).PHP_EOL);
        } catch (Throwable $exception) {
            $this->error('Nao foi possivel capturar o manifesto: '.$exception->getMessage());

            return self::FAILURE;
        }

        $present = collect($manifest['tables'])->where('exists', true)->count();
        $this->info('Manifesto de backup criado sem dados pessoais.');
        $this->line("Identificador: {$identifier}");
        $this->line("Tabelas controladas: {$present}");
        $this->line('SHA-256: '.hash_file('sha256', $output));
        $this->line('Arquivo: '.$output);

        return self::SUCCESS;
    }
}
