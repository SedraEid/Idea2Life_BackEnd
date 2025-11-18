<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->unsignedBigInteger('gantt_chart_id')->nullable()->after('idea_id');
            $table->foreign('gantt_chart_id')->references('id')->on('gantt_charts')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropForeign(['gantt_chart_id']);
            $table->dropColumn('gantt_chart_id');
        });
    }
};
