<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) { $table->string('username',80)->nullable()->unique(); $table->json('permissions')->nullable(); });
        DB::table('users')->orderBy('id')->get(['id','email','name','role'])->each(function($user){
            $base=Str::slug(explode('@',$user->email)[0]?:$user->name,'_') ?: 'user'; $username=$base; $suffix=1;
            while(DB::table('users')->where('username',$username)->exists())$username=$base.'_'.$suffix++;
            $permissions=$user->role==='stock_manager'?['products','inventory','purchases','suppliers','sales']:($user->role==='cashier'?['pos','sales','customers']:null);
            DB::table('users')->where('id',$user->id)->update(['username'=>$username,'permissions'=>$permissions?json_encode($permissions):null]);
        });
    }
    public function down(): void { Schema::table('users', function (Blueprint $table) { $table->dropUnique(['username']); $table->dropColumn(['username','permissions']); }); }
};
