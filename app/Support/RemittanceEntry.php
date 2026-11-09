<?php

namespace App\Support;

use App\Models\{EscrowRemittance,ReturnAllocation};
use Illuminate\Support\Fluent;

/** Presentation of one existing ledger record, with an explicit source identity. */
final class RemittanceEntry extends Fluent
{
    private EscrowRemittance|ReturnAllocation $record;

    public static function order(EscrowRemittance $row): self
    {
        $entry=new self($row->getAttributes()+[
            'source'=>'order','key'=>'order-'.$row->id,'return_id'=>null,
            'order'=>$row->order,'delivery'=>$row->delivery,'logisticsCenter'=>$row->logisticsCenter,
            'hubTransfer'=>$row->hubTransfer,
        ]);
        $entry->amount=(float)$row->amount;$entry->remitted_at=$row->remitted_at;$entry->created_at=$row->created_at;
        $entry->record=$row;
        return $entry;
    }

    public static function returned(ReturnAllocation $row): self
    {
        $status=match($row->status){
            'eligible'=>$row->readyForRelease()?'pending_remittance':'held_in_escrow',
            'remitted','accepted'=>$row->status,
            'cancelled','voided'=>'voided',default=>'held_in_escrow',
        };
        $entry=new self([
            'id'=>$row->id,'source'=>'return','key'=>'return-'.$row->id,
            'return_id'=>$row->settlement?->return_request_id,'order_id'=>$row->settlement?->order_id,
            'order'=>$row->settlement?->order,'delivery'=>null,'logisticsCenter'=>$row->center,'hubTransfer'=>null,
            'recipient_role'=>$row->role,'recipient_name'=>$row->recipientUser?->name??$row->driver?->name??$row->center?->name??'Awaiting assignment',
            'amount'=>$row->amount_cents/100,'status'=>$status,'bank_reference'=>$row->reference,
            'remitted_at'=>$row->remitted_at,'created_at'=>$row->created_at,'accepted_at'=>$row->accepted_at,
            'rule_applied'=>'Completed return service · Return #'.$row->settlement?->return_request_id,
            'notes'=>$row->note,'has_proof'=>(bool)$row->proof_path,
            'hold_reason'=>$status==='held_in_escrow'?($row->status==='reserved'?'Awaiting completed service':
                (!$row->settlement?->funded_at?'Awaiting escrow receipt confirmation':
                (!$row->hasAssignedRecipient()?'Awaiting worker or hub assignment':'Review available escrow funds'))):null,
        ]);
        $entry->record=$row;
        return $entry;
    }

    public function roleLabel(): string
    {
        if($this->source==='order')return $this->record->roleLabel();
        return match($this->recipient_role){
            'collection_rider'=>'Return pickup rider','collection_hub'=>'Return origin hub',
            'inter_hub_driver'=>'Return inter-hub driver','seller_hub'=>'Return destination hub',
            'seller_delivery_rider'=>'Return delivery rider','replacement_delivery_rider'=>'Replacement delivery rider',
            default=>'Return service',
        };
    }
    public function settlementModeLabel(): string
    { return $this->source==='order'?$this->record->settlementModeLabel():'Escrow release · recipient confirms receipt'; }
    public function canAcceptHubPayout($user): bool
    { return $this->source==='order'?$this->record->canAcceptHubPayout($user):$this->record->canAccept($user); }
    public function api(): array
    {
        return [
            'id'=>$this->id,'source'=>$this->source,'key'=>$this->key,'return_id'=>$this->return_id,
            'order_number'=>$this->order?->order_number??'#'.$this->order_id,'role'=>$this->recipient_role,'role_label'=>$this->roleLabel(),
            'amount'=>(float)$this->amount,'status'=>$this->status,'can_accept'=>$this->status==='remitted',
            'bank_reference'=>$this->bank_reference,'remitted_at'=>$this->remitted_at?->toIso8601String(),
            'tracking_number'=>$this->delivery?->tracking_number,
            'transfer_reference'=>$this->source==='return'?'Return #'.$this->return_id:$this->hubTransfer?->reference,
            'rule_applied'=>$this->rule_applied,'has_proof'=>$this->source==='return' && $this->has_proof,
            'hold_reason'=>$this->hold_reason,
        ];
    }
}
