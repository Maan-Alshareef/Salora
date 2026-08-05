<?php
namespace App\Http\Controllers\Api;
use App\Models\PaymentProof;
use App\Models\PaymentRefund;
use App\Services\PaymentWorkflowService;
use App\Services\RefundWorkflowService;
use Illuminate\Http\Request;
class PaymentReviewController extends BaseApiController
{
    public function index(Request $request){return $this->ok(PaymentProof::with(['invoice.booking.venue','invoice.customer:id,name,email,phone','method','payoutAccount'])->whereHas('invoice',fn($q)=>$q->where('payee_id',$request->user()->id))->latest()->get());}
    public function approve(Request $request,PaymentProof $payment,PaymentWorkflowService $service){return $this->ok($service->review($request->user(),$payment,true),'تم قبول الدفعة وإصدار الإيصال.');}
    public function reject(Request $request,PaymentProof $payment,PaymentWorkflowService $service){$data=$request->validate(['reason'=>'required|string|max:700']);return $this->ok($service->review($request->user(),$payment,false,$data['reason']),'تم رفض الإيصال ويمكن للعميل إعادة الرفع.');}
    public function refunds(Request $request){return $this->ok(PaymentRefund::with(['invoice.customer:id,name,email,phone','invoice.booking.venue','method'])->where('payee_id',$request->user()->id)->latest()->get());}
    public function uploadRefundProof(Request $request,PaymentRefund $refund,RefundWorkflowService $service){$data=$request->validate(['payment_method_id'=>'required|exists:payment_methods,id','transaction_reference'=>'required|string|max:190','transferred_at'=>'required|date','proof'=>'required|image|mimes:jpeg,jpg,png,webp|max:5120']);return $this->ok($service->uploadTransfer($request->user(),$refund,$request->file('proof'),$data),'تم رفع إثبات إعادة المبلغ.');}
}
