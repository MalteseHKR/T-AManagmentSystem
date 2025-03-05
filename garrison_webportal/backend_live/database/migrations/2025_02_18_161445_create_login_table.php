<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLoginTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('login', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('user_login_pass');
            $table->unsignedBigInteger('user_id');
            $table->integer('login_attempts')->default(0);
            $table->timestamp('last_login_attempt')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('user_information')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('login');
    }
}