@extends('layouts.app')
@section('title','Backups')
@section('section','Backups')
@section('content')
<div class="page-heading">
    <div><span class="eyebrow">DATA SAFETY</span><h1>Backups</h1><p>Create a verified backup download or upload a backup to restore later.</p></div>
    @if($supported)<form method="post" action="{{ route('backups.create') }}">@csrf<button class="button primary">＋ Create &amp; download backup</button></form>@endif
</div>
@unless($supported)<div class="alert error">Manual snapshot and restore currently support file-backed SQLite only. The MySQL/MariaDB backup path is not implemented yet.</div>@endunless
<div class="alert"><b>Restore warning:</b> Restoring replaces the current database with the selected snapshot. ClothEase creates a fresh pre-restore snapshot automatically.</div>
@if($supported)
<section class="panel"><div class="panel-heading"><div><h2>Upload a backup</h2><p>Upload a SQLite backup (.sqlite, .sqlite3 or .db). It will be verified and added to the restore list.</p></div></div>
    <form method="post" action="{{ route('backups.upload') }}" enctype="multipart/form-data" class="form-grid">@csrf
        <label class="wide">Backup file<input type="file" name="backup_file" accept=".sqlite,.sqlite3,.db" required></label>
        <div class="form-actions wide"><button class="button secondary">Upload and verify</button></div>
    </form>
</section>
@endif
<section class="panel"><div class="panel-heading"><div><h2>Local backup files</h2><p>Stored in a private application folder with SHA-256 and SQLite integrity checks.</p></div></div>
@if(empty($backups))
    <div class="empty-state"><div class="empty-icon">↥</div><b>No backups created yet</b><p>Create a snapshot before making important data changes.</p></div>
@else
    <div class="table-wrap"><table><thead><tr><th>CREATED</th><th>FILE</th><th>SIZE</th><th>VERIFICATION</th><th>RESTORE</th></tr></thead><tbody>
    @foreach($backups as $backup)<tr>
        <td>{{ \Illuminate\Support\Carbon::parse($backup['created_at'])->format('d M Y, h:i A') }}</td><td class="table-primary">{{ $backup['name'] }}</td><td>{{ number_format($backup['size_bytes']/1024,1) }} KB</td>
        <td><span class="status-badge {{ !($backup['valid']??false)?'warning':'' }}">{{ ($backup['valid']??false)?'Verified':'Invalid' }}</span></td>
        <td>@if($supported&&($backup['valid']??false))<form method="post" action="{{ route('backups.restore') }}" class="restore-form" onsubmit="return confirm('This replaces the current shop database. A pre-restore backup will be created first. Continue only if you intend to replace all current data.')">@csrf<input type="hidden" name="backup_name" value="{{ $backup['name'] }}"><input name="confirmation" placeholder="Type RESTORE" required autocomplete="off"><button class="button secondary">Restore</button></form>@endif</td>
    </tr>@endforeach
    </tbody></table></div>
@endif
</section>
@endsection
