<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsNewToOttRenewalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('v2_ott_renewal', function (Blueprint $table) {
            $table->boolean('is_new')->default(0)->after('is_dropped')->comment('New user added for this renewal year');
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
            $table->dropColumn('is_new');
        });
    }
}

