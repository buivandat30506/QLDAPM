<?php

use App\Http\Controllers\Web\AdminController;
use Illuminate\Support\Facades\Route;

Route::get('/health/ready', function (\App\Services\Administration\RuntimeHealth $health) {
    $status=$health->inspect()['status'];
    return response()->json(['status'=>$status], $status==='ready'?200:503)->header('Cache-Control','no-store');
});

Route::get('/', function () {
    return redirect()->route('admin.dashboard');
});

Route::get('/login', fn () => view('auth.login'))->name('login');
Route::post('/login', [\App\Http\Controllers\AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.store');
Route::post('/logout', [\App\Http\Controllers\AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/forgot-password', fn () => view('auth.forgot-password'))->name('password.request');
Route::post('/forgot-password', [\App\Http\Controllers\AuthController::class, 'requestReset'])->middleware('throttle:5,1')->name('password.email');
Route::get('/reset-password/{token}', fn (string $token) => view('auth.reset-password', compact('token')))->name('password.reset');
Route::post('/reset-password', [\App\Http\Controllers\AuthController::class, 'resetPassword'])->middleware('throttle:5,1')->name('password.update');
Route::get('/account', fn () => view('auth.account'))->middleware(['auth', \App\Http\Middleware\ActiveUser::class])->name('account');
Route::post('/account/password', [\App\Http\Controllers\AuthController::class, 'changePassword'])->middleware(['auth', \App\Http\Middleware\ActiveUser::class, 'throttle:5,1'])->name('account.password');

require __DIR__.'/administration.php';
require __DIR__.'/passenger-portal.php';
require __DIR__.'/foundation.php';

Route::prefix('admin')->middleware(['auth', \App\Http\Middleware\CoreAccess::class])->name('admin.')->group(function () {
    require __DIR__.'/maintenance.php';
    Route::get('/workflows', [\App\Http\Controllers\Web\WorkflowController::class, 'index'])->name('workflows');
    Route::post('/workflows', [\App\Http\Controllers\Web\WorkflowController::class, 'act'])->name('workflows.act');
    // 0. Dashboard
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');

    // 1. Quản lý Sân bay & Tài nguyên (UC-01, UC-02)
    Route::get('/airports', [AdminController::class, 'airports'])->name('airports');
    Route::post('/airports', [AdminController::class, 'storeAirport'])->name('airports.store');
    Route::get('/airports/{iataCode}', [AdminController::class, 'airport'])->name('airports.show');
    Route::put('/airports/{iataCode}', [AdminController::class, 'updateAirport'])->name('airports.update');
    Route::delete('/airports/{iataCode}', [AdminController::class, 'destroyAirport'])->name('airports.destroy');
    Route::post('/airports/{iataCode}/resources', [AdminController::class, 'storeAirportResource'])->name('airports.resources.store');
    Route::delete('/airports/resources/{resourceId}', [AdminController::class, 'destroyAirportResource'])->name('airports.resources.destroy');
    Route::put('/airports/resources/{resourceId}', [AdminController::class, 'updateAirportResource'])->name('airports.resources.update');

    // 2. Quản lý Tuyến & Đường bay (UC-04)
    Route::get('/routes', [\App\Http\Controllers\Web\PlanningController::class, 'routes'])->name('routes');
    Route::get('/routes/{id}', [\App\Http\Controllers\Web\PlanningController::class, 'route'])->name('routes.show');
    Route::post('/routes', [\App\Http\Controllers\Web\PlanningController::class, 'saveRoute'])->name('routes.store');
    Route::put('/routes/{id}', [\App\Http\Controllers\Web\PlanningController::class, 'saveRoute'])->name('routes.update');
    Route::put('/aircrafts/{tailNumber}', [\App\Http\Controllers\Web\FleetController::class, 'update'])->name('aircrafts.update');
    Route::put('/crew/{id}', [\App\Http\Controllers\Web\CrewWorkflowController::class, 'update'])->name('crew.update');
    Route::delete('/routes/{id}', [AdminController::class, 'destroyRoute'])->name('routes.destroy');

    // 3. Quản lý Đội tàu bay & Dòng tàu bay (UC-10, UC-11)
    Route::get('/aircrafts', [\App\Http\Controllers\Web\FleetController::class, 'index'])->name('aircrafts');
    Route::post('/aircrafts', [\App\Http\Controllers\Web\FleetController::class, 'store'])->name('aircrafts.store');
    Route::get('/aircrafts/{tailNumber}', [\App\Http\Controllers\Web\FleetController::class, 'show'])->name('aircrafts.show');
    Route::post('/aircrafts/{tailNumber}/configurations', [\App\Http\Controllers\Web\FleetController::class, 'configure'])->name('aircrafts.configurations');
    Route::post('/aircraft-models', [\App\Http\Controllers\Web\FleetController::class, 'storeModel'])->name('aircraft_models.store');
    Route::delete('/aircrafts/{tailNumber}', [AdminController::class, 'destroyAircraft'])->name('aircrafts.destroy');

    // 4. Quản lý Lịch bay & Chuyến bay khai thác (UC-05, UC-06, UC-07, UC-08, UC-09)
    Route::get('/flights', [\App\Http\Controllers\Web\FlightOperationsController::class, 'index'])->name('flights');
    Route::post('/flights', [\App\Http\Controllers\Web\FlightOperationsController::class, 'store'])->name('flights.store');
    Route::post('/flights/bulk/{action}', [\App\Http\Controllers\Web\FlightOperationsController::class, 'bulk'])->whereIn('action',['preview','commit'])->name('flights.bulk');
    Route::get('/flights/{id}', [\App\Http\Controllers\Web\FlightOperationsController::class, 'show'])->name('flights.show');
    Route::post('/flights/{id}/operations/{action}', [\App\Http\Controllers\Web\FlightOperationsController::class, 'act'])->whereIn('action',['status','events','proposals','checkin','close','milestone'])->name('flights.operation');
    Route::post('/flights/{id}/proposals/{proposal}/decision', [\App\Http\Controllers\Web\FlightOperationsController::class, 'decide'])->name('flights.decide');
    Route::post('/flights/{id}/outbox/{message}/retry', [\App\Http\Controllers\Web\FlightOperationsController::class, 'retryOutbox'])->name('flights.outbox.retry');
    Route::post('/events/{event}/retry', [\App\Http\Controllers\Web\FlightOperationsController::class, 'retry'])->name('events.retry');
    Route::get('/schedules', [\App\Http\Controllers\Web\PlanningController::class, 'schedules'])->name('schedules');
    Route::get('/schedules/{id}', [\App\Http\Controllers\Web\PlanningController::class, 'schedule'])->name('schedules.show');
    Route::post('/schedules', [\App\Http\Controllers\Web\PlanningController::class, 'saveSchedule'])->name('schedules.store');
    Route::put('/schedules/{id}', [\App\Http\Controllers\Web\PlanningController::class, 'saveSchedule'])->name('schedules.update');
    Route::post('/schedules/{id}/{action}', [\App\Http\Controllers\Web\PlanningController::class, 'action'])->whereIn('action', ['check', 'submit', 'approve', 'publish', 'revise', 'reject', 'cancel'])->name('schedules.action');
    Route::put('/flights/{id}/status', [AdminController::class, 'updateFlightStatus'])->name('flights.status.update');

    // 5. Quản lý Tổ bay (UC-15, UC-16, UC-17)
    Route::get('/crew', [\App\Http\Controllers\Web\CrewWorkflowController::class, 'index'])->name('crew');
    Route::post('/crew', [\App\Http\Controllers\Web\CrewWorkflowController::class, 'store'])->name('crew.store');
    Route::get('/crew/{id}', [\App\Http\Controllers\Web\CrewWorkflowController::class, 'show'])->name('crew.show');
    Route::post('/crew/{id}/availability', [\App\Http\Controllers\Web\CrewWorkflowController::class, 'calendar'])->name('crew.calendar');
    Route::post('/crew/{id}/availability/{window}/cancel', [\App\Http\Controllers\Web\CrewWorkflowController::class, 'cancelCalendar'])->name('crew.calendar.cancel');
    Route::get('/crew-plans', [\App\Http\Controllers\Web\CrewWorkflowController::class, 'plans'])->name('crew.plans');
    Route::get('/crew-plans/{flightId}', [\App\Http\Controllers\Web\CrewWorkflowController::class, 'plan'])->name('crew.plan');
    Route::post('/crew-plans/{flightId}/assign', [\App\Http\Controllers\Web\CrewWorkflowController::class, 'assign'])->name('crew.plan.assign');
    Route::post('/crew-plans/{flightId}/approve', [\App\Http\Controllers\Web\CrewWorkflowController::class, 'approve'])->name('crew.plan.approve');
    Route::post('/crew-assignments/{id}/{action}', [\App\Http\Controllers\Web\CrewWorkflowController::class, 'action'])->whereIn('action',['confirm','replace','remove'])->name('crew.action');
    Route::post('/flights/{flightId}/crew/assign', [AdminController::class, 'assignCrew'])->name('crew.assign');
    Route::delete('/crew/{id}', [AdminController::class, 'destroyCrew'])->name('crew.destroy');
    Route::delete('/crew-assignments/{assignmentId}', [AdminController::class, 'removeCrewAssignment'])->name('crew.assignments.destroy');
});
