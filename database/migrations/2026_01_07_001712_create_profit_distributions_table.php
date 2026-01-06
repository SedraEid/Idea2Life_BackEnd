<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profit_distributions', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('idea_id')->constrained()->onDelete('cascade'); 
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // المستفيد
            $table->enum('user_role', ['idea_owner', 'investor', 'committee_member', 'admin']); 
            
            $table->decimal('amount', 15, 2)->default(0); // مبلغ الربح
            $table->decimal('percentage', 5, 2)->nullable(); // النسبة المئوية
            
            $table->text('notes')->nullable(); 
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profit_distributions');
    }
};
