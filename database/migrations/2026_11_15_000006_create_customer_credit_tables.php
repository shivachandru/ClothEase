<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customers',function(Blueprint $table){$table->id();$table->foreignId('shop_id')->constrained()->cascadeOnDelete();$table->string('name');$table->string('mobile',20)->nullable();$table->text('address')->nullable();$table->text('notes')->nullable();$table->timestamps();$table->unique(['shop_id','mobile']);$table->index(['shop_id','name']);});
        Schema::table('sales',function(Blueprint $table){$table->foreignId('customer_id')->nullable()->after('user_id')->constrained()->nullOnDelete();$table->decimal('amount_paid',12,2)->default(0)->after('total');$table->decimal('balance_due',12,2)->default(0)->after('amount_paid');});
        Schema::create('customer_payments',function(Blueprint $table){$table->id();$table->foreignId('shop_id')->constrained()->cascadeOnDelete();$table->foreignId('customer_id')->constrained()->restrictOnDelete();$table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();$table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();$table->string('method');$table->decimal('amount',12,2);$table->string('reference')->nullable();$table->timestamp('paid_at');$table->timestamps();$table->index(['shop_id','customer_id','paid_at']);});
    }
    public function down(): void
    {
        Schema::dropIfExists('customer_payments');Schema::table('sales',function(Blueprint $table){$table->dropConstrainedForeignId('customer_id');$table->dropColumn(['amount_paid','balance_due']);});Schema::dropIfExists('customers');
    }
};
