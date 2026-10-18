<?php

namespace App\Integrations\Identity;

use App\Models\User;

interface PasswordResetDelivery
{
    /** Return an opaque delivery reference. Never log or return the secret link in HTTP responses. */
    public function deliver(User $user, string $resetLink): string;
}
