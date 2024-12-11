<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateVirtualCardApiTableColumn extends Migration
{
    public function up()
    {
        Schema::table('virtual_card_apis', function (Blueprint $table) {
           $table->integer('card_limit')->default(3);
        });
    }

    public function down()
    {
        Schema::table('virtual_card_apis', function (Blueprint $table) {
            $table->dropColumn('card_limit');
        });
    }
}
