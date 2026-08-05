<?php
namespace App\Http\Controllers\Api;
use App\Models\PaymentRefund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
class RefundProofFileController extends BaseApiController
{
 public function show(Request $request,PaymentRefund $refund){$u=$request->user();abort_unless(in_array($u->id,[(int)$refund->customer_id,(int)$refund->payee_id],true)||$u->role==='admin',403);abort_unless($refund->proof_path&&Storage::disk('local')->exists($refund->proof_path),404);return Storage::disk('local')->response($refund->proof_path);}
}
