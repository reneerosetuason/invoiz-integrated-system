<?php

namespace App\Http\Controllers;

use App\Models\{EscrowRemittance,ReturnAllocation,User,HubTransportDriver};
use App\Services\ReturnSettlementService;
use App\Support\{RemittanceFeed,RemittanceEntry};
use Illuminate\Http\Request;

class IncomingRemittanceController extends Controller
{
    private function actor(Request $request)
    {
        $actor=$request->user();
        abort_unless(($actor instanceof User && $actor->isActive()) || ($actor instanceof HubTransportDriver && $actor->status!=='inactive'
            && (!$actor->user || $actor->user->isActive())),403);
        return $actor;
    }
    public function index(Request $request)
    {
        $actor=$this->actor($request);
        $request->validate(['status'=>'nullable|in:held_in_escrow,pending_remittance,remitted,accepted,voided','per_page'=>'nullable|integer|min:1|max:100']);
        $all=RemittanceFeed::forRecipient($actor);$totals=RemittanceFeed::totals($all);
        $filtered=$request->filled('status')?$all->where('status',$request->input('status'))->values():$all;
        $rows=RemittanceFeed::paginate($filtered,(int)$request->input('per_page',20));
        if($request->expectsJson())return response()->json([
            'remittances'=>$rows->getCollection()->map(fn($entry)=>$entry->api()),'summary'=>$totals,
            'pagination'=>['current_page'=>$rows->currentPage(),'last_page'=>$rows->lastPage(),'total'=>$rows->total()],
        ])->header('Cache-Control','private, no-store');
        if($actor instanceof User && $actor->isAdmin())return redirect('/admin/payments#escrow-remittances');
        if($actor instanceof User && $actor->isLogisticsStaff())return redirect()->route('remittances.hub-payouts.index');
        if($actor instanceof User && $actor->isSeller())return redirect()->route('seller.remittances.index');
        $layout=$actor instanceof User && $actor->isBuyer()?'buyer.layouts.website':'logistics.layouts.app';
        return response()->view('shared.remittances.incoming',compact('rows','totals','actor','layout'))->header('Cache-Control','private, no-store');
    }
    public function accept(Request $request,string $source,int $id)
    {
        $actor=$this->actor($request);
        $request->validate(['confirm_received'=>'required|accepted']);
        if($source==='return'){
            $row=ReturnAllocation::findOrFail($id);
            abort_unless($row->isDeliveryService(),404);
            ReturnSettlementService::payout($row,$actor,'accept',[]);
            $entry=RemittanceEntry::returned($row->fresh());
        }elseif($source==='order'){
            $row=EscrowRemittance::findOrFail($id);
            $recipient=$actor;
            if($actor instanceof User && $row->recipient_role===EscrowRemittance::ROLE_INTER_HUB_DRIVER
                && (int)$actor->hubTransportDriver?->id===(int)$row->hub_transport_driver_id)$recipient=$actor->hubTransportDriver;
            $accepted=$row->accept($recipient);
            if(!$accepted && $row->fresh()->status!=='accepted')throw \Illuminate\Validation\ValidationException::withMessages(['remittance'=>'Wait for admin to release this payment before confirming receipt.']);
            $entry=RemittanceEntry::order($row->fresh());
        }else abort(404);
        return $request->expectsJson()?response()->json(['message'=>'Receipt confirmed. Earnings updated.','remittance'=>$entry->api()])
            :back()->with('success','Receipt confirmed. Earnings updated.');
    }
}
