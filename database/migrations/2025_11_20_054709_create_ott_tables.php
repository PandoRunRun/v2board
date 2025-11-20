<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOttTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('v2_user', function (Blueprint $table) {
            $table->tinyInteger('is_ott')->default(0)->after('is_admin');
        });

        Schema::create('v2_ott_account', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Account Name');
            $table->string('type')->comment('Account Type');
            $table->string('username')->comment('Login Username/Email');
            $table->string('password')->comment('Login Password');
            $table->boolean('has_otp')->default(0)->comment('Requires OTP');
            $table->boolean('is_shared_credentials')->default(1)->comment('Shared Credentials');
            $table->string('sender_filter')->nullable()->comment('Email Sender Filter');
            $table->string('recipient_filter')->nullable()->comment('Email Recipient Filter');
            $table->string('subject_regex')->nullable()->comment('OTP Extraction Regex');
            $table->integer('otp_validity_minutes')->default(10)->comment('OTP Validity in Minutes');
            $table->string('ignore_regex')->nullable()->comment('Regex to Ignore Email');
            $table->decimal('price_monthly', 10, 2)->nullable()->comment('Monthly Price');
            $table->decimal('price_yearly', 10, 2)->nullable()->comment('Yearly Price');
            $table->integer('shared_seats')->default(1)->comment('Total Shared Seats');
            $table->decimal('next_price_yearly', 10, 2)->nullable()->comment('Next Cycle Yearly Price');
            $table->integer('next_shared_seats')->nullable()->comment('Next Cycle Shared Seats');
            $table->boolean('is_active')->default(1)->comment('Is Active');
            $table->integer('group_id')->nullable()->comment('User Group ID');
            $table->timestamps();
        });

        Schema::create('v2_ott_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedBigInteger('account_id');
            $table->string('sub_account_id')->nullable()->comment('Profile Name or ID');
            $table->string('sub_account_pin')->nullable()->comment('Profile PIN');
            $table->integer('expired_at');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('v2_user')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('v2_ott_account')->onDelete('cascade');
        });

        Schema::create('v2_ott_renewal', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedBigInteger('account_id');
            $table->integer('target_year')->comment('Target Renewal Year');
            $table->decimal('price', 10, 2)->comment('Renewal Price Snapshot');
            $table->boolean('is_paid')->default(0);
            $table->string('sub_account_id')->nullable();
            $table->string('sub_account_pin')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('v2_user')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('v2_ott_account')->onDelete('cascade');
        });

        Schema::create('v2_ott_message', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('subject')->nullable();
            $table->text('content')->nullable();
            $table->integer('received_at');
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
        Schema::table('v2_user', function (Blueprint $table) {
            $table->dropColumn('is_ott');
        });

        Schema::dropIfExists('v2_ott_message');
        Schema::dropIfExists('v2_ott_user');
        Schema::dropIfExists('v2_ott_account');
    }
}
