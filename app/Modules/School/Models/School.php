<?php

namespace App\Modules\School\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Modules\Class\Models\SchoolClass;
use App\Modules\Auth\Models\SchoolMembership;

class School extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'code',
        'city',
        'address',
        'status',
        'phone',
        'email',
        'website',
        'description',
        'logo',
    ];
    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'school_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(SchoolMembership::class, 'school_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

        protected function giveRole(User $user, string $roleCode): Role
    {
        $role = Role::where('code', $roleCode)->firstOrFail();

        // ← School واقعی بساز
        $school = School::create([
            'name'   => 'مدرسه تست ' . Str::random(6),
            'status' => 'active',
        ]);

        $membership = SchoolMembership::unguarded(fn () => SchoolMembership::create([
            'id'        => (string) Str::uuid(),
            'school_id' => $school->id,   // ← واقعی
            'user_id'   => $user->id,
            'status'    => 'active',
        ]));

        MembershipRole::unguarded(fn () => MembershipRole::create([
            'membership_id' => $membership->id,
            'role_id'       => $role->id,
        ]));

        app(PermissionService::class)->clearUserCache($user);

        return $role;
    }

}