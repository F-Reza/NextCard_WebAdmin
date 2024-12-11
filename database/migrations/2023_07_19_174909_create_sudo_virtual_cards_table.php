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
        Schema::create('sudo_virtual_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("user_id");
            $table->string('card_id')->nullable();
            $table->string('name')->nullable();
            $table->string('business_id')->nullable();
            $table->text('customer')->nullable();
            $table->text('account')->nullable();
            $table->text('fundingSource')->nullable();
            $table->string('type')->nullable();
            $table->string('brand')->nullable();
            $table->string('currency')->nullable();
            $table->string('amount',28,8)->default(0.00000000);
            $table->string('charge',28,8)->default(0.00000000);
            $table->string('maskedPan')->nullable();
            $table->string('last4')->nullable();
            $table->string('expiryMonth')->nullable();
            $table->string('expiryYear')->nullable();
            $table->boolean('status')->default(true);
            $table->boolean('isDeleted')->default(true);
            $table->boolean('is_default')->default(false);
            $table->text('billingAddress')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sudo_virtual_cards');
    }
};
