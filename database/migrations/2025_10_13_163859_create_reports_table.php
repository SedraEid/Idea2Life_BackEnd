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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')->constrained('ideas')->onDelete('cascade');
            $table->foreignId('meeting_id')->nullable()->constrained('meetings')->onDelete('cascade');
            $table->foreignId('committee_id')->constrained('committees')->onDelete('cascade');
            $table->text('description')->nullable();
            $table->string('report_type'); 
            $table->decimal('evaluation_score', 5, 2)->nullable();
            $table->text('strengths')->nullable();
            $table->text('weaknesses')->nullable();
            $table->text('recommendations')->nullable();
            $table->string('status')->default('pending'); 

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
