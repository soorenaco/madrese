<?php

namespace Database\Seeders;

use App\Modules\Auth\Database\Seeders\RoleSeeder;
use App\Modules\Auth\Database\Seeders\PermissionSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
        ]);
    }
}