<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   
     public function up(): void
    {
        Schema::create('roadmaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')->constrained('ideas')->onDelete('cascade');
            $table->string('current_stage')->comment('اسم المرحلة الحالية مثل: التقييم، التطوير، التنفيذ');
            $table->text('stage_description')->nullable()->comment('وصف المرحلة الحالية');
            $table->integer('progress_percentage')->default(0)->comment('نسبة التقدم الحالية بالمشروع');
            $table->dateTime('last_update')->nullable()->comment('آخر تحديث في المرحلة');
            $table->text('next_step')->nullable()->comment('الخطوة القادمة في الخطة');
            $table->timestamps();
        });
    }
  
    public function down(): void
    {
        Schema::dropIfExists('roadmaps');
    }
};
