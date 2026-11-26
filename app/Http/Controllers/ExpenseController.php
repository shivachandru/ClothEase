<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Expense;
use App\Services\AuditService;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $expenses=DB::table('expenses')->leftJoin('users','users.id','=','expenses.user_id')->where('expenses.shop_id',$request->user()->shop_id)->select('expenses.*','users.name as recorded_by')->orderByDesc('expenses.expense_date')->orderByDesc('expenses.id')->paginate(20);
        $monthTotal=DB::table('expenses')->where('shop_id',$request->user()->shop_id)->whereMonth('expense_date',now()->month)->whereYear('expense_date',now()->year)->sum('amount');
        return view('expenses.index',compact('expenses','monthTotal'));
    }
    public function store(Request $request,AuditService $audit)
    {
        $data=$request->validate(['category'=>['required','in:Rent,Electricity,Salary,Transport,Packaging,Maintenance,Other'],
            'amount'=>['required','numeric','gt:0','max:99999999'],'expense_date'=>['required','date','before_or_equal:today'],
            'payment_method'=>['required','in:cash,upi,card,other'],'description'=>['nullable','string','max:250']]);
        DB::transaction(function()use($request,$data,$audit){
            $expense=Expense::create(['shop_id'=>$request->user()->shop_id,'user_id'=>$request->user()->id,
                'category'=>$data['category'],'amount'=>$data['amount'],'expense_date'=>$data['expense_date'],
                'payment_method'=>$data['payment_method'],'description'=>$data['description']??null]);
            $audit->record($request->user(),'expense.recorded',$expense,['category'=>$expense->category,'amount'=>$expense->amount]);
        });
        return redirect()->route('expenses.index')->with('success','Expense recorded.');
    }
}
