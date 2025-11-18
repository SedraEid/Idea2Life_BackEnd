<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  public function up()
{
    Schema::table('fundings', function (Blueprint $table) {
        $table->foreignId('gantt_id')->nullable()->constrained('gantt_charts')->onDelete('set null');
        $table->foreignId('task_id')->nullable()->constrained('tasks')->onDelete('set null');
    });
}

public function down()
{
    Schema::table('fundings', function (Blueprint $table) {
        $table->dropConstrainedForeignId('gantt_id');
        $table->dropConstrainedForeignId('task_id');
    });
}

};
