<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up(): void
    {
        Schema::create('launch_projects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idea_id');
            $table->enum('status', ['pending', 'approved', 'rejected', 'launched'])->default('pending');
            $table->dateTime('launch_date')->nullable();
            $table->timestamps();
            $table->foreign('idea_id')->references('id')->on('ideas')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('launches_projects');
    }
};
