<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Attendance;
use App\Models\Employee;
use Carbon\Carbon;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $employees = Employee::all();

        foreach ($employees as $employee) {
            for ($i = 0; $i < 10; $i++) {
                $punchIn = Carbon::now()->subDays(rand(1, 30))->setTime(rand(8, 10), rand(0, 59));
                $punchOut = (clone $punchIn)->addHours(rand(7, 9))->addMinutes(rand(0, 59));

                Attendance::create([
                    'employee_id' => $employee->id,
                    'punch_in' => $punchIn,
                    'punch_out' => $punchOut,
                    'duration' => $punchOut->diff($punchIn)->format('%H:%I:%S'),
                    'device_id' => 'Device_' . rand(1, 5),
                    'punch_type' => 'In',
                    'photo_url' => null,
                    'punch_date' => $punchIn->toDateString(),
                    'punch_time' => $punchIn->toTimeString(),
                    'latitude' => rand(-90, 90) + rand(0, 1000000) / 1000000,
                    'longitude' => rand(-180, 180) + rand(0, 1000000) / 1000000,
                ]);
            }
        }
    }
}
