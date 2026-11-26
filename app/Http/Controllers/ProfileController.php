<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
class ProfileController extends Controller {
    public function edit(Request $request){return view('profile.edit',['user'=>$request->user(),'shop'=>$request->user()->shop]);}
    public function update(Request $request){
        $user=$request->user();
        $data=$request->validate(['name'=>['required','string','max:100'],'username'=>['required','alpha_dash','max:80',Rule::unique('users','username')->ignore($user->id)],'email'=>['required','email','max:150',Rule::unique('users','email')->ignore($user->id)],'shop_name'=>['required','string','max:150'],'password'=>['nullable','string','min:10','confirmed']]);
        $user->name=$data['name'];$user->username=$data['username'];$user->email=$data['email'];if(!empty($data['password']))$user->password=Hash::make($data['password']);$user->save();
        $user->shop?->update(['name'=>$data['shop_name']]);
        return back()->with('success','Profile updated.');
    }
}
