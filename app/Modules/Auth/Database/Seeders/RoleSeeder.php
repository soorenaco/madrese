<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['code' => 'admin', 'title' => 'ادمین سیستم', 'scope' => 'platform', 'is_system' => true],
            ['code' => 'school_manager', 'title' => 'مدیر مدرسه', 'scope' => 'school', 'is_system' => true],
            ['code' => 'teacher', 'title' => 'معلم', 'scope' => 'class', 'is_system' => true],
            ['code' => 'student', 'title' => 'دانش‌آموز', 'scope' => 'self', 'is_system' => true],
            ['code' => 'parent', 'title' => 'والدین', 'scope' => 'linked_child', 'is_system' => true],
            ['code' => 'counselor', 'title' => 'مشاور', 'scope' => 'assigned_students', 'is_system' => true],
            ['code' => 'bazaar', 'title' => 'بازار', 'scope' => 'assigned_students', 'is_system' => true],
            ['code' => 'driver', 'title' => 'راننده', 'scope' => 'self', 'is_system' => true],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['code' => $role['code']],
                [
                    'id' => Str::uuid(),
                    'title' => $role['title'],
                    'scope' => $role['scope'],
                    'is_system' => $role['is_system'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}