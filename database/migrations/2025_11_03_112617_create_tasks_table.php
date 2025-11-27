<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('idea_id')
                  ->constrained('ideas')
                  ->onDelete('cascade');

            $table->foreignId('gantt_id')
                  ->constrained('gantt_charts')
                  ->onDelete('cascade'); 

            $table->foreignId('owner_id')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null'); 


            $table->string('task_name', 255);
            $table->text('description')->nullable();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending');
            $table->unsignedTinyInteger('priority')->default(1); // ترتيب المهمة ضمن المرحلة
            $table->json('attachments')->nullable(); // ملفات مرفقة بالمرحلة

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
