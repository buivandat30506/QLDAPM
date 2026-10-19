<?php

namespace App\Providers;

use App\Models\Aircraft;
use App\Models\AircraftModel;
use App\Models\Airport;
use App\Models\AirportResource;
use App\Models\CrewMember;
use App\Models\FlightCrewAssignment;
use App\Models\FlightSchedule;
use App\Models\OperationalFlight;
use App\Models\Route;
use App\Services\Core\AuditService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Shared\Security\AccessControl;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Integrations\Operations\FlightChangeDelivery::class, \App\Integrations\Operations\LocalFlightChangeDelivery::class);
        $this->app->bind(\App\Integrations\Identity\PasswordResetDelivery::class, \App\Integrations\Identity\LocalPasswordResetDelivery::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Pagination\Paginator::useBootstrapFive();
        foreach (array_keys(config('access.permissions')) as $permission) {
            Gate::define($permission, fn (\App\Models\User $user, array $context = []) => app(AccessControl::class)->allows($user, $permission, $context));
        }
        foreach ([Airport::class, AirportResource::class, Route::class,
            Aircraft::class, AircraftModel::class, FlightSchedule::class,
            OperationalFlight::class, CrewMember::class, FlightCrewAssignment::class] as $model) {
            $model::saved(fn ($record) => AuditService::observe($record));
            $model::deleted(fn ($record) => AuditService::record($record->getTable(), $record->getKey(), 'DELETED', $record->toArray()));
        }
    }
}
