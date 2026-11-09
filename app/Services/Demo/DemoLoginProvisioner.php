<?php

namespace App\Services\Demo;

use App\Models\School;
use App\Models\Staff;
use App\Models\User;
use App\Support\DemoLoginCatalog;
use App\Support\Rbac;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent: move demo accounts onto the live mail host and reset their password to `password`.
 * Never touches a live-registered address such as smartworldgambia@gmail.com.
 */
class DemoLoginProvisioner
{
    /** @return array{platform: int, school: int, relocated: int} */
    public function sync(): array
    {
        if (Schema::hasTable('roles')) {
            Rbac::sync();
        }

        $relocated = 0;
        $platform = $this->ensurePlatform($relocated);
        $school = 0;

        if (Schema::hasTable('schools')) {
            foreach (School::query()->where('status', 'active')->orderBy('id')->get() as $row) {
                $school += count($this->ensureSchool($row, $relocated));
            }
        }

        return ['platform' => $platform, 'school' => $school, 'relocated' => $relocated];
    }

    public function ensurePlatform(int &$relocated = 0): int
    {
        $count = 0;
        foreach (DemoLoginCatalog::platformDirectory() as $local => $meta) {
            $target = DemoLoginCatalog::platformEmail($local);
            $relocated += $this->relocateAliases($target);
            $this->upsertUser($target, null, $meta['name'], $meta['role']);
            $count++;
        }

        return $count;
    }

    /**
     * @return array<string, User>
     */
    public function ensureSchool(School $school, int &$relocated = 0): array
    {
        $users = [];
        foreach (DemoLoginCatalog::schoolDirectory($school->slug) as $key => $meta) {
            $relocated += $this->relocateAliases($meta['email']);
            $users[$key] = $this->upsertUser(
                $meta['email'],
                $school->id,
                $meta['name'],
                $meta['role'],
                $meta['phone'] ?? null,
            );
        }

        return $users;
    }

    public function relocateLoginEmail(string $from, string $to): bool
    {
        $from = strtolower(trim($from));
        $to = strtolower(trim($to));
        if ($from === '' || $to === '' || $from === $to) {
            return false;
        }

        $moved = false;
        if (! User::query()->where('email', $to)->exists()) {
            $moved = User::query()->where('email', $from)->update(['email' => $to]) > 0;
        }

        if (Schema::hasTable('staff') && ! Staff::withoutGlobalScopes()->where('email', $to)->exists()) {
            Staff::withoutGlobalScopes()->where('email', $from)->update(['email' => $to]);
        }

        return $moved;
    }

    private function relocateAliases(string $target): int
    {
        $moved = 0;
        foreach (DemoLoginCatalog::aliasesFor($target) as $from) {
            if ($this->relocateLoginEmail($from, $target)) {
                $moved++;
            }
        }

        return $moved;
    }

    private function upsertUser(string $email, ?int $schoolId, string $name, string $role, ?string $phone = null): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'school_id' => $schoolId,
            'name' => $user->exists ? $user->name : $name,
            'phone' => $user->phone ?: $phone,
            'password' => DemoLoginCatalog::PASSWORD,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();
        $user->syncRoles([$role]);

        return $user;
    }
}
