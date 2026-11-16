<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Concerns\SharesPlatformShell;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Rbac;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/** Platform staff directory — Gambia SchoolOS operators (school_id null). */
class PlatformUserController extends Controller
{
    use SharesPlatformShell;

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('platform.users.manage'), 403);

        $users = User::query()
            ->whereNull('school_id')
            ->with('roles:id,name')
            ->latest('id')
            ->paginate(30)
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'roles' => $u->getRoleNames()->values()->all(),
                'created_at' => $u->created_at?->toDateString(),
            ]);

        $roles = array_values(array_filter(
            Rbac::platformRoles(),
            fn (string $role) => $role !== 'super_admin' || $request->user()->hasRole('super_admin')
        ));

        return Inertia::render('Platform/Users/Index', array_merge($this->platformShellProps(), [
            'users' => $users,
            'roles' => $roles,
            'storeUrl' => route('platform.users.store'),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('platform.users.manage'), 403);

        $allowed = array_values(array_filter(
            Rbac::platformRoles(),
            fn (string $role) => $role !== 'super_admin' || $request->user()->hasRole('super_admin')
        ));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::in($allowed)],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'school_id' => null,
            'email_verified_at' => now(),
        ]);
        $user->assignRole($data['role']);

        return redirect()->route('platform.users.index')->with('success', 'ok.platformStaff.created');
    }
}
