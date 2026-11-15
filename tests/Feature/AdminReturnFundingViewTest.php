<?php

namespace Tests\Feature;

use App\Models\ReturnFundPayment;
use App\Models\ReturnSettlement;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class AdminReturnFundingViewTest extends TestCase
{
    private function receipt(int $id, ?ReturnSettlement $settlement): ReturnFundPayment
    {
        $receipt = (new ReturnFundPayment())->forceFill(['id' => $id, 'amount_cents' => 12500, 'purpose' => 'return_budget']);
        $receipt->setRelation('settlement', $settlement);
        return $receipt;
    }

    private function renderReceipts(array $receipts): string
    {
        return view('admin.partials.return-escrow-funding', [
            'returnFundingReceipts' => new LengthAwarePaginator($receipts, count($receipts), 10),
        ])->render();
    }

    public function test_missing_settlement_is_visible_for_review_without_payment_actions(): void
    {
        $html = $this->renderReceipts([$this->receipt(1, null)]);
        $this->assertStringContainsString('Receipt #1', $html);
        $this->assertStringContainsString('linked return settlement is unavailable', $html);
        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringNotContainsString('Confirm escrow received', $html);
    }

    public function test_valid_receipt_keeps_existing_verification_controls_beside_missing_link(): void
    {
        $settlement = (new ReturnSettlement())->forceFill(['id' => 3, 'return_request_id' => 7]);
        $html = $this->renderReceipts([$this->receipt(1, null), $this->receipt(2, $settlement)]);
        $this->assertStringContainsString('Return #7', $html);
        $this->assertStringContainsString('View payment receipt', $html);
        $this->assertStringContainsString('Confirm escrow received', $html);
        $this->assertStringContainsString('Reject an unmatched receipt', $html);
        $this->assertSame(2, substr_count($html, '<form'));
    }
}
