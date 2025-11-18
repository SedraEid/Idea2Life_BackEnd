    <?php

    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration
    {
        public function up(): void
        {
            Schema::create('wallets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('user_type')->default('creator'); 
                $table->decimal('balance', 15, 2)->default(0);
                $table->enum('status', ['active', 'inactive', 'frozen'])->default('active');
                $table->timestamps();
            });
        }
        public function down(): void
        {
            Schema::dropIfExists('wallets');
        }
    };
