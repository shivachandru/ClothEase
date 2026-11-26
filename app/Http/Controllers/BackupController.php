<?php
namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BackupController extends Controller
{
    public function index(BackupService $backups)
    {
        return view('backups.index',['backups'=>$backups->list(),'supported'=>$backups->supported()]);
    }
    public function create(Request $request,BackupService $backups,AuditService $audit)
    {
        abort_unless($backups->supported(),422,'Manual file backups currently require a file-backed SQLite database.');
        try{$manifest=$backups->create();$audit->record($request->user(),'backup.created',$request->user()->shop,['name'=>$manifest['name'],'size_bytes'=>$manifest['size_bytes']]);}
        catch(RuntimeException $e){return back()->withErrors(['backup'=>$e->getMessage()]);}
        return response()->download(storage_path('app/private/backups'.DIRECTORY_SEPARATOR.$manifest['name']),$manifest['name'],['Content-Type'=>'application/vnd.sqlite3']);
    }
    public function upload(Request $request,BackupService $backups,AuditService $audit)
    {
        abort_unless($backups->supported(),422,'Backup upload currently requires file-backed SQLite.');
        $data=$request->validate(['backup_file'=>['required','file','max:102400']]);
        $extension=strtolower($data['backup_file']->getClientOriginalExtension());
        if(!in_array($extension,['sqlite','db','sqlite3'],true))return back()->withErrors(['backup_file'=>'Upload a SQLite backup file (.sqlite, .sqlite3 or .db).']);
        try{$manifest=$backups->import($data['backup_file']->getPathname());$audit->record($request->user(),'backup.uploaded',$request->user()->shop,['name'=>$manifest['name'],'size_bytes'=>$manifest['size_bytes']]);}
        catch(RuntimeException $e){return back()->withErrors(['backup_file'=>$e->getMessage()]);}
        return redirect()->route('backups.index')->with('success','Backup uploaded and verified: '.$manifest['name'].'. It is available to restore below.');
    }
    public function restore(Request $request,BackupService $backups,AuditService $audit)
    {
        abort_unless($backups->supported(),422,'Restore currently requires a file-backed SQLite database.');
        $data=$request->validate(['backup_name'=>['required','string','max:100'],'confirmation'=>['required','in:RESTORE']]);
        try{
            $result=$backups->restore($data['backup_name']);
            $currentUser=User::where('shop_id',$request->user()->shop_id)->whereKey($request->user()->id)->first();
            if($currentUser)$audit->record($currentUser,'backup.restored',$currentUser->shop,['name'=>$result['restored']['name'],'pre_restore'=>$result['pre_restore']['name']]);
        }catch(RuntimeException $e){return back()->withErrors(['backup'=>$e->getMessage()]);}
        return redirect()->route('backups.index')->with('success','Backup restored. A pre-restore backup was also created: '.$result['pre_restore']['name']);
    }
}
