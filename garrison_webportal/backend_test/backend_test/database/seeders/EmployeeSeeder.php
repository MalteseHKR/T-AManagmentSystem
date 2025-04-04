<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Employee;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Create sample users
        $user1 = User::create([
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'password' => bcrypt('password'),
        ]);

        $user2 = User::create([
            'name' => 'Jane Smith',
            'email' => 'jane.smith@example.com',
            'password' => bcrypt('password'),
        ]);

        // Create sample employees
        Employee::create([
            'user_id' => $user1->id,
            'first_name' => 'John',
            'surname' => 'Doe',
            'job_role' => 'Software Engineer',
            'phone_number' => '1234567890',
            'email' => 'john.doe@example.com',
            'date_of_birth' => '1990-01-01',
            'start_date' => '2020-01-01',
            'is_active' => true,
            'department' => 'IT',
        ]);

        Employee::create([
            'user_id' => $user2->id,
            'first_name' => 'Jane',
            'surname' => 'Smith',
            'job_role' => 'Project Manager',
            'phone_number' => '0987654321',
            'email' => 'jane.smith@example.com',
            'date_of_birth' => '1985-05-15',
            'start_date' => '2018-03-01',
            'is_active' => true,
            'department' => 'Management',
        ]);
    }
}
