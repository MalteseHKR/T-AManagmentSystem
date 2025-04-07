<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // Create users first
        User::factory(10)->create(); // Reduced to 10 users for testing

        // Then run attendance seeder which will create employees and attendance records
        $this->call([
            AttendanceSeeder::class,
        ]);
    }
}
