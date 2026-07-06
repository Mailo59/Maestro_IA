<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/run-migrations-xyz', function () {
    try {
        // Obliga al contenedor de Docker a limpiar el caché de rutas generado en el build
        Artisan::call('optimize:clear');
        $clearOutput = Artisan::output();

        // Ejecuta las migraciones en Supabase
        Artisan::call('migrate', ['--force' => true]);
        $migrateOutput = Artisan::output();

        return '<h3>Proceso Completado</h3>' .
            '<pre><b>Caché Limpiado:</b><br>' . $clearOutput . '</pre>' .
            '<pre><b>Resultado de la Migración:</b><br>' . $migrateOutput . '</pre>';
    } catch (\Exception $e) {
        return '<h3>Error al migrar:</h3><pre>' . $e->getMessage() . '</pre>';
    }
});
