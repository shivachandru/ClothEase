<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['bill_next_number' => 'integer', 'barcode_use_prefix' => 'boolean', 'barcode_next_number' => 'integer', 'receipt_alignment' => 'array']; }
    public function users(): HasMany { return $this->hasMany(User::class); }
    public function products(): HasMany { return $this->hasMany(Product::class); }
    public function variants(): HasMany { return $this->hasMany(ProductVariant::class); }
    public function sales(): HasMany { return $this->hasMany(Sale::class); }
}
