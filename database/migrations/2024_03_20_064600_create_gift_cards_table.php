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
        Schema::create('gift_cards', function (Blueprint $table) {
            $table->id();
            $table->string('user_type',50)->comment("should be USER, AGENT, Merchant");
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('user_wallet_id');
            $table->unsignedBigInteger('recipient_currency_id');
            $table->string('uuid');
            $table->string('trx_id')->comment("internal TRX ID");
            $table->string('api_trx_id',250)->comment("TRX ID From API");
            $table->decimal('card_amount', 28, 8)->default(0);
            $table->decimal('card_total_amount', 28, 8)->default(0);
            $table->string('card_currency', 250);
            $table->string('card_image', 255)->nullable();
            $table->string('card_name', 255)->nullable();
            $table->string('user_wallet_currency',30);
            $table->decimal('exchange_rate',28,8)->default(1);
            $table->string('default_currency',30);
            $table->decimal('percent_charge',28,8)->default(0)->comment("charge percentage in default currency");
            $table->decimal('fixed_charge',28,8)->default(0)->comment("charge fixed in default currency");
            $table->decimal('percent_charge_calc',28,8)->default(0);
            $table->decimal('fixed_charge_calc',28,8)->default(0);
            $table->decimal('total_charge',28,8)->default(0);
            $table->integer('qty');
            $table->decimal('unit_amount',28,8)->default(0)->comment("Unit amount in user wallet currency");
            $table->decimal('conversion_amount',28,8)->default(0);
            $table->decimal('total_payable',28,8)->default(0);
            $table->string('api_currency', 30)->comment('API owner account currency');
            $table->decimal('api_discount', 28,8)->default(0);
            $table->decimal('api_fee', 28,8)->default(0);
            $table->decimal('api_sms_fee', 28,8)->default(0);
            $table->decimal('api_total_fee', 28,8)->default(0);
            $table->boolean('pre_order')->default(false);
            $table->string('recipient_email',250)->nullable();
            $table->string('recipient_country_iso2',250)->nullable();
            $table->string('recipient_phone',250)->nullable();
            $table->text('codes')->nullable()->comment("Redeem Codes");
            $table->tinyInteger("status")->default(1);
            $table->text('details', 5000)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gift_cards');
    }
};
