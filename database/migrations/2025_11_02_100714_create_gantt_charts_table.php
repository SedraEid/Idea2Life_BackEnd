<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gantt_charts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')->constrained()->onDelete('cascade'); 
            $table->string('phase_name'); // اسم المرحلة
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('priority')->default(1); 
            $table->enum('status', ['pending', 'in_progress', 'completed', 'needs_improvement'])->default('pending');
            $table->integer('progress')->default(0); // نسبة الإنجاز
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->decimal('evaluation_score', 5, 2)->nullable(); 
            $table->integer('failure_count')->default(0); // عدد مرات الفشل
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gantt_charts');
    }
};
