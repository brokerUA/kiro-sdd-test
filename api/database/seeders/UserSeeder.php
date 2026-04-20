<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('users')->upsert(
            [
                [
                    'id'            => Str::uuid()->toString(),
                    'email'         => 'test@test.test',
                    'password_hash' => password_hash('password', PASSWORD_BCRYPT),
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ],
            ],
            uniqueBy: ['email'],
            update:   ['password_hash', 'updated_at'],
        );

        $this->command->info('Test user seeded: test@test.test / password');
    }
}
