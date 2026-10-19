<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Services\Core\AuditService;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate(['username' => 'required|string', 'password' => 'required|string']);
        if (! Auth::attempt($credentials + ['status' => 'ACTIVE'])) {
            AuditService::record('authentication', 'anonymous', 'LOGIN_FAILED', outcome: 'DENIED');
            throw ValidationException::withMessages(['username' => ['Sai tài khoản/mật khẩu hoặc tài khoản không hoạt động.']]);
        }
        $request->session()->regenerate();
        AuditService::record('users', Auth::id(), 'LOGIN_SUCCEEDED');

        return redirect()->intended($this->landing($request));
    }

    public function token(Request $request)
    {
        $values = $request->validate(['username' => 'required|string', 'password' => 'required|string']);
        $user = User::where('username', $values['username'])->where('status', 'ACTIVE')->first();
        if (! $user || ! Hash::check($values['password'], $user->password)) {
            AuditService::record('authentication', 'anonymous', 'LOGIN_FAILED', outcome: 'DENIED');
            throw ValidationException::withMessages(['username' => ['Thông tin đăng nhập không hợp lệ.']]);
        }

        AuditService::record('users', $user->id, 'TOKEN_ISSUED', actorId: $user->id);
        return response()->json(['success' => true, 'message' => 'Đăng nhập thành công.', 'data' => [
            'token' => $user->createToken('api', ['*'], now('UTC')->addHours(8))->plainTextToken,
            'roles' => $user->roles()->pluck('role_code'), 'expires_in' => 28800,
        ], 'errors' => null]);
    }

    public function logout(Request $request)
    {
        AuditService::record('users', $request->user()->id, 'LOGOUT');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    public function revokeToken(Request $request)
    {
        AuditService::record('users', $request->user()->id, 'TOKEN_REVOKED');
        $token = $request->user()->currentAccessToken();
        abort_unless($token instanceof \Laravel\Sanctum\PersonalAccessToken, 409, 'Đăng xuất phiên web bằng biểu mẫu đăng xuất.');
        $token->delete();

        return response()->json(['success' => true, 'message' => 'Đã thu hồi token hiện tại.']);
    }

    public function changePassword(Request $request)
    {
        $values = $request->validate(['current_password' => ['required', 'current_password:web'], 'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->numbers()]]);
        $user = $request->user();
        $user->forceFill(['password' => $values['password'], 'remember_token' => Str::random(60)])->save();
        $user->tokens()->delete();
        AuditService::record('users', $user->id, 'PASSWORD_CHANGED');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Đã đổi mật khẩu và thu hồi token. Vui lòng đăng nhập lại.');
    }

    public function requestReset(Request $request)
    {
        $request->validate(['email' => 'required|email|max:100']);
        Password::sendResetLink($request->only('email'), function (User $user, string $token) {
            if ($user->status !== 'ACTIVE') { return; }
            $link = route('password.reset', ['token' => $token, 'email' => $user->email]);
            app(\App\Integrations\Identity\PasswordResetDelivery::class)->deliver($user, $link);
        });

        return back()->with('status', 'Nếu tài khoản phù hợp tồn tại, hướng dẫn đã được chuyển tới kênh khôi phục. Local: hộp thư mô phỏng riêng tư.');
    }

    public function resetPassword(Request $request)
    {
        $values = $request->validate(['email' => 'required|email', 'token' => 'required|string', 'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->numbers()]]);
        $status = Password::reset($values, function (User $user, string $password) {
            abort_unless($user->status === 'ACTIVE', 403);
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
            \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $user->id)->delete();
            AuditService::record('users', $user->id, 'PASSWORD_RESET');
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => ['Liên kết không hợp lệ hoặc đã hết hạn.']]);
        }

        return redirect()->route('login')->with('status', 'Đã đặt lại mật khẩu. Vui lòng đăng nhập.');
    }

    private function landing(Request $request): string
    {
        if ($request->user()->can('users.manage')) { return '/administration/users'; }
        if ($request->user()->can('operations.read')) { return '/admin/workflows'; }

        return '/account';
    }
}
