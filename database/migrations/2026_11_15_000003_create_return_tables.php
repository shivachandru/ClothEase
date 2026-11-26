<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sale_returns',function(Blueprint $table){
            $table->id(); $table->foreignId('shop_id')->constrained()->cascadeOnDelete(); $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $table->string('return_number');
            $table->decimal('refund_total',12,2); $table->string('status')->default('completed'); $table->timestamp('returned_at'); $table->timestamps();
            $table->unique(['shop_id','return_number']); $table->index(['shop_id','returned_at']);
        });
        Schema::create('sale_return_items',function(Blueprint $table){
            $table->id(); $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete(); $table->foreignId('sale_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete(); $table->string('product_name');
            $table->integer('quantity'); $table->string('condition'); $table->boolean('restocked')->default(false);
            $table->decimal('refund_amount',12,2); $table->timestamps();
        });
        Schema::create('refund_payments',function(Blueprint $table){
            $table->id(); $table->foreignId('shop_id')->constrained()->cascadeOnDelete(); $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
            $table->string('method'); $table->decimal('amount',12,2); $table->string('reference')->nullable(); $table->timestamp('refunded_at'); $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('refund_payments'); Schema::dropIfExists('sale_return_items'); Schema::dropIfExists('sale_returns'); }
};
