<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('idea_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('requested_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->text('reason');

            $table->enum('status', [
                'pending',
                'approved',
                'rejected'
            ])->default('pending');

            $table->decimal('penalty_amount', 15, 2)
                ->default(0);

            $table->boolean('penalty_paid')
                ->default(false);

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')
                ->nullable();

            $table->text('committee_notes')
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
    }
};
