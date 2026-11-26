<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Sale extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['sold_at' => 'datetime', 'total' => 'decimal:2', 'incl_tax' => 'boolean', 'tendered_amount' => 'decimal:2', 'change_due' => 'decimal:2']; }
    public function items() { return $this->hasMany(SaleItem::class); }
    public function payments() { return $this->hasMany(Payment::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function returns() { return $this->hasMany(SaleReturn::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
}
