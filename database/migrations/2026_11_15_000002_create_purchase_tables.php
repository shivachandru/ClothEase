<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id(); $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name'); $table->string('mobile',20)->nullable(); $table->string('email')->nullable();
            $table->text('address')->nullable(); $table->text('notes')->nullable(); $table->boolean('is_active')->default(true);
            $table->timestamps(); $table->index(['shop_id','name']);
        });
        Schema::create('purchases', function (Blueprint $table) {
            $table->id(); $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete(); $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number'); $table->date('purchase_date'); $table->string('status')->default('unpaid');
            $table->decimal('subtotal',12,2); $table->decimal('discount',12,2)->default(0); $table->decimal('tax_total',12,2)->default(0);
            $table->decimal('total',12,2); $table->decimal('amount_paid',12,2)->default(0); $table->decimal('balance_due',12,2)->default(0);
            $table->timestamps(); $table->unique(['shop_id','invoice_number']); $table->index(['shop_id','purchase_date']);
        });
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id(); $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('product_name'); $table->string('sku'); $table->string('variant_label')->nullable();
            $table->integer('quantity'); $table->decimal('unit_cost',12,2); $table->decimal('discount',12,2)->default(0);
            $table->decimal('tax_rate',5,2)->default(0); $table->decimal('tax_amount',12,2)->default(0); $table->decimal('line_total',12,2); $table->timestamps();
        });
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id(); $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete(); $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $table->string('method');
            $table->decimal('amount',12,2); $table->string('reference')->nullable(); $table->timestamp('paid_at'); $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('supplier_payments'); Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases'); Schema::dropIfExists('suppliers');
    }
};
