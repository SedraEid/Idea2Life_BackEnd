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

            // تحديات
            $table->boolean('challenge_detected')->default(false);
            $table->text('challenge_description')->nullable();
            $table->text('action_taken')->nullable();

            // مؤشرات أداء KPI
            $table->integer('kpi_active_users')->nullable();
            $table->integer('kpi_sales')->nullable();
            $table->integer('kpi_user_growth')->nullable();
            $table->integer('kpi_engagement')->nullable();

            // تقييم عام من اللجنة
            $table->enum('overall_status', [
                'good',       
                'needs_support', 
                'critical'    
            ])->default('good');

            // جاهزية الانفصال
            $table->boolean('ready_to_separate')->default(false);
            $table->date('recommended_separation_date')->nullable();
            $table->date('actual_separation_date')->nullable();

            // قرار اللجنة النهائي
            $table->enum('committee_decision', [
                'pending',     
                'approved',    
                'rejected'    
            ])->default('pending');

            $table->text('decision_notes')->nullable(); 

            //من الذي أدخل البيانات؟
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();
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
