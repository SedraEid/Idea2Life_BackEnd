<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->foreignId('improvement_plan_id')
                ->nullable()
                ->constrained('improvement_plans')
                ->onDelete('set null')
                ->after('roadmap_id');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropForeign(['improvement_plan_id']);
            $table->dropColumn('improvement_plan_id');
        });
    }
};
