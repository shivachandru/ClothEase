<?php
namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Services\SupplierPaymentService;
use App\Services\AuditService;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $suppliers=Supplier::where('shop_id',$request->user()->shop_id)->withCount('purchases')->orderBy('name')->paginate(20);
        return view('suppliers.index',compact('suppliers'));
    }
    public function store(Request $request)
    {
        $data=$request->validate(['name'=>['required','string','max:150'],'mobile'=>['nullable','string','max:20'],
            'email'=>['nullable','email','max:150'],'address'=>['nullable','string','max:1000'],'notes'=>['nullable','string','max:1000']]);
        Supplier::create($data+['shop_id'=>$request->user()->shop_id,'is_active'=>true]);
        return redirect()->route('suppliers.index')->with('success','Supplier added.');
    }
    public function show(Request $request,Supplier $supplier)
    {
        abort_unless($supplier->shop_id===$request->user()->shop_id,404);
        $purchases=$supplier->purchases()->with('items','payments')->latest('purchase_date')->paginate(15);
        $outstanding=(float)$supplier->purchases()->sum('balance_due');
        $payments=$supplier->payments()->with('purchase')->latest('paid_at')->limit(30)->get();
        return view('suppliers.show',compact('supplier','purchases','outstanding','payments'));
    }
    public function collect(Request $request,Supplier $supplier,SupplierPaymentService $service,AuditService $audit)
    {
        abort_unless($supplier->shop_id===$request->user()->shop_id,404);
        $data=$request->validate(['amount'=>['required','numeric','gt:0'],'method'=>['required','in:cash,upi,card,other'],'reference'=>['nullable','string','max:100']]);
        $service->collect($supplier,$request->user(),(float)$data['amount'],$data['method'],$data['reference']??null,$audit);
        return redirect()->route('suppliers.show',$supplier)->with('success','Supplier payment recorded against oldest outstanding invoices first.');
    }
}
