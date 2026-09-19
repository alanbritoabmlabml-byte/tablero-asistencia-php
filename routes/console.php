<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment('Los datos no deciden: ayudan a decidir.');
})->purpose('Mensaje del dia');
