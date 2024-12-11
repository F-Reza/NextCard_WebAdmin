<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUserTableUpdateColumn extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('sudo_customer')->nullable();
            $table->text('sudo_account')->nullable();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('sudo_customer');
            $table->dropColumn('sudo_account');
        });
    }
}
