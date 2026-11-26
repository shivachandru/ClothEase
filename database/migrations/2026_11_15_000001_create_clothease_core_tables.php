<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('owner_name');
            $table->string('mobile', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('gstin', 20)->nullable();
            $table->string('state')->nullable();
            $table->string('currency', 3)->default('INR');
            $table->string('receipt_footer')->nullable();
            $table->boolean('allow_negative_stock')->default(false);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('shop_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('owner')->after('password');
            $table->string('pin_hash')->nullable()->after('role');
            $table->boolean('is_active')->default(true)->after('pin_hash');
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id(); $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name'); $table->timestamps(); $table->unique(['shop_id', 'name']);
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id(); $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name'); $table->string('brand')->nullable(); $table->text('description')->nullable();
            $table->string('status')->default('active'); $table->timestamps();
            $table->index(['shop_id', 'name']);
        });
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id(); $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('size')->nullable(); $table->string('colour')->nullable();
            $table->string('sku'); $table->string('barcode')->nullable();
            $table->decimal('cost_price', 12, 2)->default(0); $table->decimal('selling_price', 12, 2);
            $table->decimal('wholesale_price', 12, 2)->nullable(); $table->decimal('tax_rate', 5, 2)->default(0);
            $table->integer('stock_quantity')->default(0); $table->integer('low_stock_threshold')->default(5);
            $table->boolean('is_active')->default(true); $table->timestamps();
            $table->unique(['shop_id', 'sku']); $table->unique(['shop_id', 'barcode']);
            $table->index(['shop_id', 'barcode']); $table->index(['shop_id', 'stock_quantity']);
        });
        Schema::create('sales', function (Blueprint $table) {
            $table->id(); $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('bill_number'); $table->string('status')->default('completed');
            $table->decimal('subtotal', 12, 2); $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0); $table->decimal('total', 12, 2);
            $table->timestamp('sold_at'); $table->timestamps(); $table->unique(['shop_id', 'bill_number']);
            $table->index(['shop_id', 'sold_at']);
        });
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id(); $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('product_name'); $table->string('sku'); $table->string('variant_label')->nullable();
            $table->integer('quantity'); $table->decimal('unit_price', 12, 2); $table->decimal('cost_price', 12, 2);
            $table->decimal('discount', 12, 2)->default(0); $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0); $table->decimal('line_total', 12, 2); $table->timestamps();
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id(); $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method'); $table->decimal('amount', 12, 2); $table->string('reference')->nullable();
            $table->timestamp('paid_at'); $table->timestamps(); $table->index(['shop_id', 'method', 'paid_at']);
        });
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id(); $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('movement_type'); $table->integer('quantity_in')->default(0); $table->integer('quantity_out')->default(0);
            $table->integer('previous_quantity'); $table->integer('new_quantity');
            $table->string('reference_type')->nullable(); $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reason')->nullable(); $table->timestamps(); $table->index(['shop_id', 'created_at']);
        });
        Schema::create('expenses', function (Blueprint $table) {
            $table->id(); $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category'); $table->decimal('amount', 12, 2); $table->date('expense_date');
            $table->string('payment_method')->default('cash'); $table->string('description')->nullable(); $table->timestamps();
            $table->index(['shop_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses'); Schema::dropIfExists('stock_movements'); Schema::dropIfExists('payments');
        Schema::dropIfExists('sale_items'); Schema::dropIfExists('sales'); Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products'); Schema::dropIfExists('categories');
        Schema::table('users', function (Blueprint $table) { $table->dropConstrainedForeignId('shop_id'); $table->dropColumn(['role', 'pin_hash', 'is_active']); });
        Schema::dropIfExists('shops');
    }
};
