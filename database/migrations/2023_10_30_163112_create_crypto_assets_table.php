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
        Schema::create('crypto_assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_gateway_id');
            $table->string('type',250);
            $table->string('chain',250);
            $table->string('coin',250);
            $table->text('credentials',3000)->nullable();
            $table->text('assets')->nullable();
            $table->timestamps();

            $table->foreign('payment_gateway_id')->references('id')->on('payment_gateways')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('crypto_assets');
    }
};
