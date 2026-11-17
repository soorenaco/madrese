<?php

namespace App\Modules\School\Services;

use App\Modules\School\Models\School;
use App\Modules\Auth\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Modules\Auth\Services\PermissionService;
/**
 * منطق مشترک مدارس (پنل وب و API).
 */
class SchoolService
{
    public function __construct(
        protected PermissionService $permissions
    ) {}

    /**
     * لیست مدارس در دسترس کاربر (ادمین: همه | مدیر: مدارس خودش).
     */
    public function paginateFor(User $user, ?string $term = null, int $perPage = 15): LengthAwarePaginator
    {
        $managedIds = $this->permissions->managedSchoolIds($user);

        return School::query()
            ->withCount(['classes', 'memberships'])
            ->when($managedIds !== null, fn ($q) => $q->whereIn('id', $managedIds ?: ['00000000-0000-0000-0000-000000000000']))
            ->when($term, function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'ilike', "%{$term}%")
                        ->orWhere('code', 'ilike', "%{$term}%")
                        ->orWhere('city', 'ilike', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): School
    {
        return School::create($data);
    }

    public function update(School $school, array $data): School
    {
        $school->update($data);

        return $school;
    }

    public function hasDependents(School $school): bool
    {
        return $school->classes()->exists() || $school->memberships()->exists();
    }

    /**
     * حذف مدرسه؛ اگر کلاس یا عضو دارد false برمی‌گرداند و حذف نمی‌کند.
     */
    public function delete(School $school): bool
    {
        if ($this->hasDependents($school)) {
            return false;
        }

        $school->delete();

        return true;
    }
}
