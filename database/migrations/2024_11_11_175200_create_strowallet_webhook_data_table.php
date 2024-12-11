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
        Schema::create('strowallet_webhook_data', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("parent_id")->nullable();
            $table->unsignedBigInteger("user_id")->nullable();
            $table->string("transaction_id")->nullable();
            $table->string("event")->nullable();
            $table->string("cardId")->nullable();
            $table->string("card_currency")->nullable();
            $table->text("data")->nullable();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign("parent_id")->references("id")->on("strowallet_virtual_cards")->onDelete("cascade")->onUpdate("cascade");
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
        Schema::dropIfExists('strowallet_webhook_data');
    }
};
