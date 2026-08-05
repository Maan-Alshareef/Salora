<?php
namespace App\Http\Controllers\Api\Owner;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\Review;
use Illuminate\Http\Request;
class OwnerReviewController extends BaseApiController
{
    public function index(Request $r){ return $this->ok(Review::with(['customer:id,name','venue'])->whereHas('venue',fn($q)=>$q->where('owner_id',$r->user()->id))->latest()->get()); }
    public function reply(Request $r, Review $review){ if($review->venue->owner_id!==$r->user()->id)return $this->fail('Forbidden.',403); $data=$r->validate(['reply'=>'required|string|max:1000']); $review->update(['owner_reply'=>$data['reply']]); return $this->ok($review,'Reply saved.'); }
}
