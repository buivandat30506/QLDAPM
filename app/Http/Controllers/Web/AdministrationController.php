<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\Administration\AdministrationService;
use App\Services\Administration\AuditQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdministrationController extends Controller
{
    public function users(Request $request)
    {
        $request->validate(['search' => 'nullable|string|max:100']);
        $users = User::with('roles')->when($request->search, fn ($q) => $q->where('username', 'like', '%'.$request->search.'%'))->orderBy('username')->paginate(25)->withQueryString();

        return view('administration.users', ['users' => $users, 'roles' => Role::orderBy('role_name')->get()]);
    }

    public function edit(User $user)
    {
        return view('administration.user-edit', ['user' => $user, 'roles' => Role::orderBy('role_name')->get(),
            'scopes' => DB::table('user_data_scopes')->where('user_id', $user->id)->get(),
            'permissions' => DB::table('permissions')->orderBy('code')->get(),
            'delegations' => DB::table('delegations')->where('grantee_id', $user->id)->orderByDesc('id')->get(),
        ]);
    }

    public function save(Request $request, AdministrationService $service, ?string $id = null)
    {
        $data = $request->all();
        $data['assignments'] ??= [];
        $user = $service->saveUser($data, $id);

        return redirect()->route('administration.users.edit', $user)->with('status', 'Đã lưu tài khoản, vai trò và phạm vi dữ liệu.');
    }

    public function delegate(Request $request, AdministrationService $service)
    {
        $service->delegate($request->all());

        return back()->with('status', 'Đã lưu ủy quyền có thời hạn.');
    }

    public function revoke(int $id, AdministrationService $service)
    {
        $service->revoke($id);

        return back()->with('status', 'Đã thu hồi ủy quyền.');
    }

    public function audit(Request $request, AuditQuery $query)
    {
        return view('administration.audit', ['entries' => $query->page($request->query())->withQueryString()]);
    }

    public function export(Request $request, AuditQuery $query)
    {
        return $query->export($request->query());
    }
}
