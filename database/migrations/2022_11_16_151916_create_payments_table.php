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
        Schema::create('payments', function (Blueprint $table) {
            $table->increments('id');
            $table->foreignId('payer_id')->unsignedInteger()->index();
            $table->foreignId('proguide_id')->unsignedInteger()->index();
            $table->foreignId('plan_id')->unsignedInteger()->index();
            $table->string('amount_paid')->nullable();
            $table->string('payer_email')->nullable();
            $table->string('payer_full_name')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payments');
    }
};
