<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUserTableUpdateColumnForStrowallet extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('strowallet_customer')->nullable();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('strowallet_customer');
        });
    }
}
