<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->onDelete('cascade');
            $table->foreignId('funding_id')->nullable()->constrained('fundings')->onDelete('set null');
            $table->foreignId('sender_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('receiver_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('transaction_type', [
                'deposit',         
                'withdrawal',       
                'transfer',        
                'distribution',     
                'refund'            
            ])->default('deposit');

            // المبلغ الكامل
            $table->decimal('amount', 12, 2);
            // النسبة (في حال التوزيع)
            $table->decimal('percentage', 5, 2)->nullable();
            // الطرف الذي حصل على النسبة (مثل: المنصة، اللجنة، المستثمر، المبدع)
        $table->enum('beneficiary_role', [
        'creator',       // صاحب الفكرة
        'investor',      // المستثمر
        'economist',     // عضو اللجنة: الاقتصادي
        'market',        // عضو اللجنة: خبير السوق
        'technical',     // عضو اللجنة: الخبير التقني
        'legal',         // عضو اللجنة: المستشار القانوني
        'committee',     // اللجنة كمجموعة
         'platform',
         'admin'       // المنصة نفسها
       ])->nullable();


            // حالة العملية
            $table->enum('status', [
                'pending',    
                'processing',
                'completed', 
                'failed'     
            ])->default('pending');
            $table->string('payment_method')->nullable(); 
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
