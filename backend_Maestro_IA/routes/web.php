<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/run-migrations-xyz', function () {
    try {
        Artisan::call('migrate', ['--force' => true]);
        return 'Migraciones ejecutadas con éxito: ' . Artisan::output();
    } catch (\Exception $e) {
        return 'Error al migrar: ' . $e->getMessage();
    }
});
