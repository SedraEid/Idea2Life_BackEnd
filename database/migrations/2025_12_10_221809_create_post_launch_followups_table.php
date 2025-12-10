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
            $table->boolean('challenge_detected')->default(false); 
            $table->text('challenge_description')->nullable(); 
            $table->text('action_taken')->nullable(); 
            $table->unsignedBigInteger('recorded_by')->nullable(); 
            
            // KPI / مؤشرات الأداء
            $table->integer('kpi_active_users')->default(0); // عدد المستخدمين النشطين
            $table->integer('kpi_sales')->default(0); // حجم المبيعات أو العائد
            $table->integer('kpi_user_growth')->default(0); // نمو المستخدمين
            $table->integer('kpi_engagement')->default(0); // مستوى التفاعل
            
            // انفصال المشروع
            $table->boolean('ready_to_separate')->default(false); // هل المشروع جاهز للانفصال
            $table->dateTime('separation_date')->nullable(); // تاريخ الانفصال المتوقع أو الفعلي
            $table->text('profit_distribution_notes')->nullable(); // ملاحظات حول توزيع الأرباح بعد الانفصال

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
