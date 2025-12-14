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
    Schema::create('ideas', function (Blueprint $table) {
        $table->id();
        $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
        $table->foreignId('committee_id')->nullable()->constrained('committees')->onDelete('set null');
        $table->string('title');
        $table->text('description');
        $table->text('problem')->nullable();
        $table->text('solution')->nullable();
        $table->string('target_audience')->nullable();
        $table->text('additional_notes')->nullable();
        $table->string('status')->default('pending');
        $table->string('roadmap_stage')->nullable(); 
        $table->float('initial_evaluation_score')->nullable();
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ideas');
    }
};
