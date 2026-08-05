<?php
namespace App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\PaymentProof;
use App\Models\PaymentRefund;
use Illuminate\Http\Request;
class AdminPaymentController extends BaseApiController
{
    public function index(Request $request){$q=PaymentProof::with(['invoice.booking.venue','invoice.customer:id,name,email,phone','invoice.payee:id,name,email,phone,role','reviewer:id,name','method'])->latest();if($request->filled('status'))$q->where('status',$request->query('status'));return $this->ok($q->get());}
    public function show(PaymentProof $payment){return $this->ok($payment->load(['invoice.booking.venue','invoice.customer:id,name,email,phone','invoice.payee:id,name,email,phone,role','invoice.refunds','reviewer:id,name','method','payoutAccount','transaction']));}
    public function refunds(Request $request){$q=PaymentRefund::with(['invoice.booking.venue','customer:id,name,email,phone','payee:id,name,email,phone,role','method'])->latest();if($request->filled('status'))$q->where('status',$request->query('status'));return $this->ok($q->get());}
    public function approve(){return $this->fail('الأدمن يراقب الدفعات فقط. قبول دفعة الصالة للمالك ودفعة الخدمة لمقدم الخدمة.',403);}
    public function reject(){return $this->fail('الأدمن يراقب الدفعات فقط ولا يرفضها.',403);}
}
