<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('barcode_prefix', 30)->default('')->after('bill_next_number');
            $table->boolean('barcode_use_prefix')->default(false)->after('barcode_prefix');
            $table->unsignedBigInteger('barcode_next_number')->default(1)->after('barcode_use_prefix');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['barcode_prefix', 'barcode_use_prefix', 'barcode_next_number']);
        });
    }
};
