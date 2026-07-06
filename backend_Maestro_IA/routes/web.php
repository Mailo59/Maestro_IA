<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/run-migrations-xyz', function () {
    try {
        // Forzamos manualmente los drivers a 'file' en tiempo de ejecución
        // Esto ignora cualquier caché viejo de configuración que tenga el contenedor
        config(['cache.default' => 'file']);
        config(['session.driver' => 'file']);

        // Ejecutamos las migraciones en Supabase
        Artisan::call('migrate', ['--force' => true]);
        $migrateOutput = Artisan::output();

        return '<h3>Proceso Completado</h3>' .
            '<pre><b>Resultado de la Migración:</b><br>' . $migrateOutput . '</pre>';
    } catch (\Exception $e) {
        return '<h3>Error al migrar:</h3><pre>' . $e->getMessage() . '</pre>';
    }
});
