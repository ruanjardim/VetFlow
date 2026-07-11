<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('vetflow:status', function () {
    $this->info('VetFlow pronto para diagnostico.');
})->purpose('Mostra o status basico do VetFlow');
