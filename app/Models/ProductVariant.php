<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $guarded = ['id']; 
    protected function casts(): array { return ['is_active' => 'boolean', 'stock_quantity' => 'integer', 'low_stock_threshold' => 'integer', 'cost_price' => 'decimal:2', 'selling_price' => 'decimal:2']; }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
