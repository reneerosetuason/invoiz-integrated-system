<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Services\BuyerAccountDeletionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountDeletionController extends Controller
{
    public function __construct(private BuyerAccountDeletionService $service) {}

    public function show(Request $request)
    {
        $this->service->assertBuyer($request->user());
        $record = $request->user()->accountDeletionRequests()->latest('id')->first();
        $data = ['deletion_request' => $this->service->payload($record), 'reasons' => BuyerAccountDeletionService::REASONS,
            'retention_notice' => config('account_deletion.retention_notice'), 'server_time' => now()->toIso8601String()];

        return $request->is('api/*') ? response()->json($data) : view('buyer.account-deletion', $data);
    }

    public function store(Request $request)
    {
        $this->service->assertBuyer($request->user());
        $data = $request->validate([
            'reason' => ['required', Rule::in(BuyerAccountDeletionService::REASONS)],
            'additional_feedback' => ['nullable', 'required_if:reason,Other (please specify).', 'string', 'max:2000'],
            'current_password' => ['required', 'string', 'max:255'], 'confirm_deletion' => ['required', 'accepted'],
        ]);
        $record = $this->service->submit($request->user(), $data);

        return $request->is('api/*') ? response()->json(['message' => BuyerAccountDeletionService::MESSAGES['submitted'], 'deletion_request' => $this->service->payload($record)], 201)
            : redirect()->route('buyer.account-deletion')->with('success', BuyerAccountDeletionService::MESSAGES['submitted']);
    }

    public function verify(Request $request)
    {
        $this->service->assertBuyer($request->user());
        $data = $request->validate(['current_password' => 'required|string|max:255']);
        $this->service->verifyPassword($request->user(), $data['current_password']);

        return response()->json(['message' => 'Identity verified. Confirm the deletion request to continue.']);
    }

    public function cancel(Request $request)
    {
        $request->validate(['confirm_cancellation' => ['required', 'accepted']]);
        $record = $this->service->cancel($request->user());

        return $request->is('api/*') ? response()->json(['message' => BuyerAccountDeletionService::MESSAGES['cancelled'], 'deletion_request' => $this->service->payload($record)])
            : redirect()->route('buyer.account-deletion')->with('success', BuyerAccountDeletionService::MESSAGES['cancelled']);
    }
}
