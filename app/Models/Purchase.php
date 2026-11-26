<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Purchase extends Model
{
    protected $guarded=['id'];
    protected function casts(): array { return ['purchase_date'=>'date','total'=>'decimal:2']; }
    public function supplier(){return $this->belongsTo(Supplier::class);}
    public function items(){return $this->hasMany(PurchaseItem::class);}
    public function payments(){return $this->hasMany(SupplierPayment::class);}
}
