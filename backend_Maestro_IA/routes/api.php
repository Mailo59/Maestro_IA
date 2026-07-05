<?php

use App\Http\Controllers\API\TaskController;
use App\Http\Controllers\API\AdminDashboardController;
use App\Http\Controllers\API\AdminAccessController;
use App\Http\Controllers\API\AgendaItemController;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => 'Maestro IA API',
        'message' => 'Backend Laravel conectado correctamente.',
    ]);
});

Route::options('/{any}', fn () => response()->noContent())->where('any', '.*');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/dashboard', AdminDashboardController::class);
        Route::delete('/admin/tasks/{task}', [AdminDashboardController::class, 'destroy']);
        Route::patch('/admin/tasks/{task}/complete', [AdminDashboardController::class, 'complete']);
        Route::patch('/admin/tasks/{task}/reject', [AdminDashboardController::class, 'reject']);
        Route::get('/admin/access', [AdminAccessController::class, 'index']);
        Route::post('/admin/roles', [AdminAccessController::class, 'storeRole']);
        Route::patch('/admin/users/{user}/role', [AdminAccessController::class, 'updateUserRole']);
        Route::post('/admin/role-screens', [AdminAccessController::class, 'storeScreen']);
        Route::patch('/admin/role-screens/{screen}', [AdminAccessController::class, 'updateScreen']);
    });

    Route::middleware('role:student')->group(function () {
        Route::get('/agenda-items', [AgendaItemController::class, 'index']);
        Route::post('/agenda-items', [AgendaItemController::class, 'store']);
        Route::patch('/agenda-items/{agendaItem}/toggle', [AgendaItemController::class, 'toggle']);
        Route::delete('/agenda-items/{agendaItem}', [AgendaItemController::class, 'destroy']);
        Route::get('/tasks', [TaskController::class, 'index']);
        Route::post('/tasks', [TaskController::class, 'store']);
        Route::get('/tasks/{task}', [TaskController::class, 'show']);
        Route::post('/tasks/{task}/messages', [TaskController::class, 'continueConversation']);
        Route::post('/tasks/{task}/finished', [TaskController::class, 'markFinished']);
    });
});
