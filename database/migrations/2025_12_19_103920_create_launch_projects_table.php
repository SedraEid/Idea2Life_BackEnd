<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
     Schema::create('launch_projects', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('idea_id');

    // حالة طلب الإطلاق
    $table->enum('status', [
        'pending',     // بانتظار موافقة اللجنة
        'approved',    // تمت الموافقة
        'rejected',    // مرفوض
        'launched'     // تم الإطلاق فعليًا
    ])->default('pending');

    $table->dateTime('launch_date')->nullable();
    $table->integer('launch_version')->default(1);

    // حالة المتابعة بعد الإطلاق
    $table->enum('followup_status', [
        'pending',             // لم تبدأ المتابعة بعد
        'ongoing',             // المتابعة جارية
        'challenge_detected',  // تم رصد تحديات
        'stabilized',          // المشروع مستقر
        'back_to_execution'    // عاد للتنفيذ
    ])->default('pending');

    // قرار نهائي
    $table->boolean('profit_allowed')->default(false); // السماح بتوزيع الأرباح
    $table->dateTime('stabilized_at')->nullable();     // تاريخ الاستقرار

    $table->timestamps();
    $table->foreign('idea_id')
          ->references('id')
          ->on('ideas')
          ->onDelete('cascade');
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('launch_projects');
    }
};
