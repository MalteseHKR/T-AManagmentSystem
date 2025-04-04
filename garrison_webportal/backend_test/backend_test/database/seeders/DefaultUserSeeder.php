<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\UserInformation;

class DefaultUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = User::create([
            'name' => 'Default User',
            'email' => 'default@example.com',
            'password' => Hash::make('password'),
            'role' => 'HR', // Added role field
        ]);

        UserInformation::create([
            'user_id' => $user->id,
            'user_name' => 'Default',
            'user_surname' => 'User',
            'user_title' => 'HR',
            'user_phone' => '1234567890',
            'user_email' => 'default@example.com',
            'user_dob' => '1990-01-01',
            'user_job_start' => '2020-01-01',
            'user_job_end' => null,
            'user_active' => true,
            'user_department' => 'HR',
        ]);
    }
}
