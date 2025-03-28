<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    public function run()
    {
        // First, get all existing users
        $users = User::all();

        // Create employees for existing users
        $users->each(function ($user) {
            Employee::factory()->create([
                'user_id' => $user->id,
                'email' => $user->email // ensure email matches user email
            ]);
        });

        // Now create attendance records for each employee
        Employee::all()->each(function ($employee) {
            Attendance::factory()
                ->count(20)
                ->create([
                    'employee_id' => $employee->id
                ]);
        });
    }
}
