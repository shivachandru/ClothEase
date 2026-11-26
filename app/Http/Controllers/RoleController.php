<?php
namespace App\Http\Controllers;
use App\Models\AccessRole;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller {
    public function index(Request $request){$roles=AccessRole::where('shop_id',$request->user()->shop_id)->withCount('users')->orderBy('name')->get();return view('masters.roles.index',['roles'=>$roles,'permissions'=>UserController::PERMISSIONS]);}
    public function create(){return view('masters.roles.create',['permissions'=>UserController::PERMISSIONS]);}
    public function store(Request $request,AuditService $audit){$data=$this->validateRole($request);$role=AccessRole::create(['shop_id'=>$request->user()->shop_id,'name'=>$data['name'],'permissions'=>$data['permissions']??[]]);$audit->record($request->user(),'access_role.created',$role,['name'=>$role->name,'permissions'=>$role->permissions]);return redirect()->route('masters.roles.index')->with('success','Role created.');}
    public function edit(Request $request,AccessRole $role){abort_unless($role->shop_id===$request->user()->shop_id,404);return view('masters.roles.edit',['role'=>$role,'permissions'=>UserController::PERMISSIONS]);}
    public function update(Request $request,AccessRole $role,AuditService $audit){abort_unless($role->shop_id===$request->user()->shop_id,404);$data=$this->validateRole($request,$role);$before=$role->permissions;$role->update(['name'=>$data['name'],'permissions'=>$data['permissions']??[]]);$audit->record($request->user(),'access_role.updated',$role,['before'=>$before,'permissions'=>$role->permissions]);return redirect()->route('masters.roles.index')->with('success','Role updated. Assigned users now have the updated access.');}
    public function destroy(Request $request,AccessRole $role){abort_unless($role->shop_id===$request->user()->shop_id,404);if($role->users()->exists())return back()->withErrors(['role'=>'This role is assigned to users. Reassign them before deleting it.']);$role->delete();return redirect()->route('masters.roles.index')->with('success','Role deleted.');}
    private function validateRole(Request $request,?AccessRole $role=null):array{return $request->validate(['name'=>['required','string','max:80',Rule::unique('access_roles','name')->where('shop_id',$request->user()->shop_id)->ignore($role?->id)],'permissions'=>['nullable','array'],'permissions.*'=>['required',Rule::in(array_keys(UserController::PERMISSIONS))]]);}
}
