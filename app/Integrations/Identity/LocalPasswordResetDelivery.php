<?php

namespace App\Integrations\Identity;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LocalPasswordResetDelivery implements PasswordResetDelivery
{
    public function deliver(User $user, string $resetLink): string
    {
        abort_unless(app()->environment(['local', 'testing']), 503, 'Kênh khôi phục mật khẩu chưa được cấu hình.');
        $reference = (string) Str::uuid();
        $written = Storage::disk('local')->put('reset-mail/'.$reference.'.json', json_encode([
            'mode' => 'local-simulation', 'recipient' => $user->email, 'reset_link' => $resetLink,
            'created_at' => now('UTC')->toIso8601String(),
        ], JSON_THROW_ON_ERROR));
        if (! $written) { throw new \RuntimeException('Private reset mailbox is unavailable.'); }

        return $reference;
    }
}
