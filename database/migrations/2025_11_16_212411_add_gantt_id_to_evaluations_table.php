<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up()
{
    Schema::table('evaluations', function (Blueprint $table) {
        $table->unsignedBigInteger('gantt_id')->nullable()->after('idea_id');

        $table->foreign('gantt_id')
              ->references('id')
              ->on('gantt_charts')
              ->onDelete('cascade');
    });
}

public function down()
{
    Schema::table('evaluations', function (Blueprint $table) {
        $table->dropForeign(['gantt_id']);
        $table->dropColumn('gantt_id');
    });
}

};
