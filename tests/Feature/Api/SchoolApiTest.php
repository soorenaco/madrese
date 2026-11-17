<?php

namespace Tests\Feature\Api;

use App\Modules\Auth\Database\Seeders\RoleSeeder;
use App\Modules\Auth\Models\MembershipRole;
use App\Modules\Auth\Models\Role;
use App\Modules\School\Models\School;
use App\Modules\Auth\Models\SchoolMembership;
use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * برش ۱ API: مدارس
 *
 * GET    /api/v1/schools
 * POST   /api/v1/schools
 * GET    /api/v1/schools/{school}
 * PUT    /api/v1/schools/{school}
 * DELETE /api/v1/schools/{school}
 *
 * قرارداد پاسخ:
 *  - موفق:   { "data": {...} }  یا لیست: { "data": [...], "links": {...}, "meta": {...} }
 *  - 401 بدون توکن | 403 نقش/مدرسه نامجاز | 404 مدرسه ناموجود
 *  - 409 حذف مدرسه‌ای که کلاس یا عضو دارد
 *  - 422 خطای اعتبارسنجی: { "message": "...", "errors": { field: [...] } }
 */
class SchoolApiTest extends TestCase
{
    use RefreshDatabase;

    private const SHAPE = ['id', 'name', 'code', 'city', 'address', 'status', 'classes_count', 'memberships_count'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function makeSchool(array $attrs = []): School
    {
        return School::create(array_merge([
            'name'   => 'مدرسه '.Str::random(6),
            'code'   => 'C'.Str::random(8),
            'city'   => 'کرمان',
            'status' => 'active',
        ], $attrs));
    }

    /** کاربر فعال با یک نقش در یک مدرسه مشخص */
    private function userWithRole(string $roleCode, School $school): User
    {
        $user = User::create([
            'mobile_e164'  => '+98912'.random_int(1000000, 9999999),
            'display_name' => 'کاربر '.$roleCode,
            'status'       => 'active',
        ]);

        $membership = SchoolMembership::unguarded(fn () => SchoolMembership::create([
            'id'        => (string) Str::uuid(),
            'school_id' => $school->id,
            'user_id'   => $user->id,
            'status'    => 'active',
        ]));

        MembershipRole::unguarded(fn () => MembershipRole::create([
            'membership_id' => $membership->id,
            'role_id'       => Role::where('code', $roleCode)->firstOrFail()->id,
        ]));

        return $user;
    }

    private function actAs(string $roleCode, ?School $school = null): User
    {
        $user = $this->userWithRole($roleCode, $school ?? $this->makeSchool());
        Sanctum::actingAs($user);

        return $user;
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'name'    => 'دبیرستان نمونه',
            'code'    => 'NEW-001',
            'city'    => 'کرمان',
            'address' => 'خیابان آزادی',
            'status'  => 'active',
        ], $override);
    }

    // ───────────── احراز هویت ─────────────

    public function test_all_routes_require_token(): void
    {
        $school = $this->makeSchool();

        $this->getJson('/api/v1/schools')->assertUnauthorized();
        $this->postJson('/api/v1/schools', $this->payload())->assertUnauthorized();
        $this->getJson("/api/v1/schools/{$school->id}")->assertUnauthorized();
        $this->putJson("/api/v1/schools/{$school->id}", $this->payload())->assertUnauthorized();
        $this->deleteJson("/api/v1/schools/{$school->id}")->assertUnauthorized();
    }

    public function test_non_manager_roles_are_forbidden_everywhere(): void
    {
        $school = $this->makeSchool();

        foreach (['teacher', 'student', 'parent', 'counselor'] as $role) {
            $this->actAs($role, $school);

            $this->getJson('/api/v1/schools')->assertForbidden();
            $this->getJson("/api/v1/schools/{$school->id}")->assertForbidden();
            $this->putJson("/api/v1/schools/{$school->id}", $this->payload())->assertForbidden();
            $this->postJson('/api/v1/schools', $this->payload())->assertForbidden();
            $this->deleteJson("/api/v1/schools/{$school->id}")->assertForbidden();
        }
    }

    // ───────────── GET /schools ─────────────

    public function test_admin_lists_all_schools_with_counts(): void
    {
        $this->makeSchool();
        $this->makeSchool();
        $this->actAs('admin'); // مدرسه سوم

        $this->getJson('/api/v1/schools')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => ['*' => self::SHAPE],
                'links',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_manager_lists_only_managed_schools(): void
    {
        $mine = $this->makeSchool(['name' => 'مدرسه من']);
        $this->makeSchool(['name' => 'مدرسه دیگری']);
        $this->actAs('school_manager', $mine);

        $response = $this->getJson('/api/v1/schools')->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $mine->id);
    }

    public function test_list_search_by_name_code_and_city(): void
    {
        $this->actAs('admin');
        $this->makeSchool(['name' => 'البرز', 'code' => 'ZZ1', 'city' => 'تهران']);

        $this->getJson('/api/v1/schools?q=البرز')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/schools?q=zz1')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/schools?q=تهران')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/schools?q=نامعلوم')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_list_is_paginated_15_per_page(): void
    {
        $this->actAs('admin');
        for ($i = 0; $i < 16; $i++) {
            $this->makeSchool();
        }

        $this->getJson('/api/v1/schools')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 17)
            ->assertJsonPath('meta.per_page', 15);

        $this->getJson('/api/v1/schools?page=2')->assertOk()->assertJsonCount(2, 'data');
    }

    // ───────────── POST /schools ─────────────

    public function test_admin_creates_school(): void
    {
        $this->actAs('admin');

        $this->postJson('/api/v1/schools', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'دبیرستان نمونه')
            ->assertJsonPath('data.code', 'NEW-001')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonStructure(['data' => self::SHAPE]);

        $this->assertDatabaseHas('schools', ['code' => 'NEW-001']);
    }

    public function test_manager_cannot_create_school(): void
    {
        $this->actAs('school_manager');

        $this->postJson('/api/v1/schools', $this->payload())->assertForbidden();
        $this->assertDatabaseMissing('schools', ['code' => 'NEW-001']);
    }

    public function test_create_validation_errors(): void
    {
        $this->actAs('admin');
        $this->makeSchool(['code' => 'DUP']);

        $this->postJson('/api/v1/schools', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'status']);

        $this->postJson('/api/v1/schools', $this->payload(['status' => 'deleted']))
            ->assertUnprocessable()->assertJsonValidationErrors(['status']);

        $this->postJson('/api/v1/schools', $this->payload(['code' => 'DUP']))
            ->assertUnprocessable()->assertJsonValidationErrors(['code']);

        $this->postJson('/api/v1/schools', $this->payload(['name' => str_repeat('a', 201)]))
            ->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    // ───────────── GET /schools/{school} ─────────────

    public function test_admin_shows_any_school(): void
    {
        $school = $this->makeSchool();
        $this->actAs('admin');

        $this->getJson("/api/v1/schools/{$school->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $school->id)
            ->assertJsonStructure(['data' => self::SHAPE]);
    }

    public function test_manager_shows_own_school_but_not_others(): void
    {
        $mine = $this->makeSchool();
        $other = $this->makeSchool();
        $this->actAs('school_manager', $mine);

        $this->getJson("/api/v1/schools/{$mine->id}")->assertOk();
        $this->getJson("/api/v1/schools/{$other->id}")->assertForbidden();
    }

    public function test_show_unknown_school_is_404(): void
    {
        $this->actAs('admin');

        $this->getJson('/api/v1/schools/'.Str::uuid())->assertNotFound();
    }

    // ───────────── PUT /schools/{school} ─────────────

    public function test_admin_updates_school(): void
    {
        $school = $this->makeSchool();
        $this->actAs('admin');

        $this->putJson("/api/v1/schools/{$school->id}", $this->payload(['name' => 'نام جدید', 'status' => 'suspended']))
            ->assertOk()
            ->assertJsonPath('data.name', 'نام جدید')
            ->assertJsonPath('data.status', 'suspended');

        $this->assertDatabaseHas('schools', ['id' => $school->id, 'name' => 'نام جدید', 'status' => 'suspended']);
    }

    public function test_update_may_keep_own_code_but_not_take_anothers(): void
    {
        $school = $this->makeSchool(['code' => 'KEEP']);
        $this->makeSchool(['code' => 'TAKEN']);
        $this->actAs('admin');

        $this->putJson("/api/v1/schools/{$school->id}", $this->payload(['code' => 'KEEP']))->assertOk();
        $this->putJson("/api/v1/schools/{$school->id}", $this->payload(['code' => 'TAKEN']))
            ->assertUnprocessable()->assertJsonValidationErrors(['code']);
    }

    public function test_manager_updates_own_school_only(): void
    {
        $mine = $this->makeSchool();
        $other = $this->makeSchool();
        $this->actAs('school_manager', $mine);

        $this->putJson("/api/v1/schools/{$mine->id}", $this->payload(['code' => 'MINE']))->assertOk();
        $this->putJson("/api/v1/schools/{$other->id}", $this->payload(['code' => 'OTHER']))->assertForbidden();
        $this->assertDatabaseMissing('schools', ['code' => 'OTHER']);
    }

    public function test_update_validation_errors(): void
    {
        $school = $this->makeSchool();
        $this->actAs('admin');

        $this->putJson("/api/v1/schools/{$school->id}", [])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'status']);
    }

    // ───────────── DELETE /schools/{school} ─────────────

    public function test_admin_deletes_empty_school(): void
    {
        $school = $this->makeSchool();
        $this->actAs('admin');

        $this->deleteJson("/api/v1/schools/{$school->id}")->assertNoContent();
        $this->assertDatabaseMissing('schools', ['id' => $school->id]);
    }

    public function test_delete_school_with_members_is_409(): void
    {
        $school = $this->makeSchool();
        $this->userWithRole('student', $school); // عضو دارد
        $this->actAs('admin');

        $this->deleteJson("/api/v1/schools/{$school->id}")
            ->assertStatus(409)
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('schools', ['id' => $school->id]);
    }

    public function test_manager_cannot_delete_even_own_school(): void
    {
        $school = $this->makeSchool();
        $this->actAs('school_manager', $school);

        $this->deleteJson("/api/v1/schools/{$school->id}")->assertForbidden();
        $this->assertDatabaseHas('schools', ['id' => $school->id]);
    }

    public function test_delete_unknown_school_is_404(): void
    {
        $this->actAs('admin');

        $this->deleteJson('/api/v1/schools/'.Str::uuid())->assertNotFound();
    }
}
