<?php

namespace App\Modules\OnlineClass\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\PermissionService;
use App\Modules\Class\Models\ClassEnrollment;
use App\Modules\Class\Models\SchoolClass;
use App\Modules\OnlineClass\Models\OnlineClass;
use App\Modules\School\Services\SchoolManagerGate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * منطق تجاری کلاس آنلاین: ثبت، شروع، ورود، پایان، حذف
 * و تعیین دسترسیِ مدیر/معاون/معلم/دانش‌آموز.
 */
class OnlineClassService
{
    public function __construct(
        protected PermissionService $permissions,
        protected SchoolManagerGate $gate,
        protected ProviderFactory $providers,
    ) {}

    /**
     * آیا کاربر می‌تواند کلاس آنلاین این school_class را مدیریت کند؟
     * ادمین، معلم کلاس، مدیر اصلی، یا معاون با can_manage_online_classes.
     */
    public function canManage(User $user, SchoolClass $class): bool
    {
        if ($this->permissions->isPlatformAdmin($user)) {
            return true;
        }

        if ($class->teacher_user_id === $user->id) {
            return true;
        }

        if (! $this->permissions->managesSchool($user, $class->school_id)) {
            return false;
        }

        if ($this->gate->isPrimaryManager($user, $class->school_id)) {
            return true;
        }

        return $this->gate->allows($user, $class->school_id, 'can_manage_online_classes');
    }

    /**
     * آیا کاربر نقش مدیر (moderator) در جلسه دارد؟
     * ادمین، معلم کلاس، یا مدیر/معاون مدرسه.
     */
    public function isModerator(User $user, SchoolClass $class): bool
    {
        if ($this->permissions->isPlatformAdmin($user)) {
            return true;
        }

        if ($class->teacher_user_id === $user->id) {
            return true;
        }

        return $this->permissions->managesSchool($user, $class->school_id);
    }

    /**
     * آیا کاربر می‌تواند به کلاس آنلاین بپیوندد؟
     * مدیر/معلم یا دانش‌آموزِ عضوِ فعالِ کلاس.
     */
    public function canJoin(User $user, SchoolClass $class): bool
    {
        if ($this->isModerator($user, $class)) {
            return true;
        }

        return ClassEnrollment::query()
            ->where('school_class_id', $class->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }

    public function register(SchoolClass $class, array $data, User $actor): OnlineClass
    {
        if (OnlineClass::query()->where('school_class_id', $class->id)->exists()) {
            throw ValidationException::withMessages([
                'school_class_id' => 'برای این کلاس قبلاً کلاس آنلاین ثبت شده است.',
            ]);
        }

        $provider = (string) $data['provider'];

        if (! $this->providers->isConfigured($provider)) {
            throw ValidationException::withMessages([
                'provider' => 'ارائه‌دهندهٔ انتخاب‌شده توسط ادمین پیکربندی نشده است.',
            ]);
        }

        return OnlineClass::create([
            'id'              => (string) Str::uuid(),
            'school_class_id' => $class->id,
            'provider'        => $provider,
            'title'           => $data['title'] ?? null,
            'record'          => (bool) ($data['record'] ?? false),
            'status'          => 'idle',
            'created_by'      => $actor->id,
        ]);
    }

    public function update(OnlineClass $online, array $data): OnlineClass
    {
        $updates = [];

        if (array_key_exists('title', $data)) {
            $updates['title'] = $data['title'];
        }

        if (array_key_exists('record', $data)) {
            $updates['record'] = (bool) $data['record'];
        }

        // ارائه‌دهنده فقط تا قبل از ساخت اتاق قابل تغییر است.
        if (array_key_exists('provider', $data) && $data['provider'] !== $online->provider) {
            if ($online->provider_room_id) {
                throw ValidationException::withMessages([
                    'provider' => 'پس از شروع جلسه، ارائه‌دهنده قابل تغییر نیست.',
                ]);
            }

            if (! $this->providers->isConfigured((string) $data['provider'])) {
                throw ValidationException::withMessages([
                    'provider' => 'ارائه‌دهندهٔ انتخاب‌شده توسط ادمین پیکربندی نشده است.',
                ]);
            }

            $updates['provider'] = (string) $data['provider'];
        }

        if ($updates) {
            $online->update($updates);
        }

        return $online->fresh();
    }

    public function start(OnlineClass $online): OnlineClass
    {
        if ($online->status === 'live') {
            return $online;
        }

        $provider = $this->providers->for($online);

        // اتاق در صورت نیاز ساخته می‌شود (BBB: idempotent | Skyroom: پایدار).
        if (! $online->provider_room_id) {
            $online->provider_room_id = $provider->createRoom($online);
        }

        $online->update([
            'status'     => 'live',
            'started_at' => now(),
            'ended_at'   => null,
        ]);

        return $online->fresh();
    }

    /**
     * ساخت لینک ورود برای کاربر. مدیر/معلم با کلیک، جلسه را شروع می‌کنند؛
     * دانش‌آموز فقط در صورتی وارد می‌شود که جلسه live باشد.
     */
    public function join(OnlineClass $online, User $user): string
    {
        $class = $online->schoolClass;
        $moderator = $this->isModerator($user, $class);

        if ($moderator) {
            if (! $online->provider_room_id || $online->status !== 'live') {
                $online = $this->start($online);
            }
        } else {
            if ($online->status !== 'live') {
                throw ValidationException::withMessages([
                    'online' => 'جلسه هنوز شروع نشده است.',
                ]);
            }
        }

        return $this->providers->for($online)->getJoinUrl($online, $user, $moderator);
    }

    public function end(OnlineClass $online): OnlineClass
    {
        if ($online->status === 'ended') {
            return $online;
        }

        if ($online->provider_room_id) {
            try {
                $this->providers->for($online)->endRoom($online);
            } catch (RuntimeException) {
                // خطای سمت ارائه‌دهنده نباید جلوی ثبت وضعیت را بگیرد.
            }
        }

        $online->update([
            'status'   => 'ended',
            'ended_at' => now(),
        ]);

        return $online->fresh();
    }

    public function unregister(OnlineClass $online): void
    {
        if ($online->status === 'live') {
            $this->end($online);
        }

        $online->delete();
    }
}
