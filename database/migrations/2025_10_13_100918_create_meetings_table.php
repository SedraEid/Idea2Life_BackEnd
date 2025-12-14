<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   

   public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')->constrained('ideas')->onDelete('cascade');
            $table->dateTime('meeting_date');
            $table->string('meeting_link')->nullable();
            $table->text('notes')->nullable();
            $table->enum('requested_by', ['owner', 'committee']);
            $table->string('type')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
