<?php

use App\Http\Controllers\Web\MasterDataController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/master-data')->name('admin.master.')->middleware(['auth', \App\Http\Middleware\ActiveUser::class])->group(function () {
    Route::get('/', [MasterDataController::class, 'index'])->name('index');
    Route::post('/', [MasterDataController::class, 'save'])->name('store');
    Route::get('/export', [MasterDataController::class, 'export'])->name('export');
    Route::get('/template', [MasterDataController::class, 'template'])->name('template');
    Route::post('/imports', [MasterDataController::class, 'preview'])->name('preview');
    Route::get('/imports/{batch}', [MasterDataController::class, 'batch'])->name('batch');
    Route::post('/imports/{batch}/commit', [MasterDataController::class, 'commit'])->name('commit');
    Route::get('/{id}', [MasterDataController::class, 'show'])->whereNumber('id')->name('show');
    Route::put('/{id}', [MasterDataController::class, 'save'])->whereNumber('id')->name('update');
    Route::post('/{id}/{action}', [MasterDataController::class, 'action'])->whereNumber('id')
        ->whereIn('action', ['submit', 'approve', 'publish', 'revise', 'reject'])->name('action');
});
