<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->unsignedTinyInteger('receipt_header_font_size')->default(18)->after('receipt_font_size');
            $table->unsignedTinyInteger('receipt_items_font_size')->default(9)->after('receipt_header_font_size');
            $table->boolean('receipt_show_gstin')->default(true)->after('receipt_items_font_size');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['receipt_header_font_size', 'receipt_items_font_size', 'receipt_show_gstin']);
        });
    }
};
