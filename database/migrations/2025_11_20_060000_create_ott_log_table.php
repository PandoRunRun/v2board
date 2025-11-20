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
        Schema::create('v2_ott_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->string('type')->index(); // 'capture', 'bind', 'error'
            $table->boolean('status')->default(true); // true = success, false = fail/ignore
            $table->text('message')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('v2_ott_account')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('v2_ott_log');
    }
};
