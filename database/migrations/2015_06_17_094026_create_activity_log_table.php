<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateActivityLogTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'activity_logs',
            function (Blueprint $table) {
                $table->increments('id');
                $table->text('message');
                $table->json('message_params')->nullable();
                $table->integer('contract_id')->unsigned()->nullable();
                $table->foreign('contract_id')
                      ->references('id')->on('contracts')
                      ->onDelete('cascade');
                $table->integer('user_id');
                $table->foreign('user_id')
                      ->references('id')->on('users')
                      ->onDelete('cascade');
                $table->timestamps();
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('activity_logs');
    }

}
