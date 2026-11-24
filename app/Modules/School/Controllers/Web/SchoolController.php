<?php

namespace App\Modules\School\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Services\PermissionService;
use App\Modules\School\Models\School;
use App\Modules\School\Requests\SchoolRequest;
use App\Modules\School\Services\SchoolService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

use App\Modules\School\Services\SchoolManagerGate;


class SchoolController extends Controller
{
    public function __construct(
        protected PermissionService $permissions,
        protected SchoolService $schools,
        protected SchoolManagerGate $gate
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $schools = $this->schools->paginateFor($user, $request->string('q')->toString() ?: null);

        return Inertia::render('School/Index', [
            'schools' => $schools,
            'filters' => [
                'q' => $request->string('q')->toString(),
            ],
            'canCreate' => $this->permissions->isPlatformAdmin($user),
            'canDelete' => $this->permissions->isPlatformAdmin($user),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($this->permissions->isPlatformAdmin($request->user()), 403);

        return Inertia::render('School/Form', [
            'school' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->permissions->isPlatformAdmin($request->user()), 403);

        $data = $this->validated($request);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('schools/logos', 'public');
            $data['logo'] = '/storage/' . $path;
        }

        // جدا کردن فیلدهای مدیر از داده‌های مدرسه
        $managerMobile = $data['manager_mobile'] ?? null;
        $managerName = $data['manager_display_name'] ?? null;
        unset($data['manager_mobile'], $data['manager_display_name']);

        // حل‌وفصل کاربرِ مدیرِ اصلی پیش از ساخت مدرسه.
        // قانون کاری: هر کاربر فقط در یک مدرسه می‌تواند مدیر باشد. اگر این
        // بررسی بعد از ساخت مدرسه انجام می‌شد، در صورت خطا یک مدرسه‌ی یتیم
        // بدون مدیر در دیتابیس باقی می‌ماند؛ بنابراین باید قبل از ساخت بررسی شود.
        $managerUser = null;
        if ($managerMobile) {
            $normalized = app(\App\Modules\Class\Services\ClassRosterService::class)
                ->normalizeMobile($managerMobile);

            $managerUser = \App\Modules\Auth\Models\User::where('mobile_e164', $normalized)->first();

            if (! $managerUser) {
                $managerUser = \App\Modules\Auth\Models\User::create([
                    'mobile_e164'  => $normalized,
                    'display_name' => $managerName ?: ('مدیر ' . substr($normalized, -4)),
                    'status'       => 'invited',
                ]);
            }

            // تأیید قانون تک‌مدرسه‌ای مدیر (school_id هنوز برابر null است
            // چون مدرسه هنوز ساخته نشده؛ پس هر عضویت مدرسِ موجود، «در جای دیگر» است).
            $this->gate->assertUserNotManagerElsewhere($managerUser, '00000000-0000-0000-0000-000000000000');
        }

        // ساخت مدرسه
        $school = $this->schools->create($data);

        // ساخت مدیر اصلی
        if ($managerUser) {
            $membership = \App\Modules\Auth\Models\SchoolMembership::create([
                'id'                 => (string) \Illuminate\Support\Str::uuid(),
                'school_id'          => $school->id,
                'user_id'            => $managerUser->id,
                'status'             => $managerUser->status === 'invited' ? 'invited' : 'active',
                'joined_at'          => now(),
                'is_primary_manager' => true,   // ← مدیر اصلی
            ]);

            $role = \App\Modules\Auth\Models\Role::where('code', 'school_manager')->firstOrFail();

            \App\Modules\Auth\Models\MembershipRole::unguarded(function () use ($membership, $role) {
                \App\Modules\Auth\Models\MembershipRole::query()->updateOrCreate(
                    ['membership_id' => $membership->id, 'role_id' => $role->id],
                    ['granted_at' => now(), 'revoked_at' => null]
                );
            });

            app(\App\Modules\Auth\Services\PermissionService::class)->clearUserCache($managerUser);
        }

        return redirect()->route('panel.school')->with('success', 'مدرسه و مدیر اصلی با موفقیت ایجاد شد.');
    }
        
    public function edit(Request $request, School $school): Response
    {
        $user = $request->user();

        abort_unless($this->permissions->managesSchool($user, $school->id), 403);

        // مدیر اصلی و ادمین آزاد هستند
        if (! $this->gate->isPrimaryManager($user, $school->id)
            && ! $this->gate->allows($user, $school->id, 'can_edit_school')) {
            abort(403, 'اجازه ویرایش اطلاعات مدرسه را ندارید.');
        }

        return Inertia::render('School/Form', [
            'school' => $school,
        ]);
    }

    public function update(Request $request, School $school): RedirectResponse
    {
        $user = $request->user();

        abort_unless($this->permissions->managesSchool($user, $school->id), 403);

        if (! $this->gate->isPrimaryManager($user, $school->id)
            && ! $this->gate->allows($user, $school->id, 'can_edit_school')) {
            abort(403, 'اجازه ویرایش اطلاعات مدرسه را ندارید.');
        }

        $data = $this->validated($request, $school->id);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('schools/logos', 'public');
            $data['logo'] = '/storage/' . $path;
        }

        $this->schools->update($school, $data);

        return redirect()->route('panel.school')->with('success', 'مدرسه به‌روزرسانی شد.');
    }    

    public function destroy(Request $request, School $school): RedirectResponse
    {
        abort_unless($this->permissions->isPlatformAdmin($request->user()), 403);

        if (! $this->schools->delete($school)) {
            return redirect()->route('panel.school')->with('error', 'این مدرسه کلاس یا عضو دارد و قابل حذف نیست.');
        }

        return redirect()->route('panel.school')->with('success', 'مدرسه حذف شد.');
    }

    protected function validated(Request $request, ?string $ignoreId = null): array
    {
        return $request->validate(SchoolRequest::rulesFor($ignoreId), SchoolRequest::messagesList());
    }
}