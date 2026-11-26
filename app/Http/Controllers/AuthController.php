<?php
namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private const SHOP_DATABASE_NAME = 'database';

    public function login() { return view('auth.login', ['setup' => Shop::exists() === false]); }
    public function authenticate(Request $request)
    {
        $credentials = $request->validate(['username' => ['required','string'], 'password' => ['required','string']]);
        $identity=filter_var($credentials['username'],FILTER_VALIDATE_EMAIL)?['email'=>$credentials['username']]:['username'=>$credentials['username']];
        if (Auth::attempt($identity + ['is_active' => true] + ['password'=>$credentials['password']], $request->boolean('remember'))) {
            $request->session()->regenerate(); return redirect()->intended(route('dashboard'))->with('success', 'Signed in successfully.');
        }
        return back()->withErrors(['email' => 'These sign-in details did not match an active account.'])->onlyInput('email');
    }
    public function quickLogin(Request $request)
    {
        $data=$request->validate(['email'=>['required','email'],'pin'=>['required','digits_between:4,8']]);
        $user=User::where('email',$data['email'])->where('is_active',true)->first();
        if($user&&$user->pin_hash&&Hash::check($data['pin'],$user->pin_hash)){
            Auth::login($user);$request->session()->regenerate();return redirect()->intended(route('dashboard'))->with('success', 'Signed in successfully.');
        }
        return back()->withErrors(['pin'=>'The cashier email or PIN was not recognised.'])->onlyInput('email');
    }
    public function register()
    {
        if (Shop::exists()) {
            return redirect()->route('login')->with('notice', 'This shop is already set up. Sign in to continue.');
        }
        return view('auth.register');
    }
    public function storeShop(Request $request)
    {
        abort_if(Shop::exists(), 404);
        $data = $request->validate(['shop_name'=>['required','string','max:150','regex:/nisha/i'],
            'owner_name'=>['required','string','max:100'],
            'mobile'=>['nullable','string','max:20'],'email'=>['required','email','max:150','unique:users,email'],
            'password'=>['required','string','min:10','confirmed']],
            ['shop_name.regex'=>'The shop name must include “Nisha”.']);
        if (config('database.default') !== 'sqlite') {
            return back()->withErrors(['shop_name'=>'Shop setup could not be completed. Please check the local installation and try again.'])->withInput();
        }
        // Store shop data beside the Windows environment, outside the
        // replaceable program directory, under the standard internal name.
        $environmentPath=app()->environmentPath();
        $databaseRoot=realpath($environmentPath)===realpath(base_path())
            ? database_path()
            : $environmentPath;
        $databasePath=rtrim($databaseRoot,'\\/').DIRECTORY_SEPARATOR.self::SHOP_DATABASE_NAME.'.sqlite';
        $originalDatabase=config('database.connections.sqlite.database');
        $envPath=$environmentPath.DIRECTORY_SEPARATOR.'.env';
        $originalEnv=File::exists($envPath)?File::get($envPath):null;
        $databaseExisted=File::exists($databasePath);
        try {
            if (!$databaseExisted && File::put($databasePath,'') === false) {
                throw new \RuntimeException('Could not create the requested SQLite file.');
            }
            config(['database.connections.sqlite.database'=>$databasePath]);
            DB::purge('sqlite');
            if (Artisan::call('migrate',['--force'=>true]) !== 0) {
                throw new \RuntimeException('Database migrations did not complete successfully.');
            }
            $user = DB::transaction(function () use ($data) {
                $shop = Shop::create(['name'=>$data['shop_name'],'owner_name'=>$data['owner_name'],'mobile'=>$data['mobile'] ?? null]);
                $username=Str::slug(explode('@',$data['email'])[0],'_')?:'owner';$base=$username;$suffix=1;
                while(User::where('username',$username)->exists())$username=$base.'_'.$suffix++;
                return User::create(['shop_id'=>$shop->id,'name'=>$data['owner_name'],'email'=>$data['email'],'username'=>$username,
                    'password'=>Hash::make($data['password']),'role'=>'owner','is_active'=>true]);
            });
            if ($originalEnv !== null) {
                $databaseEnvPath=str_replace('\\','/',$databasePath);
                $updatedEnv=preg_match('/^\s*#?\s*DB_DATABASE\s*=.*$/m',$originalEnv)
                    ? preg_replace('/^\s*#?\s*DB_DATABASE\s*=.*$/m','DB_DATABASE='.$databaseEnvPath,$originalEnv,1)
                    : rtrim($originalEnv)."\nDB_DATABASE=".$databaseEnvPath."\n";
                if (File::put($envPath,$updatedEnv) === false) {
                    throw new \RuntimeException('Could not save the local shop configuration.');
                }
                Artisan::call('config:clear');
            }
        } catch (\Throwable $exception) {
            DB::purge('sqlite');
            config(['database.connections.sqlite.database'=>$originalDatabase]);
            if ($originalEnv !== null) File::put($envPath,$originalEnv);
            if (!$databaseExisted) File::delete($databasePath);
            report($exception);
            return back()->withErrors(['shop_name'=>'Shop setup could not be completed. Please restart the app and try again.'])->withInput();
        }
        Auth::login($user); $request->session()->regenerate(); return redirect()->route('dashboard')->with('success', 'Your shop is ready. You are signed in.');
    }
    public function logout(Request $request) { Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect()->route('login'); }
}
