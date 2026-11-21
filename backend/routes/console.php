<?php

use App\Models\User;
use App\Modules\Booking\BookingService;
use App\Modules\Finance\OutboxRelay;
use App\Modules\Shared\Access;
use App\Modules\Shared\Audit;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

Artisan::command('airline:relay', function () {
    $failed = app(OutboxRelay::class)->drain();
    $this->info('Outbox delivery errors: '.$failed);

    return $failed ? 1 : 0;
})->purpose('Deliver durable sandbox payment events once');
Artisan::command('airline:expire', function () {
    app(BookingService::class)->expire();
    $this->info('Expired holds released.');
});
Artisan::command('airline:work', function () {
    while (true) {
        try {
            app(BookingService::class)->expire();
            app(OutboxRelay::class)->drain();
        } catch (Throwable $e) {
            report($e);
        } sleep(2);
    }
});
Artisan::command('airline:user {email} {--role=admin} {--airport=HAN}', function () {
    $role = $this->option('role');
    if (! in_array($role, Access::ROLES)) {
        $this->error('Invalid role');

        return 1;
    }
    $email = $this->argument('email');
    if (! filter_var($email, FILTER_VALIDATE_EMAIL) || User::where('email', $email)->exists()) {
        $this->error('Invalid email or already exists; no existing account was changed.');

        return 1;
    }
    $password = $this->secret('Mật khẩu (ít nhất 10 ký tự)');
    if (strlen($password ?? '') < 10) {
        $this->error('Password too short');

        return 1;
    }
    $airport = in_array($role, Access::SCOPED) ? $this->option('airport') : null;
    if ($airport && ! DB::connection('ops')->table('airports')->where('code', $airport)->exists()) {
        $this->error('Invalid airport');

        return 1;
    }
    DB::transaction(function () use ($email, $password, $role, $airport) {
        $u = User::create(['name' => $email, 'email' => $email, 'password' => Hash::make($password)]);
        $u->forceFill(['role' => $role, 'airport_code' => $airport, 'email_verified_at' => now()])->save();
        Audit::record('user.provisioned', (string) $u->id, null, $role);
    });
    $this->info('Account created; password not logged.');
});
