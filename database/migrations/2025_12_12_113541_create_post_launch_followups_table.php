<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_launch_followups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('launch_project_id');
            // من قام بعمل المتابعة (اللجنة – صاحب المشروع – النظام)
            $table->unsignedBigInteger('recorded_by')->nullable();

            // تحديات خلال فترة التشغيل
            $table->boolean('challenge_detected')->default(false);
            $table->enum('challenge_level', ['low', 'medium', 'high'])->nullable();  
            $table->text('challenge_description')->nullable();
            $table->text('action_taken')->nullable();

            // مؤشرات أداء KPIs
            $table->integer('kpi_active_users')->nullable();
            $table->integer('kpi_sales')->nullable();
            $table->integer('kpi_user_growth')->nullable();
            $table->integer('kpi_engagement')->nullable();

            // تقييم اللجنة
        $table->enum('overall_status', [
    'in_review',
    'stable',
    'needs_support',
    'critical'
])->default('in_review');


            // جاهزية الانفصال
            $table->boolean('ready_to_separate')->default(false);
            $table->date('recommended_separation_date')->nullable();
            $table->date('actual_separation_date')->nullable();

            // القرار النهائي لكل متابعة
     $table->enum('review_status', [
    'in_review',     // قيد المتابعة
    'stable',        // المشروع مستقر
    'needs_support', // يحتاج دعم
    'critical',      // حالة حرجة
    'closed'         // المتابعة انتهت
])->default('in_review');



            $table->text('decision_notes')->nullable();

            $table->timestamps();

            // العلاقات
            $table->foreign('launch_project_id')
                ->references('id')
                ->on('launch_projects')
                ->onDelete('cascade');

            $table->foreign('recorded_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_launch_followups');
    }
};
