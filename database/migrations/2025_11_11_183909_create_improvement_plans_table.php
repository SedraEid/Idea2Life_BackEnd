<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('improvement_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')->constrained('ideas')->onDelete('cascade');
            $table->foreignId('report_id')->constrained('reports')->onDelete('cascade');
            $table->foreignId('gantt_chart_id')->nullable()->constrained('gantt_charts')->onDelete('set null');

            $table->text('root_cause')->nullable(); // أسباب القصور
            $table->longText('corrective_actions')->nullable(); // الإجراءات التصحيحية
            $table->longText('revised_goals')->nullable(); // الأهداف المعدلة
            $table->longText('support_needed')->nullable(); // الدعم المطلوب من اللجنة

            $table->date('deadline')->nullable(); 
            $table->enum('status', ['pending', 'submitted', 'approved', 'rejected'])->default('pending');

            $table->decimal('committee_score', 5, 2)->nullable(); // تقييم اللجنة (0 - 100)
            $table->text('committee_feedback')->nullable(); // ملاحظات اللجنة
            $table->date('next_review_date')->nullable(); // موعد المراجعة القادمة إن وُجد

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('improvement_plans');
    }
};
