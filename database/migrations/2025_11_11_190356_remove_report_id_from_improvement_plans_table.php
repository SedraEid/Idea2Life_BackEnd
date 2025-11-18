<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('improvement_plans', function (Blueprint $table) {
            $table->dropForeign(['report_id']); 
            $table->dropColumn('report_id');   
        });
    }

    public function down(): void
    {
        Schema::table('improvement_plans', function (Blueprint $table) {
            $table->foreignId('report_id')->constrained('reports')->onDelete('cascade');
        });
    }
};

