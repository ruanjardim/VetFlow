<?php

namespace App\Console\Commands;

use App\Support\Operations\DatabaseRestoreVerificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class VerifyBackupRestoreCommand extends Command
{
    protected $signature = 'vetflow:backup:verify
        {--manifest= : Caminho do manifesto criado no momento do backup}
        {--connection=backup_restore : Conexao exclusiva do banco restaurado}
        {--evidence= : Caminho da evidencia JSON do teste de restauracao}';

    protected $description = 'Compara um banco restaurado isolado com o manifesto do backup, sem alterar registros.';

    public function __construct(
        private readonly DatabaseRestoreVerificationService $verification
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $manifestPath = trim((string) $this->option('manifest'));
        $connection = trim((string) $this->option('connection'));

        if ($manifestPath === '' || ! File::isFile($manifestPath)) {
            $this->error('Informe um arquivo existente em --manifest.');

            return self::FAILURE;
        }

        try {
            $manifest = json_decode(File::get($manifestPath), true, flags: JSON_THROW_ON_ERROR);
            $evidence = $this->verification->verify($manifest, $connection);
            $evidencePath = trim((string) $this->option('evidence')) ?: storage_path(
                'app/private/backup-drills/'.$evidence['backup_identifier'].'-'.now()->format('Ymd-His').'-evidence.json'
            );
            File::ensureDirectoryExists(dirname($evidencePath));
            File::put($evidencePath, json_encode(
                $evidence,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ).PHP_EOL);
        } catch (Throwable $exception) {
            $this->error('Nao foi possivel verificar a restauracao: '.$exception->getMessage());

            return self::FAILURE;
        }

        $failed = collect($evidence['checks'])->where('passed', false);
        $this->table(
            ['Controle', 'Status'],
            collect($evidence['checks'])->map(fn (array $check): array => [
                $check['name'],
                $check['passed'] ? 'OK' : 'DIVERGENTE',
            ])->all()
        );
        $this->line('Evidencia: '.$evidencePath);

        if ($failed->isNotEmpty()) {
            $this->error("Restauracao reprovada por {$failed->count()} divergencia(s).");

            return self::FAILURE;
        }

        $this->info('Restauracao isolada verificada com sucesso.');

        return self::SUCCESS;
    }
}
