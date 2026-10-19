<?php

use App\Http\Controllers\Web\MaintenanceController as M;
use Illuminate\Support\Facades\Route;

Route::get('maintenance/plans', [M::class, 'index'])->name('maintenance');
Route::post('maintenance/plans', [M::class, 'save'])->name('maintenance.store');
Route::post('maintenance/materials/receipts', [M::class, 'stock'])->name('maintenance.stock');
Route::get('maintenance/evidence/{evidence}', [M::class, 'download'])->whereNumber('evidence')->name('maintenance.evidence');
Route::get('maintenance/plans/{plan}', [M::class, 'show'])->whereNumber('plan')->name('maintenance.show');
Route::put('maintenance/plans/{plan}', [M::class, 'save'])->whereNumber('plan')->name('maintenance.update');
Route::post('maintenance/plans/{plan}/orders', [M::class, 'order'])->whereNumber('plan')->name('maintenance.order');
Route::post('maintenance/orders/{order}/evidence', [M::class, 'upload'])->whereNumber('order')->name('maintenance.upload');
Route::post('maintenance/orders/{order}/adopt', [M::class, 'adopt'])->whereNumber('order')->name('maintenance.adopt');
Route::post('maintenance/orders/{order}/{action}', [M::class, 'act'])->whereNumber('order')->whereIn('action', ['authorize', 'assign', 'start', 'task', 'complete', 'approve', 'release', 'keep-unavailable'])->name('maintenance.action');
