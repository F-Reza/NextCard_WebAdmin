<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateVirtualCardForDefaultSystemTableColumn extends Migration
{
    public function up()
    {
        Schema::table('virtual_cards', function (Blueprint $table) {
            $table->boolean('is_default')->default(false);
        });
        Schema::table('strowallet_virtual_cards', function (Blueprint $table) {
            $table->boolean('is_default')->default(false);
        });
        Schema::table('stripe_virtual_cards', function (Blueprint $table) {
            $table->boolean('is_default')->default(false);
        });
    }

    public function down()
    {
        Schema::table('virtual_cards', function (Blueprint $table) {
            $table->boolean('is_default')->default(false);
        });
        Schema::table('strowallet_virtual_cards', function (Blueprint $table) {
            $table->boolean('is_default')->default(false);
        });
        Schema::table('stripe_virtual_cards', function (Blueprint $table) {
            $table->boolean('is_default')->default(false);
        });
    }
}
