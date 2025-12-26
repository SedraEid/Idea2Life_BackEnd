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
            $table->foreignId('investor_id')->nullable()->constrained('users')->onDelete('set null'); 

            $table->decimal('requested_amount', 12, 2); // المبلغ الذي طلبه صاحب الفكرة
            $table->text('justification')->nullable(); // سبب طلب التمويل

            $table->text('committee_notes')->nullable();
            $table->boolean('is_approved')->nullable()->after('approved_amount');

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
