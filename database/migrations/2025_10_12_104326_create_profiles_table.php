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
        Schema::create('profiles', function (Blueprint $table) {
            $table->id('profile_id');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('idea_owner_id')->nullable()->constrained('idea_owners')->onDelete('cascade');
            $table->foreignId('committee_member_id')->nullable()->constrained('committee_members')->onDelete('set null');
            $table->string('phone')->nullable();
            $table->string('profile_image')->nullable();
            $table->text('bio')->nullable();
            $table->string('user_type')->nullable(); 
            $table->string('committee_role')->nullable(); 
            

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
