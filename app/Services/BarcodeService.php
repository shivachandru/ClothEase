<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\Shop;

class BarcodeService
{
    /** Must be called inside a database transaction. */
    public function nextForShop(int $shopId): string
    {
        $shop = Shop::whereKey($shopId)->lockForUpdate()->firstOrFail();
        $number = max(1, (int) $shop->barcode_next_number);
        $prefix = $shop->barcode_use_prefix ? trim((string) $shop->barcode_prefix) : '';

        do {
            $barcode = $prefix . $number;
            $number++;
        } while (ProductVariant::where('shop_id', $shopId)->where('barcode', $barcode)->exists());

        $shop->barcode_next_number = $number;
        $shop->save();

        return $barcode;
    }

    public function previewForShop(int $shopId): string
    {
        $shop = Shop::findOrFail($shopId);
        $number = max(1, (int) $shop->barcode_next_number);
        $prefix = $shop->barcode_use_prefix ? trim((string) $shop->barcode_prefix) : '';
        while (ProductVariant::where('shop_id', $shopId)->where('barcode', $prefix . $number)->exists()) {
            $number++;
        }

        return $prefix . $number;
    }
}
