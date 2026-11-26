<?php
namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\CustomerPaymentService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers=Customer::where('shop_id',$request->user()->shop_id)->withSum('sales as outstanding_balance','balance_due')->withCount('sales')->orderBy('name')->paginate(20);
        return view('customers.index',compact('customers'));
    }
    public function export(Request $request)
    {
        $shopId=$request->user()->shop_id;
        return response()->streamDownload(function()use($shopId){
            $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
            fputcsv($out,['Customer name','Mobile','Address','Notes','Bills','Outstanding credit (INR)']);
            Customer::where('shop_id',$shopId)->withSum('sales as outstanding_balance','balance_due')->withCount('sales')->orderBy('name')->chunk(500,function($customers)use($out){
                foreach($customers as $customer){$safe=fn($value)=>preg_match('/^[=+@-]/',(string)$value)?"'".$value:$value;fputcsv($out,[$safe($customer->name),$safe($customer->mobile??''),$safe($customer->address??''),$safe($customer->notes??''),$customer->sales_count,number_format((float)($customer->outstanding_balance??0),2,'.','')]);}
            });fclose($out);
        },'customers-'.now()->format('Ymd-His').'.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }
    public function store(Request $request)
    {
        $data=$request->validate(['name'=>['required','string','max:150'],'mobile'=>['nullable','string','max:20',Rule::unique('customers','mobile')->where('shop_id',$request->user()->shop_id)],
            'address'=>['nullable','string','max:1000'],'notes'=>['nullable','string','max:1000']]);
        Customer::create($data+['shop_id'=>$request->user()->shop_id]);
        return redirect()->route('customers.index')->with('success','Customer added.');
    }
    public function show(Request $request,Customer $customer)
    {
        abort_unless($customer->shop_id===$request->user()->shop_id,404);
        $sales=$customer->sales()->with('items','payments')->latest('sold_at')->paginate(15);
        $outstanding=(float)$customer->sales()->sum('balance_due');
        $lifetimeSales=(float)$customer->sales()->sum('total')-(float)\DB::table('sale_returns')->join('sales','sales.id','=','sale_returns.sale_id')->where('sales.customer_id',$customer->id)->sum('sale_returns.refund_total');
        $payments=$customer->payments()->with('sale')->latest('paid_at')->limit(20)->get();
        return view('customers.show',compact('customer','sales','outstanding','lifetimeSales','payments'));
    }
    public function search(Request $request)
    {
        $q=trim($request->query('q',''));if(strlen($q)<1)return response()->json([]);
        return Customer::where('shop_id',$request->user()->shop_id)->withSum('sales as balance','balance_due')->where(fn($query)=>$query->where('name','like',"$q%")->orWhere('mobile','like',"$q%"))->orderBy('name')->limit(8)->get()->map(fn($c)=>['id'=>$c->id,'name'=>$c->name,'mobile'=>$c->mobile,'balance'=>(float)$c->balance]);
    }
    public function collect(Request $request,Customer $customer,CustomerPaymentService $service,AuditService $audit)
    {
        abort_unless($customer->shop_id===$request->user()->shop_id,404);
        $data=$request->validate(['amount'=>['required','numeric','gt:0'],'method'=>['required','in:cash,upi,card,other'],'reference'=>['nullable','string','max:100']]);
        $service->collect($customer,$request->user(),(float)$data['amount'],$data['method'],$data['reference']??null,$audit);
        return redirect()->route('customers.show',$customer)->with('success','Customer payment recorded against their oldest outstanding bills.');
    }
}
