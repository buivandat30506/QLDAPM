<?php

use App\Http\Controllers\Web\PassengerPortalController as Portal;
use App\Http\Middleware\ActiveUser;
use Illuminate\Support\Facades\Route;

Route::prefix('portal')->name('passenger.')->middleware(['auth', ActiveUser::class])->group(function () {
    Route::get('profiles', [Portal::class, 'profiles'])->name('profiles');
    Route::post('profiles', [Portal::class, 'saveProfile'])->name('profiles.store');
    Route::get('profiles/{id}', [Portal::class, 'profile'])->name('profiles.show');
    Route::put('profiles/{id}', [Portal::class, 'saveProfile'])->name('profiles.update');
    Route::post('profiles/{id}/consents', [Portal::class, 'consent'])->name('profiles.consent');
    Route::get('assistance', [Portal::class, 'assistance'])->name('assistance');
    Route::post('assistance', [Portal::class, 'createAssistance'])->name('assistance.store');
    Route::get('assistance/{id}', [Portal::class, 'assistanceDetail'])->name('assistance.show');
    Route::post('assistance/{id}', [Portal::class, 'actAssistance'])->name('assistance.act');
});
