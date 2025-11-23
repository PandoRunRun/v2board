<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsDroppedToOttRenewalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('v2_ott_renewal', function (Blueprint $table) {
            $table->boolean('is_dropped')->default(0)->after('is_paid')->comment('User dropped this account for next year');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('v2_ott_renewal', function (Blueprint $table) {
            $table->dropColumn('is_dropped');
        });
    }
}

