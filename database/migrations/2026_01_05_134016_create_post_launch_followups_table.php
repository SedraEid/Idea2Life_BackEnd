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
       Schema::create('post_launch_followups', function (Blueprint $table) {
    $table->id();

    $table->foreignId('launch_request_id')
        ->constrained('launch_requests')
        ->cascadeOnDelete();

    $table->enum('followup_phase', ['week_1','month_1','month_3','month_6']);

    $table->date('scheduled_date');

    $table->enum('status', ['pending','done','issue_detected'])
        ->default('pending');

    $table->enum('performance_status', [
        'excellent',
        'stable',
        'at_risk',
        'failing'
    ])->nullable();

    $table->enum('committee_decision', [
        'continue',
        'extra_support',
        'pivot_required',
        'terminate',
        'graduate'
    ])->nullable();

    $table->boolean('marketing_support_given')->default(false);

    $table->boolean('product_issue_detected')->default(false);

    $table->text('actions_taken')->nullable();

    $table->text('committee_notes')->nullable();

    $table->foreignId('reviewed_by')
        ->nullable()
        ->constrained('users')
        ->nullOnDelete();

    $table->boolean('is_stable')->default(false);

    $table->boolean('profit_distributed')->default(false);    

    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_launch_followups');
    }
};
