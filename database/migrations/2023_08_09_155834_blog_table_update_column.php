<?php

use App\Constants\PaymentGatewayConst;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class BlogTableUpdateColumn extends Migration
{
    public function up()
    {
        Schema::table('blogs', function (Blueprint $table) {
            $table->text('name')->change();
        });
    }

    public function down()
    {
        Schema::table('blogs', function (Blueprint $table) {
            $table->string('name')->change();
        });
    }
}
