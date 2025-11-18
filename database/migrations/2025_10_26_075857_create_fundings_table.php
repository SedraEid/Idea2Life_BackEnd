<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   
    public function up(): void
    {
        Schema::create('fundings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('idea_id')->constrained('ideas')->onDelete('cascade');
            $table->foreignId('idea_owner_id')->nullable()->constrained('idea_owners')->onDelete('set null');
            $table->foreignId('committee_id')->nullable()->constrained('committees')->onDelete('set null');
            $table->foreignId('investor_id')->nullable()->constrained('users')->onDelete('set null'); 
            $table->foreignId('meeting_id')->nullable()->constrained('meetings')->onDelete('set null'); 


            $table->decimal('requested_amount', 12, 2); // المبلغ الذي طلبه صاحب الفكرة
            $table->text('justification')->nullable(); // سبب طلب التمويل

            $table->foreignId('report_id')->nullable()->constrained('reports')->onDelete('set null'); 
            $table->boolean('requirements_verified')->default(false); //هل تم التحقق من الشروط من قبل اللجنة
            $table->text('committee_notes')->nullable();

            $table->decimal('approved_amount', 12, 2)->nullable(); // المبلغ الموافق عليه فعليًا
            $table->string('payment_method')->nullable(); // طريقة التحويل (محفظة)
            $table->timestamp('transfer_date')->nullable(); // تاريخ تنفيذ التحويل
            $table->string('transaction_reference')->nullable(); // رقم العملية أو معرف التحويل

            $table->enum('status', [
                'requested',      // تم الطلب من قبل صاحب الفكرة
                'under_review',   // قيد المراجعة من اللجنة
                'approved',       // تمت الموافقة من المستثمر/اللجنة
                'rejected',       // مرفوض من اللجنة أو المستثمر
                'funded',          // تم التحويل فعليًا
                'cancelled',
            ])->default('requested');

            $table->timestamps();
        });
    }

  
    public function down(): void
    {
        Schema::dropIfExists('fundings');
    }
};
