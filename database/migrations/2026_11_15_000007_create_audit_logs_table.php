<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_logs',function(Blueprint $table){$table->id();$table->foreignId('shop_id')->constrained()->cascadeOnDelete();$table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();$table->string('action');$table->string('subject_type');$table->unsignedBigInteger('subject_id');$table->json('details')->nullable();$table->timestamp('created_at')->useCurrent();$table->index(['shop_id','created_at']);$table->index(['subject_type','subject_id']);});
    }
    public function down(): void { Schema::dropIfExists('audit_logs'); }
};
