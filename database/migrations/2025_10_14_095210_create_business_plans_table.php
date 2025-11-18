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
        Schema::create('business_plans', function (Blueprint $table) {
            $table->id();            
            $table->foreignId('idea_id')->constrained('ideas')->onDelete('cascade');
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('committee_id')->nullable()->constrained('committees')->onDelete('set null');
            $table->foreignId('report_id')->nullable()->constrained('reports')->onDelete('set null');
            $table->foreignId('meeting_id')->nullable()->constrained('meetings')->onDelete('set null');
            $table->text('key_partners')->nullable();
            $table->text('key_activities')->nullable();
            $table->text('key_resources')->nullable();
            $table->text('value_proposition')->nullable();
            $table->text('customer_relationships')->nullable();
            $table->text('channels')->nullable();
            $table->text('customer_segments')->nullable();
            $table->text('cost_structure')->nullable();
            $table->text('revenue_streams')->nullable();
            $table->enum('status', ['draft', 'under_review', 'approved', 'rejected','needs_revision'])->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_plans');
    }
};
