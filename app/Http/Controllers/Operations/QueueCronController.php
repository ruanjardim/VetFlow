<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class QueueCronController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless((bool) config('operations.queue.cron.enabled'), 404);

        $expectedToken = (string) config('operations.queue.cron.token');

        if (mb_strlen($expectedToken) < 32) {
            Log::critical('Queue cron indisponivel porque o token operacional nao atende ao tamanho minimo.');

            return response()->json(['status' => 'unavailable'], 503, ['Cache-Control' => 'no-store']);
        }

        $header = (string) config('operations.queue.cron.header', 'X-Cron-Auth');
        $providedToken = (string) $request->header($header, '');

        if ($header === '' || ! hash_equals($expectedToken, $providedToken)) {
            Log::warning('Tentativa rejeitada de acionar o cron da fila.');

            abort(403);
        }

        $exitCode = Artisan::call('vetflow:queue:drain', [
            '--max-jobs' => (int) config('operations.queue.cron.max_jobs'),
            '--max-time' => (int) config('operations.queue.cron.max_time'),
            '--timeout' => (int) config('operations.queue.cron.timeout'),
            '--tries' => (int) config('operations.queue.cron.tries'),
        ]);

        if ($exitCode !== Command::SUCCESS) {
            Log::error('O ciclo controlado da fila terminou com falha.', ['exit_code' => $exitCode]);

            return response()->json(['status' => 'failed'], 503, ['Cache-Control' => 'no-store']);
        }

        return response()->noContent(204, ['Cache-Control' => 'no-store']);
    }
}
