<?php

namespace App\Console\Commands;

use App\Support\Demo\WalkthroughDemoFixture;
use App\Support\Demo\WalkthroughDemoManager;
use Database\Seeders\WalkthroughDemoSeeder;
use Illuminate\Console\Command;

class ResetWalkthroughDemoCommand extends Command
{
    protected $signature = 'vetflow:demo:reset
        {--reseed : Recria os dados do walkthrough depois da limpeza}
        {--force : Ignora a confirmacao interativa}';

    protected $description = 'Remove seletivamente os dados ficticios do walkthrough em ambiente local ou de testes.';

    public function handle(WalkthroughDemoManager $manager): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('O reset da demo so pode ser executado em ambiente local ou de testes.');

            return self::FAILURE;
        }

        $reseed = (bool) $this->option('reseed');
        $action = $reseed ? 'remover e recriar' : 'remover';

        if (! $this->option('force') && ! $this->confirm(
            "Confirma {$action} somente os dados identificados do walkthrough da clinica CNPJ "
            .WalkthroughDemoFixture::CLINIC_CNPJ.'?'
        )) {
            $this->warn('Reset da demo cancelado sem alteracoes.');

            return self::SUCCESS;
        }

        $summary = $manager->cleanup();

        $this->table(
            ['Tipo', 'Removidos'],
            collect($summary)
                ->map(fn (int $count, string $type): array => [$type, $count])
                ->values()
                ->all()
        );

        if ($reseed) {
            $exitCode = $this->call('db:seed', [
                '--class' => WalkthroughDemoSeeder::class,
                '--force' => true,
            ]);

            if ($exitCode !== self::SUCCESS) {
                $this->error('A limpeza terminou, mas a recriacao da demo falhou.');

                return self::FAILURE;
            }

            $this->info('Dados do walkthrough recriados com sucesso.');
        } else {
            $this->info('Dados identificados do walkthrough removidos com sucesso.');
        }

        return self::SUCCESS;
    }
}
