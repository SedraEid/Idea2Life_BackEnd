<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('launch_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')
                ->constrained('ideas')
                ->onDelete('cascade');
                
 $table->integer('version')->nullable()
    ->comment('رقم الإصدار أو النسخة لكل إطلاق فعلي (v1, v2, …)');

$table->text('execution_steps')->nullable()
    ->comment('الخطوات العملية المحددة لتشغيل المشروع بعد الإطلاق، مفهومة للجنة وصاحب الفكرة');

$table->text('marketing_strategy')->nullable()
    ->comment('خطة تسويقية محددة للمنتج أو الخدمة بعد الإطلاق، تشمل القنوات والجمهور المستهدف');

$table->text('risk_mitigation')->nullable()
    ->comment('خطوات واضحة لتقليل المخاطر المحتملة بعد الإطلاق، تشمل مشاكل المنتج أو السوق');


            $table->boolean('founder_commitment')
                ->default(false)
                ->comment('تعهد صاحب الفكرة بالالتزام بخطة الإطلاق');

            $table->enum('status', [
                'submitted',   // تم تقديم الطلب
                'under_review',// قيد المراجعة
                'approved',    // تمت الموافقة
                'rejected',    // مرفوض
                'launched',    // تم الإطلاق فعليًا
                'halted'       // تم إيقاف المشروع بعد الإطلاق
            ])->default('submitted');

            $table->text('committee_notes')->nullable();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('launch_date')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('launch_requests');
    }
};
