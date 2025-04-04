<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition()
    {
        $punchIn = $this->faker->dateTimeBetween('-1 month', 'now');
        $punchOut = clone $punchIn;
        $punchOut->modify('+' . rand(1, 9) . ' hours');
        
        // Format duration as time (HH:MM:SS)
        $duration = gmdate('H:i:s', $punchOut->getTimestamp() - $punchIn->getTimestamp());

        return [
            'employee_id' => Employee::factory(),
            'punch_in' => $punchIn,
            'punch_out' => $punchOut,
            'duration' => $duration,
            'device_id' => $this->faker->uuid,
            'punch_type' => $this->faker->randomElement(['in', 'out']),
            'photo_url' => $this->faker->imageUrl(640, 480, 'people'),
            'punch_date' => $punchIn->format('Y-m-d'),
            'punch_time' => $punchIn->format('H:i:s'),
            'latitude' => $this->faker->latitude,
            'longitude' => $this->faker->longitude,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}