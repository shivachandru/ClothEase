<?php
namespace App\Http\Controllers;

use App\Models\AccessRole;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public const PERMISSIONS=['pos'=>'New bill','products'=>'Products','inventory'=>'Inventory','purchases'=>'Purchases','suppliers'=>'Suppliers','sales'=>'Bill history','returns'=>'Process returns','customers'=>'Customers','expenses'=>'Expenses','reports'=>'Sales report'];
    public function index(Request $request){$users=User::where('shop_id',$request->user()->shop_id)->with('accessRole')->orderBy('name')->paginate(20);return view('users.index',['users'=>$users,'permissions'=>self::PERMISSIONS]);}
    public function create(Request $request){$roles=AccessRole::where('shop_id',$request->user()->shop_id)->orderBy('name')->get();return view('users.create',compact('roles'));}
    public function edit(Request $request,User $user){abort_unless($user->shop_id===$request->user()->shop_id,404);$roles=AccessRole::where('shop_id',$request->user()->shop_id)->orderBy('name')->get();return view('users.edit',['staff'=>$user,'roles'=>$roles]);}
    public function store(Request $request,AuditService $audit)
    {
        $shopId=$request->user()->shop_id;
        $data=$request->validate(['name'=>['required','string','max:100'],'username'=>['required','alpha_dash','max:80','unique:users,username'],'email'=>['required','email','max:150','unique:users,email'],
            'access_role_id'=>['required','integer',Rule::exists('access_roles','id')->where('shop_id',$shopId)],'password'=>['required','string','min:10','confirmed'],'pin'=>['nullable','digits_between:4,8']]);
        DB::transaction(function()use($request,$data,$audit){$staff=User::create(['shop_id'=>$request->user()->shop_id,'name'=>$data['name'],'username'=>$data['username'],'email'=>$data['email'],
            'role'=>'cashier','access_role_id'=>$data['access_role_id'],'password'=>Hash::make($data['password']),'pin_hash'=>isset($data['pin'])?Hash::make($data['pin']):null,'is_active'=>true]);
            $audit->record($request->user(),'user.created',$staff,['email'=>$staff->email,'access_role_id'=>$staff->access_role_id]);});
        return redirect()->route('users.index')->with('success','Staff account created.');
    }
    public function update(Request $request,User $user,AuditService $audit)
    {
        abort_unless($user->shop_id===$request->user()->shop_id,404);$shopId=$request->user()->shop_id;
        $data=$request->validate(['name'=>['required','string','max:100'],'username'=>['required','alpha_dash','max:80',Rule::unique('users','username')->ignore($user->id)],
            'email'=>['required','email','max:150',Rule::unique('users','email')->ignore($user->id)],'access_role_id'=>[$user->role==='owner'?'prohibited':'required','integer',Rule::exists('access_roles','id')->where('shop_id',$shopId)],
            'password'=>['nullable','string','min:10','confirmed'],'pin'=>['nullable','digits_between:4,8'],'is_active'=>['required','boolean']]);
        if($user->is($request->user())&&!(bool)$data['is_active'])throw ValidationException::withMessages(['is_active'=>'You cannot disable your own account.']);
        $before=['access_role_id'=>$user->access_role_id,'is_active'=>$user->is_active];$user->name=$data['name'];$user->username=$data['username'];$user->email=$data['email'];$user->is_active=(bool)$data['is_active'];
        if($user->role!=='owner')$user->access_role_id=$data['access_role_id'];if(!empty($data['password']))$user->password=Hash::make($data['password']);if(!empty($data['pin']))$user->pin_hash=Hash::make($data['pin']);
        $user->save();$audit->record($request->user(),'user.updated',$user,['before'=>$before,'access_role_id'=>$user->access_role_id,'is_active'=>$user->is_active]);
        return redirect()->route('users.index')->with('success','User account updated.');
    }
}
