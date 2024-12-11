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
        Schema::table('strowallet_virtual_cards', function (Blueprint $table) {
            $table->string('card_name')->nullable()->change();
            $table->string('card_number')->nullable()->change();
            $table->string('last4')->nullable()->change();
            $table->string('cvv')->nullable()->change();
            $table->string('expiry')->nullable()->change();
            $table->string('customer_email')->nullable()->change();
            $table->string('balance')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
