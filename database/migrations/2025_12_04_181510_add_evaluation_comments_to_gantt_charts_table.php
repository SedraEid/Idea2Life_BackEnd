<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up()
{
    Schema::table('gantt_charts', function (Blueprint $table) {
        $table->text('evaluation_comments')->nullable()->after('evaluation_score');
    });
}

public function down()
{
    Schema::table('gantt_charts', function (Blueprint $table) {
        $table->dropColumn('evaluation_comments');
    });
}

};
