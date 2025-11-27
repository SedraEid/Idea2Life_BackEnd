<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fundings', function (Blueprint $table) {
            $table->dropForeign(['report_id']); 
            $table->dropColumn('report_id');

            $table->boolean('is_approved')->nullable()->after('approved_amount');
        });
    }

    public function down(): void
    {
        Schema::table('fundings', function (Blueprint $table) {
            $table->foreignId('report_id')->nullable()->constrained('reports')->onDelete('set null')->after('justification');
            $table->dropColumn('is_approved');
        });
    }
};
