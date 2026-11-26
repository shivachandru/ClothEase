<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sale_return_items',function(Blueprint $table){$table->decimal('tax_amount',12,2)->default(0)->after('refund_amount');$table->decimal('cost_amount',12,2)->default(0)->after('tax_amount');});
    }
    public function down(): void
    {
        Schema::table('sale_return_items',function(Blueprint $table){$table->dropColumn(['tax_amount','cost_amount']);});
    }
};
