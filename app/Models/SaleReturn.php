<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SaleReturn extends Model
{
    protected $guarded=['id'];
    protected function casts(): array { return ['returned_at'=>'datetime','refund_total'=>'decimal:2']; }
    public function items(){return $this->hasMany(SaleReturnItem::class);}
    public function payments(){return $this->hasMany(RefundPayment::class);}
    public function exchangeSale(){return $this->belongsTo(Sale::class,'exchange_sale_id');}
}
