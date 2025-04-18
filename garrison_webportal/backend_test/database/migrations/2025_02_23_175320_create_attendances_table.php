<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id(); // Primary Key
            $table->foreignId('employee_id')->constrained()->onDelete('cascade'); // Foreign Key to employees table
            $table->dateTime('punch_in'); // DateTime of punch in
            $table->dateTime('punch_out')->nullable(); // DateTime of punch out
            $table->time('duration')->nullable(); // Duration of the attendance
            $table->string('device_id'); // Device ID used for punching in
            $table->enum('punch_type', ['in', 'out']); // Punch type (In or Out)
            $table->string('photo_url')->nullable(); // Photo URL
            $table->date('punch_date'); // Date when the user punched in
            $table->time('punch_time'); // Time when the user punched in
            $table->decimal('latitude', 10, 8)->nullable(); // Latitude for geolocation
            $table->decimal('longitude', 11, 8)->nullable(); // Longitude for geolocation
            $table->timestamps(); // Created at and Updated at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
