<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up()
{
    Schema::table('evaluations', function (Blueprint $table) {
        $table->unsignedBigInteger('funding_id')->nullable()->after('business_plan_id');

        $table->foreign('funding_id')
              ->references('id')
              ->on('fundings')
              ->onDelete('set null');
    });
}

public function down()
{
    Schema::table('evaluations', function (Blueprint $table) {
        $table->dropForeign(['funding_id']);
        $table->dropColumn('funding_id');
    });
}

};
