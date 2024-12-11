<?php

use App\Constants\PaymentGatewayConst;
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
        Schema::create('crypto_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('internal_trx_type',150)->nullable()->comment("Internal Transaction Type. EX: Add Money, Money Out");
            $table->unsignedBigInteger('internal_trx_ref_id')->nullable()->comment("Internal Transaction Reference ID. EX: Transaction Table ID");
            $table->string('transaction_type',250)->nullable();
            $table->string('sender_address',250)->nullable();
            $table->string('receiver_address', 250)->nullable();
            $table->string('amount',250)->nullable()->comment("Can be positive and negative");
            $table->string('asset',250)->nullable();
            $table->string('block_number',250)->nullable();
            $table->string('txn_hash',250)->nullable()->comment("Transaction ID/Transaction Hash");
            $table->string('chain',250)->nullable();
            $table->text('callback_response',1000)->nullable();
            $table->string('status')->default(PaymentGatewayConst::NOT_USED);
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
        Schema::dropIfExists('crypto_transactions');
    }
};
