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
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('mobile_code');
            $table->string('currency_name');
            $table->string('currency_code');
            $table->string('currency_symbol');
            $table->decimal('rate', 28, 8)->default(0);
            $table->boolean('status')->default(1)->comment('1:Active,2:Inactive');
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
        Schema::dropIfExists('exchange_rates');
    }
};
