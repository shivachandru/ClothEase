<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SupplierPayment extends Model
{
    protected $guarded=['id'];
    protected function casts(): array { return ['paid_at'=>'datetime','amount'=>'decimal:2']; }
    public function purchase(){return $this->belongsTo(Purchase::class);}
}
