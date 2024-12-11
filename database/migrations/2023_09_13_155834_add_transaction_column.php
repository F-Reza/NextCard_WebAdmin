<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddTransactionColumn extends Migration
{

    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('callback_ref')->nullable();
        });
    }
    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('callback_ref');
        });
    }
}
