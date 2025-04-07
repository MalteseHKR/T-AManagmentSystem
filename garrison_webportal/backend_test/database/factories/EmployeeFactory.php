<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition()
    {
        // Get existing user IDs
        $userIds = User::pluck('id')->toArray();

        return [
            'user_id' => $this->faker->unique()->randomElement($userIds),
            'first_name' => $this->faker->firstName,
            'surname' => $this->faker->lastName,
            'job_role' => $this->faker->jobTitle,
            'phone_number' => $this->faker->phoneNumber,
            'email' => $this->faker->unique()->safeEmail,
            'date_of_birth' => $this->faker->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
            'start_date' => $this->faker->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'is_active' => $this->faker->boolean(90),
            'department' => $this->faker->randomElement(['HR', 'IT', 'Finance', 'Marketing', 'Operations']),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}