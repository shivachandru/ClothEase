<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CustomerPayment extends Model
{
    protected $guarded=['id'];
    protected function casts(): array { return ['paid_at'=>'datetime','amount'=>'decimal:2']; }
    public function sale(){return $this->belongsTo(Sale::class);}
}
