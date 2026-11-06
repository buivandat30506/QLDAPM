<?php

use App\Http\Controllers\Web\AdministrationController;
use App\Http\Middleware\ActiveUser;
use Illuminate\Support\Facades\Route;

Route::prefix('administration')->name('administration.')->middleware(['auth', ActiveUser::class])->group(function () {
    Route::middleware('permission:users.manage')->group(function () {
        Route::get('users', [AdministrationController::class, 'users'])->name('users');
        Route::post('users', [AdministrationController::class, 'save'])->name('users.store');
        Route::get('users/{user}', [AdministrationController::class, 'edit'])->name('users.edit');
        Route::put('users/{id}', [AdministrationController::class, 'save'])->name('users.update');
        Route::post('delegations', [AdministrationController::class, 'delegate'])->name('delegations.store');
        Route::post('delegations/{id}/revoke', [AdministrationController::class, 'revoke'])->name('delegations.revoke');
    });
    Route::get('audit', [AdministrationController::class, 'audit'])->middleware('permission:audit.read')->name('audit');
    Route::get('audit/export', [AdministrationController::class, 'export'])->middleware('permission:audit.export')->name('audit.export');
});
