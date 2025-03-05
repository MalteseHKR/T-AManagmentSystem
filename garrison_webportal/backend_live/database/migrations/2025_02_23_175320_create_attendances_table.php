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
            $table->id('attendance_id'); // Primary Key
            $table->unsignedBigInteger('employee_id'); // Foreign Key to employees table
            $table->dateTime('punch_in'); // DateTime of punch in
            $table->dateTime('punch_out')->nullable(); // DateTime of punch out
            $table->time('duration')->nullable(); // Duration of the attendance
            $table->string('device_id'); // Device ID used for punching in
            $table->enum('punch_type', ['In', 'Out']); // Punch type (In or Out)
            $table->string('photo_url')->nullable(); // Photo URL
            $table->date('punch_date'); // Date when the user punched in
            $table->time('punch_time'); // Time when the user punched in
            $table->decimal('latitude', 10, 7)->nullable(); // Latitude for geolocation
            $table->decimal('longitude', 10, 7)->nullable(); // Longitude for geolocation
            $table->timestamps(); // Created at and Updated at

            // Foreign key constraint
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
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
