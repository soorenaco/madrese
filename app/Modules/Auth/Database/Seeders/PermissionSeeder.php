<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // ─── Platform ───
            ['code' => 'platform.manage', 'resource' => 'platform', 'action' => 'manage', 'description' => 'مدیریت کل سامانه'],

            // ─── User ───
            ['code' => 'user.invite', 'resource' => 'user', 'action' => 'invite', 'description' => 'دعوت کاربر جدید به مدرسه'],
            ['code' => 'user.view', 'resource' => 'user', 'action' => 'view', 'description' => 'مشاهده اطلاعات کاربران مدرسه'],
            ['code' => 'user.suspend', 'resource' => 'user', 'action' => 'suspend', 'description' => 'تعلیق عضویت کاربر'],
            ['code' => 'user.manage', 'resource' => 'user', 'action' => 'manage', 'description' => 'مدیریت کامل کاربران'],

            // ─── Role ───
            ['code' => 'role.assign', 'resource' => 'role', 'action' => 'assign', 'description' => 'اعطا یا لغو نقش به عضویت'],
            ['code' => 'role.view', 'resource' => 'role', 'action' => 'view', 'description' => 'مشاهده نقش‌ها و مجوزها'],

            // ─── School ───
            ['code' => 'school.create', 'resource' => 'school', 'action' => 'create', 'description' => 'ایجاد مدرسه'],
            ['code' => 'school.view', 'resource' => 'school', 'action' => 'view', 'description' => 'مشاهده مدرسه'],
            ['code' => 'school.update', 'resource' => 'school', 'action' => 'update', 'description' => 'ویرایش مدرسه'],
            ['code' => 'school.delete', 'resource' => 'school', 'action' => 'delete', 'description' => 'حذف مدرسه'],
            ['code' => 'school.deputy.manage', 'resource' => 'school.deputy', 'action' => 'manage', 'description' => 'مدیریت معاونان مدرسه'],

            // ─── Class ───
            ['code' => 'class.manage', 'resource' => 'class', 'action' => 'manage', 'description' => 'ایجاد و مدیریت کلاس‌ها'],
            ['code' => 'class.view', 'resource' => 'class', 'action' => 'view', 'description' => 'مشاهده کلاس‌ها'],
            ['code' => 'class.roster.manage', 'resource' => 'class.roster', 'action' => 'manage', 'description' => 'مدیریت لیست دانش‌آموزان کلاس'],
            ['code' => 'class.schedule.manage', 'resource' => 'class.schedule', 'action' => 'manage', 'description' => 'مدیریت برنامه هفتگی کلاس'],
            ['code' => 'class.promote', 'resource' => 'class', 'action' => 'promote', 'description' => 'ارتقای کلاس به سال تحصیلی جدید'],

            // ─── Attendance ───
            ['code' => 'attendance.record', 'resource' => 'attendance', 'action' => 'record', 'description' => 'ثبت حضور و غیاب'],
            ['code' => 'attendance.view', 'resource' => 'attendance', 'action' => 'view', 'description' => 'مشاهده وضعیت حضور'],

            // ─── Assignment ───
            ['code' => 'assignment.manage', 'resource' => 'assignment', 'action' => 'manage', 'description' => 'ایجاد و مدیریت تکالیف'],
            ['code' => 'assignment.view', 'resource' => 'assignment', 'action' => 'view', 'description' => 'مشاهده تکالیف'],
            ['code' => 'assignment.submit', 'resource' => 'assignment', 'action' => 'submit', 'description' => 'تحویل تکلیف توسط دانش‌آموز'],

            // ─── Evaluation ───
            ['code' => 'evaluation.manage', 'resource' => 'evaluation', 'action' => 'manage', 'description' => 'ثبت و انتشار نتیجه'],
            ['code' => 'evaluation.view', 'resource' => 'evaluation', 'action' => 'view', 'description' => 'مشاهده نتایج منتشرشده'],


            // ─── Online Class ───
            ['code' => 'online_class.manage', 'resource' => 'online_class', 'action' => 'manage', 'description' => 'ثبت و مدیریت کلاس آنلاین'],
            ['code' => 'online_class.view', 'resource' => 'online_class', 'action' => 'view', 'description' => 'مشاهده کلاس‌های آنلاین'],
            ['code' => 'online_class.join', 'resource' => 'online_class', 'action' => 'join', 'description' => 'ورود به جلسهٔ آنلاین'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $permission['code']],
                [
                    'id' => Str::uuid(),
                    'resource' => $permission['resource'],
                    'action' => $permission['action'],
                    'description' => $permission['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}