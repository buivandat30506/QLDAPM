<?php

use App\Http\Controllers\Api\Core\AircraftController;
use App\Http\Controllers\Api\Core\AirportController;
use App\Http\Controllers\Api\Core\CrewController;
use App\Http\Controllers\Api\Core\FlightController;
use App\Http\Controllers\Api\Core\RouteController;
use App\Http\Controllers\Api\Passenger\BaggageController;
use App\Http\Controllers\Api\Passenger\BookingController;
use App\Http\Controllers\Api\Passenger\CheckInController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Smart Airline Management (Phần mềm Quản lý Hàng không Thông minh)
| Base prefix: /api
|--------------------------------------------------------------------------
*/

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['auth:sanctum', \App\Http\Middleware\ActiveUser::class]);

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [\App\Http\Controllers\AuthController::class, 'token'])->middleware('throttle:10,1');
    Route::post('auth/logout', [\App\Http\Controllers\AuthController::class, 'revokeToken'])->middleware('auth:sanctum');

    // =========================================================================
    // 1. MODULE 1: QUẢN LÝ CỐT LÕI (FLIGHT OPERATIONS CORE): /api/v1/core/...
    // =========================================================================
    Route::prefix('core')->middleware(['auth:sanctum', \App\Http\Middleware\CoreAccess::class])->group(function () {
        require __DIR__.'/core-workflows.php';

        // Cụm 1: Sân bay & Tài nguyên sân bay (UC-01, UC-02)
        Route::prefix('airports')->group(function () {
            Route::get('/', [AirportController::class, 'index'])->name('api.v1.core.airports.index');
            Route::post('/', [AirportController::class, 'store'])->name('api.v1.core.airports.store');
            Route::get('/{iataCode}', [AirportController::class, 'show'])->name('api.v1.core.airports.show');
            Route::put('/{iataCode}', [AirportController::class, 'update'])->name('api.v1.core.airports.update');
            Route::delete('/{iataCode}', [AirportController::class, 'destroy'])->name('api.v1.core.airports.destroy');

            // Tài nguyên sân bay (Terminals, Gates, Runways, Carousels)
            Route::get('/{iataCode}/resources', [AirportController::class, 'getResources'])->name('api.v1.core.airports.resources.index');
            Route::post('/{iataCode}/resources', [AirportController::class, 'storeResource'])->name('api.v1.core.airports.resources.store');
            Route::delete('/resources/{resourceId}', [AirportController::class, 'destroyResource'])->name('api.v1.core.airports.resources.destroy');
        });

        // Cụm 2: Đường bay & Tuyến bay (UC-04)
        Route::prefix('routes')->group(function () {
            Route::get('/', [RouteController::class, 'index'])->name('api.v1.core.routes.index');
            Route::post('/', [RouteController::class, 'store'])->name('api.v1.core.routes.store');
            Route::get('/{id}', [RouteController::class, 'show'])->name('api.v1.core.routes.show');
            Route::put('/{id}', [RouteController::class, 'update'])->name('api.v1.core.routes.update');
            Route::delete('/{id}', [RouteController::class, 'destroy'])->name('api.v1.core.routes.destroy');
        });

        // Cụm 3: Đội tàu bay & Dòng tàu bay (UC-10, UC-11)
        Route::get('/aircraft-models', [AircraftController::class, 'getModels'])->name('api.v1.core.aircraft_models.index');
        Route::post('/aircraft-models', [AircraftController::class, 'storeModel'])->name('api.v1.core.aircraft_models.store');

        Route::prefix('aircrafts')->group(function () {
            Route::get('/maintenance-alerts', [AircraftController::class, 'maintenanceAlerts'])->name('api.v1.core.aircrafts.alerts');
            Route::get('/', [AircraftController::class, 'index'])->name('api.v1.core.aircrafts.index');
            Route::post('/', [AircraftController::class, 'store'])->name('api.v1.core.aircrafts.store');
            Route::get('/{tailNumber}', [AircraftController::class, 'show'])->name('api.v1.core.aircrafts.show');
            Route::get('/{tailNumber}/configurations', [AircraftController::class, 'configurations']);
            Route::post('/{tailNumber}/configurations', [AircraftController::class, 'configure']);
            Route::get('/{tailNumber}/usage', [AircraftController::class, 'usage']);
            Route::post('/{tailNumber}/usage', [AircraftController::class, 'receiveUsage']);
            Route::put('/{tailNumber}', [AircraftController::class, 'update'])->name('api.v1.core.aircrafts.update');
            Route::delete('/{tailNumber}', [AircraftController::class, 'destroy'])->name('api.v1.core.aircrafts.destroy');
        });

        // Cụm 4: Lịch bay & Chuyến bay khai thác (UC-05, UC-06, UC-07, UC-08, UC-09)
        Route::prefix('schedules')->group(function () {
            Route::get('/', [FlightController::class, 'getSchedules'])->name('api.v1.core.schedules.index');
            Route::post('/', [FlightController::class, 'storeSchedule'])->name('api.v1.core.schedules.store');
        });

        Route::prefix('flights')->group(function () {
            Route::get('/', [FlightController::class, 'index'])->name('api.v1.core.flights.index');
            Route::post('/', [FlightController::class, 'store'])->name('api.v1.core.flights.store');
            Route::post('/check-conflict', [FlightController::class, 'checkConflict'])->name('api.v1.core.flights.check_conflict');
            Route::get('/{id}', [FlightController::class, 'show'])->name('api.v1.core.flights.show');
            Route::put('/{id}/status', [FlightController::class, 'updateStatus'])->name('api.v1.core.flights.update_status');

            // Phân công tổ bay cho chuyến bay (UC-16, UC-17)
            Route::get('/{flightId}/crew', [CrewController::class, 'getFlightAssignments'])->name('api.v1.core.flights.crew.index');
            Route::post('/{flightId}/crew/assign', [CrewController::class, 'assignCrew'])->name('api.v1.core.flights.crew.assign');
            Route::delete('/crew-assignments/{assignmentId}', [CrewController::class, 'removeAssignment'])->name('api.v1.core.flights.crew.remove');
        });

        // Cụm 5: Hồ sơ nhân sự tổ bay (UC-15)
        Route::prefix('crew-members')->group(function () {
            Route::get('/{id}/availability', [CrewController::class, 'calendar']);
            Route::post('/{id}/availability', [CrewController::class, 'addCalendar']);
            Route::post('/{id}/availability/{window}/cancel', [CrewController::class, 'cancelCalendar']);
            Route::get('/', [CrewController::class, 'index'])->name('api.v1.core.crew.index');
            Route::post('/', [CrewController::class, 'store'])->name('api.v1.core.crew.store');
            Route::get('/{id}', [CrewController::class, 'show'])->name('api.v1.core.crew.show');
            Route::put('/{id}', [CrewController::class, 'update'])->name('api.v1.core.crew.update');
            Route::delete('/{id}', [CrewController::class, 'destroy'])->name('api.v1.core.crew.destroy');
        });
        Route::get('/flights/{flightId}/crew/suggestions', [CrewController::class, 'suggestions']);
    });

    // =========================================================================
    // 2. PASSENGER & SERVICES MODULE: /api/v1/passenger/...
    // =========================================================================
    Route::prefix('passenger')->middleware(['auth:sanctum', \App\Http\Middleware\ActiveUser::class])->group(function () {
        // Bookings & Reservations (BR-011)
        Route::prefix('bookings')->group(function () {
            Route::post('/', [BookingController::class, 'store'])->name('api.v1.passenger.bookings.store');
            Route::get('/{pnr}', [BookingController::class, 'show'])->name('api.v1.passenger.bookings.show');
            Route::post('/confirm', [BookingController::class, 'confirm'])->name('api.v1.passenger.bookings.confirm');
        });

        // Check-in & Boarding Pass
        Route::prefix('checkin')->group(function () {
            Route::post('/', [CheckInController::class, 'checkIn'])->name('api.v1.passenger.checkin.process');
            Route::post('/board', [CheckInController::class, 'boardPassenger'])->name('api.v1.passenger.checkin.board');
        });

        // Baggage & Tracking
        Route::prefix('baggage')->group(function () {
            Route::post('/', [BaggageController::class, 'store'])->name('api.v1.passenger.baggage.store');
            Route::get('/{tagCode}', [BaggageController::class, 'show'])->name('api.v1.passenger.baggage.show');
            Route::post('/events', [BaggageController::class, 'recordEvent'])->name('api.v1.passenger.baggage.events');
        });
    });
});
