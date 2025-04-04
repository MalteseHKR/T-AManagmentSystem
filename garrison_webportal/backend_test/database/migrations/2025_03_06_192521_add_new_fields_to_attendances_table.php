<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Add new columns if they don't exist
            if (!Schema::hasColumn('attendances', 'date')) {
                $table->date('date')->nullable();
            }
            if (!Schema::hasColumn('attendances', 'clock_in')) {
                $table->dateTime('clock_in')->nullable();
            }
            if (!Schema::hasColumn('attendances', 'clock_out')) {
                $table->dateTime('clock_out')->nullable();
            }
            if (!Schema::hasColumn('attendances', 'status')) {
                $table->string('status')->nullable();
            }
            if (!Schema::hasColumn('attendances', 'notes')) {
                $table->text('notes')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'date',
                'clock_in',
                'clock_out',
                'status',
                'notes'
            ]);
        });
    }
};
