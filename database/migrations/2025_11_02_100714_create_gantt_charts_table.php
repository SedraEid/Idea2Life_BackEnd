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
            
            $table->foreignId('idea_id')
                  ->constrained('ideas')
                  ->onDelete('cascade');

            $table->string('phase_name', 255); // اسم المرحلة
            $table->date('start_date');
            $table->date('end_date');

            $table->unsignedTinyInteger('progress')->default(0); // نسبة تقدم المرحلة
            $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending'); 
            $table->unsignedTinyInteger('priority')->default(1); // ترتيب المرحلة

            $table->timestamps();

            $table->index(['idea_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gantt_charts');
    }
};
