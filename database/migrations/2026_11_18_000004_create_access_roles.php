<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('access_roles', function(Blueprint $table){
            $table->id();$table->foreignId('shop_id')->constrained()->cascadeOnDelete();$table->string('name',80);$table->json('permissions');$table->timestamps();$table->unique(['shop_id','name']);
        });
        Schema::table('users', function(Blueprint $table){$table->foreignId('access_role_id')->nullable()->constrained('access_roles')->nullOnDelete();});
        DB::table('shops')->orderBy('id')->pluck('id')->each(function($shopId){
            foreach(['cashier'=>'Cashier','stock_manager'=>'Stock manager'] as $legacy=>$name){
                $members=DB::table('users')->where('shop_id',$shopId)->where('role',$legacy)->get(['id','permissions']);
                $permissions=$members->isNotEmpty()&&$members->first()->permissions?json_decode($members->first()->permissions,true):($legacy==='cashier'?['pos','sales','customers']:['products','inventory','purchases','suppliers','sales']);
                $roleId=DB::table('access_roles')->insertGetId(['shop_id'=>$shopId,'name'=>$name,'permissions'=>json_encode($permissions?:[]),'created_at'=>now(),'updated_at'=>now()]);
                foreach($members as $member)DB::table('users')->where('id',$member->id)->update(['access_role_id'=>$roleId]);
            }
        });
    }
    public function down(): void {
        Schema::table('users',function(Blueprint $table){$table->dropConstrainedForeignId('access_role_id');});
        Schema::dropIfExists('access_roles');
    }
};
