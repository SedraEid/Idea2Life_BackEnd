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
       Schema::create('evaluations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('idea_id')->constrained()->onDelete('cascade');
    $table->foreignId('committee_id')->constrained()->onDelete('cascade');
    $table->foreignId('business_plan_id')->nullable()->constrained()->onDelete('cascade');
    $table->enum('evaluation_type', ['initial', 'advanced']);
    $table->integer('score')->nullable();
    $table->string('recommendation')->nullable();
    $table->text('comments')->nullable();
    $table->text('strengths')->nullable();
    $table->text('weaknesses')->nullable();
    $table->text('financial_analysis')->nullable();
    $table->text('risks')->nullable();
    $table->string('status')->default('pending');
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
