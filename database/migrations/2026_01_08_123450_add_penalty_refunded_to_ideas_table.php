<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ideas', function (Blueprint $table) {
            $table->boolean('penalty_refunded')
                  ->default(false)
                  ->after('roadmap_stage')
                  ->comment('هل تم إرجاع المبلغ الجزائي من قبل اللجنة؟');
        });
    }

    public function down(): void
    {
        Schema::table('ideas', function (Blueprint $table) {
            $table->dropColumn('penalty_refunded');
        });
    }
};
