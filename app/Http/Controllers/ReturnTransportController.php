<?php
namespace App\Http\Controllers;

use App\Models\{HubTransportDriver,User,Order,ReturnRequest,ReturnWorkflow};
use App\Services\ReturnSettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Validation\ValidationException;

class ReturnTransportController extends Controller
{
    private function driver(Request $request): HubTransportDriver
    {
        $actor=$request->user();
        $driver=$actor instanceof HubTransportDriver?$actor:($actor instanceof User?$actor->hubTransportDriver:null);
        abort_unless($driver && $driver->status!=='inactive' && $driver->company?->status==='active' && (!$driver->user || $driver->user->isActive()),403);
        return $driver;
    }
    public function index(Request $request)
    {
        $driver=$this->driver($request);
        $cases=ReturnRequest::with(['item','workflow.center','workflow.destinationCenter'])->where('status','approved')
            ->whereHas('workflow',fn($q)=>$q->where('driver_id',$driver->id)->where('stage','in_transfer'))->get();
        return response()->json(['returns'=>$cases->map(fn($case)=>[
            'id'=>$case->id,'order_id'=>$case->order_id,'quantity'=>$case->quantity,'product'=>$case->item?->product_name,
            'stage'=>$case->workflow->stage,'center_id'=>$case->workflow->center_id,
            'origin_hub'=>$case->workflow->center?->name,'origin_address'=>$case->workflow->center?->address,
            'destination_hub'=>$case->workflow->destinationCenter?->name,'destination_address'=>$case->workflow->destinationCenter?->address,
            'driver_accepted_at'=>$case->workflow->driver_accepted_at,'driver_arrived_at'=>$case->workflow->driver_arrived_at,
            'transport_reference'=>$case->workflow->transport_reference,'amount_to_collect'=>0,
        ])])->header('Cache-Control','private, no-store');
    }
    public function update(Request $request,ReturnRequest $returnRequest)
    {
        $driver=$this->driver($request);
        $data=$request->validate(['action'=>'required|in:accept,arrive','confirm'=>'required|accepted',
            'proof'=>'required_if:action,arrive|file|mimes:jpg,jpeg,png,webp|max:5120']);
        $path=null;
        $used=false;
        try{
            if($request->hasFile('proof'))$path=$request->file('proof')->store('return-handover-proofs','private');
            DB::transaction(function()use($returnRequest,$driver,$data,$path,&$used){
                Order::lockForUpdate()->findOrFail($returnRequest->order_id);
                $case=ReturnRequest::lockForUpdate()->findOrFail($returnRequest->id);
                $flow=ReturnWorkflow::where('return_request_id',$case->id)->lockForUpdate()->firstOrFail();
                abort_unless((int)$flow->driver_id===(int)$driver->id,403);
                if($case->status!=='approved' || $flow->stage!=='in_transfer')throw ValidationException::withMessages(['return'=>'This return transfer is no longer assigned to you. Refresh the job list.']);
                ReturnSettlementService::assertFunded($case);
                $field=$data['action']==='accept'?'driver_accepted_at':'driver_arrived_at';
                if($flow->$field)return;
                if($data['action']==='arrive' && !$flow->driver_accepted_at)throw ValidationException::withMessages(['return'=>'Accept this return transfer before recording arrival.']);
                $events=$flow->events??[];
                $events[]=['action'=>$data['action']==='accept'?'driver_accept':'driver_arrived','actor_id'=>$driver->id,'actor_type'=>'hub_transport_driver',
                    'at'=>now()->toIso8601String(),'proof_path'=>$path,'from'=>$flow->stage,'to'=>$flow->stage];
                $flow->update([$field=>now(),'events'=>$events]);
                $used=true;
            },3);
            if($path && !$used)Storage::disk('private')->delete($path);
        }catch(\Throwable $e){if($path)Storage::disk('private')->delete($path);throw $e;}
        return response()->json(['message'=>'Return transport updated. The destination hub must independently confirm parcel receipt.']);
    }
}
