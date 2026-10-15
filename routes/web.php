<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\ElectionController;
use App\Http\Controllers\RoundController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VoteController;
use App\Http\Middleware\EnsureActiveUser;
use Illuminate\Support\Facades\Route;

Route::get('/', [ElectionController::class, 'index'])->name('home');
Route::middleware(['auth', EnsureActiveUser::class])->group(function () {
    Route::get('dashboard', [ElectionController::class, 'index'])->name('dashboard');
    Route::get('admin/eleicoes', [ElectionController::class, 'admin'])->name('admin.elections');
    Route::post('admin/eleicoes', [ElectionController::class, 'store'])->name('elections.store');
    Route::post('admin/eleicoes/{election}/turnos', [RoundController::class, 'store'])->name('rounds.store');
    Route::post('admin/turnos/{round}/encerrar', [RoundController::class, 'close'])->name('rounds.close');
    Route::post('turnos/{round}/votos', [VoteController::class, 'store'])->name('votes.store');
    Route::get('auditoria', [AuditController::class, 'index'])->name('audit.index');
    Route::get('admin/usuarios', [UserController::class, 'index'])->name('admin.users');
    Route::patch('admin/usuarios/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('admin/usuarios/{user}', [UserController::class, 'destroy'])->name('users.destroy');
});
require __DIR__.'/settings.php';
