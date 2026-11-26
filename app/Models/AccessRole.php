<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AccessRole extends Model {
    protected $table='access_roles';
    protected $fillable=['shop_id','name','permissions'];
    protected function casts():array{return ['permissions'=>'array'];}
    public function users(){return $this->hasMany(User::class,'access_role_id');}
}
