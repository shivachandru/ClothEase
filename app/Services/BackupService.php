<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use PDO;

class BackupService
{
    public function supported(): bool
    {
        return config('database.default')==='sqlite' && $this->databasePath()!==':memory:';
    }

    public function create(?string $sourcePath=null): array
    {
        $source=$sourcePath ? $this->resolve($sourcePath) : $this->databasePath();
        if(!is_file($source))throw new RuntimeException('A file-backed SQLite database is required for this backup operation.');
        $directory=storage_path('app/private/backups');$this->ensureDirectory($directory);
        $name='clothease-backup-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(3)).'.sqlite';$target=$directory.DIRECTORY_SEPARATOR.$name;
        $pdo=new PDO('sqlite:'.$source,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$pdo->exec('PRAGMA busy_timeout=5000');
        $pdo->exec('VACUUM INTO '.$pdo->quote($target));unset($pdo);
        $this->verifyFile($target);
        $manifest=['name'=>$name,'created_at'=>now()->toIso8601String(),'size_bytes'=>filesize($target),'sha256'=>hash_file('sha256',$target)];
        file_put_contents($target.'.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX);@chmod($target,0600);@chmod($target.'.json',0600);
        return $manifest;
    }

    public function import(string $sourcePath): array
    {
        if(!is_file($sourcePath))throw new RuntimeException('The uploaded backup file could not be read.');
        $directory=storage_path('app/private/backups');$this->ensureDirectory($directory);
        $name='clothease-backup-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(3)).'.sqlite';$target=$directory.DIRECTORY_SEPARATOR.$name;
        if(!copy($sourcePath,$target))throw new RuntimeException('Could not copy the uploaded backup into private storage.');
        try{$this->verifyFile($target);}catch(RuntimeException $e){@unlink($target);throw new RuntimeException('The uploaded file is not a valid, healthy SQLite backup.');}
        $manifest=['name'=>$name,'created_at'=>now()->toIso8601String(),'size_bytes'=>filesize($target),'sha256'=>hash_file('sha256',$target)];
        file_put_contents($target.'.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX);@chmod($target,0600);@chmod($target.'.json',0600);
        return $manifest;
    }

    public function list(): array
    {
        $directory=storage_path('app/private/backups');if(!is_dir($directory))return [];
        $items=[];foreach(glob($directory.DIRECTORY_SEPARATOR.'clothease-backup-*.sqlite')?:[] as $path){$name=basename($path);try{$items[]=$this->verify($name);}catch(RuntimeException $e){$items[]=['name'=>$name,'created_at'=>date(DATE_ATOM,filemtime($path)),'size_bytes'=>filesize($path),'valid'=>false,'error'=>'Integrity verification failed'];}}
        usort($items,fn($a,$b)=>strcmp($b['created_at'],$a['created_at']));return $items;
    }

    public function verify(string $name): array
    {
        if(!preg_match('/^clothease-backup-\d{8}-\d{6}-[a-f0-9]{6}\.sqlite$/',$name))throw new RuntimeException('Invalid backup name.');
        $path=storage_path('app/private/backups'.DIRECTORY_SEPARATOR.$name);$manifestPath=$path.'.json';
        if(!is_file($path)||!is_file($manifestPath))throw new RuntimeException('Backup or verification manifest is missing.');
        $manifest=json_decode(file_get_contents($manifestPath),true,512,JSON_THROW_ON_ERROR);
        if(($manifest['name']??null)!==$name||!hash_equals((string)($manifest['sha256']??''),hash_file('sha256',$path))||(int)($manifest['size_bytes']??-1)!==filesize($path))throw new RuntimeException('Backup checksum does not match its manifest.');
        $this->verifyFile($path);return $manifest+['valid'=>true];
    }

    public function restore(string $name,?string $targetPath=null): array
    {
        $backup=$this->verify($name);$target=$targetPath?$this->resolve($targetPath):$this->databasePath();
        if($target===':memory:'||!is_file($target))throw new RuntimeException('Restore requires an existing file-backed SQLite database.');
        $preRestore=$this->create($target);$source=storage_path('app/private/backups'.DIRECTORY_SEPARATOR.$name);
        $temporary=$target.'.restore-'.bin2hex(random_bytes(4));$old=$target.'.previous-'.bin2hex(random_bytes(4));
        if(!copy($source,$temporary))throw new RuntimeException('Could not prepare the selected backup.');
        $this->verifyFile($temporary);
        $defaultRestore=$targetPath===null;
        if($defaultRestore)DB::purge('sqlite');
        if(!rename($target,$old)){@unlink($temporary);throw new RuntimeException('Could not safely close the current database file.');}
        if(!rename($temporary,$target)){@rename($old,$target);throw new RuntimeException('Restore failed; the current database file was put back.');}
        if($defaultRestore)DB::purge('sqlite');
        @unlink($old);
        return ['restored'=>$backup,'pre_restore'=>$preRestore];
    }

    private function databasePath(): string
    {
        $path=(string)config('database.connections.sqlite.database');return $this->resolve($path);
    }
    private function resolve(string $path): string
    {
        if($path===':memory:')return $path;
        return str_starts_with($path,DIRECTORY_SEPARATOR)||preg_match('/^[A-Za-z]:[\\\\\/]/',$path)?$path:base_path($path);
    }
    private function ensureDirectory(string $path): void
    {
        if(!is_dir($path)&&!mkdir($path,0700,true)&&!is_dir($path))throw new RuntimeException('Could not create the private backup folder.');
        @chmod($path,0700);
    }
    private function verifyFile(string $path): void
    {
        $pdo=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $result=$pdo->query('PRAGMA integrity_check')->fetchColumn();unset($pdo);
        if($result!=='ok')throw new RuntimeException('SQLite reported that this backup is not healthy.');
    }
}
