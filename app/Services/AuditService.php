<?php
namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    public function record(User $user,string $action,Model $subject,array $details=[]): AuditLog
    {
        return AuditLog::create(['shop_id'=>$user->shop_id,'user_id'=>$user->id,'action'=>$action,
            'subject_type'=>$subject::class,'subject_id'=>$subject->getKey(),'details'=>$details,'created_at'=>now()]);
    }
}
