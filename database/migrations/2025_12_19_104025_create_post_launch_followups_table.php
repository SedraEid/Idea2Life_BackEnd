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
            $table->unsignedBigInteger('idea_id');

            // نقطة المتابعة
            $table->enum('checkpoint', [
                'week_1',
                'month_1',
                'month_3',
                'month_6',
                'emergency'
            ]);

            // نوع التقييم
            $table->enum('issue_type', [
                'none',
                'technical',
                'financial',
                'operational',
                'marketing',
                'legal',
                'other'
            ])->default('none');

            // وصف المشكلة
            $table->text('issue_description')->nullable();

            // إجراء المنصة
            $table->text('platform_action')->nullable();

            // نتيجة المتابعة
            $table->enum('status', [
                'pending',
                'in_review',
                'resolved',
                'failed'
            ])->default('pending');

            // هل تحتاج إعادة تنفيذ؟
            $table->boolean('requires_reexecution')->default(false);

            // توصية اللجنة
            $table->text('committee_recommendation')->nullable();

            // من قام بالتقييم
            $table->unsignedBigInteger('reviewed_by')->nullable();

            $table->timestamps();

            $table->foreign('launch_project_id')
                  ->references('id')
                  ->on('launch_projects')
                  ->onDelete('cascade');

            $table->foreign('idea_id')
                  ->references('id')
                  ->on('ideas')
                  ->onDelete('cascade');

            $table->foreign('reviewed_by')
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
