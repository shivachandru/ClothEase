<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        DB::table('shops')->orderBy('id')->pluck('id')->each(function($shopId){
            foreach(['Cashier'=>['pos','sales','customers'],'Stock manager'=>['products','inventory','purchases','suppliers','sales']] as $name=>$permissions){
                if(!DB::table('access_roles')->where('shop_id',$shopId)->where('name',$name)->exists())DB::table('access_roles')->insert(['shop_id'=>$shopId,'name'=>$name,'permissions'=>json_encode($permissions),'created_at'=>now(),'updated_at'=>now()]);
            }
        });
    }
    public function down(): void {}
};
