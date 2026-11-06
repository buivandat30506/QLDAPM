<?php

use App\Http\Controllers\Api\Core\WorkflowController as Workflow;
use Illuminate\Support\Facades\Route;

require __DIR__.'/maintenance.php';

Route::put('airports/resources/{id}', [Workflow::class, 'resourceUpdate']);

Route::get('master-data', [Workflow::class, 'masterIndex']);
Route::get('master-data/export', [\App\Http\Controllers\Api\Core\FoundationController::class, 'export']);
Route::post('master-data/imports', [\App\Http\Controllers\Api\Core\FoundationController::class, 'preview']);
Route::get('master-data/imports/{batch}', [\App\Http\Controllers\Api\Core\FoundationController::class, 'batch']);
Route::post('master-data/imports/{batch}/commit', [\App\Http\Controllers\Api\Core\FoundationController::class, 'commit']);
Route::get('master-data/{id}', [\App\Http\Controllers\Api\Core\FoundationController::class, 'show'])->whereNumber('id');
Route::get('master-data/{id}/published', [\App\Http\Controllers\Api\Core\FoundationController::class, 'published'])->whereNumber('id');
Route::post('master-data', [Workflow::class, 'masterSave']);
Route::put('master-data/{id}', [Workflow::class, 'masterSave'])->whereNumber('id');
Route::post('master-data/{id}/{action}', [Workflow::class, 'masterAction'])->whereNumber('id')->whereIn('action', ['submit', 'approve', 'publish', 'revise', 'reject']);
Route::put('schedules/{id}', [Workflow::class, 'scheduleSave']);
Route::get('schedules/{id}', [\App\Http\Controllers\Web\PlanningController::class, 'apiSchedule']);
Route::post('schedules/{id}/check', [\App\Http\Controllers\Web\PlanningController::class, 'apiCheck']);
Route::post('schedules/{id}/{action}', [Workflow::class, 'scheduleAction'])->whereIn('action', ['submit', 'approve', 'publish', 'revise', 'reject', 'cancel']);
Route::get('maintenance', [Workflow::class, 'maintenanceIndex']);
Route::post('maintenance', [Workflow::class, 'maintenancePlan']);
Route::post('maintenance/{id}/{action}', [Workflow::class, 'maintenanceAction'])->whereIn('action', ['start', 'complete', 'approve', 'release']);
Route::get('events', [Workflow::class, 'eventIndex']);
Route::post('flights/bulk/{action}', [\App\Http\Controllers\Web\FlightOperationsController::class, 'bulk'])->whereIn('action',['preview','commit']);
Route::post('events', [Workflow::class, 'eventReceive']);
Route::post('events/{event}/retry', [\App\Http\Controllers\Web\FlightOperationsController::class, 'retry']);
Route::get('flights/{id}/operations', [\App\Http\Controllers\Web\FlightOperationsController::class, 'apiDetail']);
Route::post('flights/{id}/operations/{action}', [\App\Http\Controllers\Web\FlightOperationsController::class, 'act'])->whereIn('action',['status','events','proposals','checkin','close','milestone']);
Route::post('flights/{id}/proposals/{proposal}/decision', [\App\Http\Controllers\Web\FlightOperationsController::class, 'decide']);
Route::post('flights/{id}/outbox/{message}/retry', [\App\Http\Controllers\Web\FlightOperationsController::class, 'retryOutbox']);
Route::post('flights/{id}/resolve', [Workflow::class, 'resolve']);
Route::post('flights/{id}/crew/approve', [Workflow::class, 'crewApprove']);
Route::post('crew-assignments/{id}/confirm', [Workflow::class, 'crewConfirm']);
Route::post('crew-assignments/{id}/replace', [Workflow::class, 'crewReplace']);
Route::get('audit', [Workflow::class, 'history']);
Route::get('versions', [Workflow::class, 'versions']);
