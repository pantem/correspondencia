<?php

use App\Http\Controllers\Api\Admin\AreaController;
use App\Http\Controllers\Api\Admin\CargoController;
use App\Http\Controllers\Api\Admin\TitularController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\MovimientoController;
use App\Http\Controllers\Api\TurnoController;
use App\Http\Controllers\Api\VolanteController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('auth.login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');

    // Portada con KPIs (RF-61).
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Exportaciones (RF-64): deben ir antes de las rutas con `{document}`/`{turno}`.
    Route::get('/documents/export', [ExportController::class, 'documentos'])->name('documents.export');
    Route::get('/turnos/export', [ExportController::class, 'turnos'])->name('turnos.export');

    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('/documents/{document}/archivos/{archivo}', [DocumentController::class, 'showArchivo'])->name('documents.archivos.show');
    Route::get('/documents/{document}/turnos', [TurnoController::class, 'turnosDocumento'])->name('documents.turnos.index');
    Route::get('/documents/{document}/historial', [DocumentController::class, 'historial'])->name('documents.historial');

    // Volantes de un documento o turno (RF-30, RF-35, RF-38).
    Route::get('/documents/{document}/volantes', [VolanteController::class, 'documento'])->name('documents.volantes.index');
    Route::get('/turnos/{turno}/volantes', [VolanteController::class, 'turno'])->name('turnos.volantes.index');
    Route::middleware('can:turns.update')->group(function () {
        Route::post('/turnos/{turno}/volantes', [VolanteController::class, 'store'])->name('turnos.volantes.store');
    });
    Route::get('/volantes/{volante}', [VolanteController::class, 'show'])->name('volantes.show');
    Route::get('/volantes/{volante}/pdf', [VolanteController::class, 'pdf'])->name('volantes.pdf');

    // Acuse de recibo digitalizado por movimiento (RF-47, RF-50).
    Route::post('/movimientos/{movimiento}/acuse', [MovimientoController::class, 'subirAcuse'])->middleware('can:documents.upload')->name('movimientos.acuse.store');
    Route::post('/movimientos/{movimiento}/resolucion', [MovimientoController::class, 'subirResolucion'])->middleware('can:documents.upload')->name('movimientos.resolucion.store');

    Route::middleware('can:documents.turn')->group(function () {
        Route::post('/documents/{document}/turnos', [TurnoController::class, 'store'])->name('documents.turnos.store');
    });

    Route::get('/turnos', [TurnoController::class, 'index'])->name('turnos.index');
    Route::get('/turnos/{turno}', [TurnoController::class, 'show'])->name('turnos.show');
    Route::post('/turnos/{turno}/reasignar', [TurnoController::class, 'reasignar'])->name('turnos.reasignar');
    Route::post('/turnos/{turno}/seguimiento', [TurnoController::class, 'seguimiento'])->middleware('can:turns.respond')->name('turnos.seguimiento');
    Route::post('/turnos/{turno}/resolver', [TurnoController::class, 'resolver'])->middleware('can:turns.respond')->name('turnos.resolver');
    Route::middleware('can:turns.update')->group(function () {
        Route::put('/turnos/{turno}', [TurnoController::class, 'update'])->name('turnos.update');
        Route::post('/turnos/{turno}/cancelar', [TurnoController::class, 'cancelar'])->name('turnos.cancelar');
    });

    Route::middleware('can:documents.create')->group(function () {
        Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    });

    Route::middleware('can:documents.update')->group(function () {
        Route::put('/documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
    });

    Route::middleware('can:documents.upload')->group(function () {
        Route::post('/documents/{document}/archivos', [DocumentController::class, 'storeArchivo'])->name('documents.archivos.store');
    });

    Route::get('/catalogos/roles', [UserController::class, 'roles'])->name('catalogos.roles');
    Route::get('/catalogos/usuarios', [UserController::class, 'usuarios'])->name('catalogos.usuarios');

    Route::get('/admin/areas', [AreaController::class, 'index'])->name('admin.areas.index');
    Route::get('/admin/cargos', [CargoController::class, 'index'])->name('admin.cargos.index');
    Route::get('/admin/titulares', [TitularController::class, 'index'])->name('admin.titulares.index');

    Route::middleware('can:admin.catalogs')->group(function () {
        Route::post('/admin/areas', [AreaController::class, 'store'])->name('admin.areas.store');
        Route::put('/admin/areas/{area}', [AreaController::class, 'update'])->name('admin.areas.update');

        Route::post('/admin/cargos', [CargoController::class, 'store'])->name('admin.cargos.store');
        Route::put('/admin/cargos/{cargo}', [CargoController::class, 'update'])->name('admin.cargos.update');

        Route::post('/admin/titulares', [TitularController::class, 'store'])->name('admin.titulares.store');
        Route::put('/admin/titulares/{titular}', [TitularController::class, 'update'])->name('admin.titulares.update');
    });

    Route::middleware('can:admin.users')->group(function () {
        Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
        Route::post('/admin/users', [UserController::class, 'store'])->name('admin.users.store');
        Route::put('/admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
    });

    Route::middleware('can:audit.view')->group(function () {
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });
});
