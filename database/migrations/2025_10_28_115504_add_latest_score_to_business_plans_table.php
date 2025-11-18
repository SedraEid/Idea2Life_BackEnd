<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_plans', function (Blueprint $table) {
            $table->unsignedTinyInteger('latest_score')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('business_plans', function (Blueprint $table) {
            $table->dropColumn('latest_score');
        });
    }
};
