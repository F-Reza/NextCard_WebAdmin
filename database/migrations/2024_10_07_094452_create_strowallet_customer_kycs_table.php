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
        Schema::create('strowallet_customer_kycs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("user_id");
            $table->string('id_image',255)->nullable();
            $table->string('face_image',255)->nullable();
            $table->timestamps();

            $table->foreign("user_id")->references("id")->on("users")->onDelete("cascade")->onUpdate("cascade");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('strowallet_customer_kycs');
    }
};
