<?php

namespace App\Http\Controllers\Logistics;

use App\Models\Remittance;
use App\Models\User;
use App\Support\Cod;
use App\Support\CsvExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only COD remittance oversight (rider -> seller cash handoffs).
 *
 * The seller remains the confirming party (see App\Support\Remittances), so
 * this surface never mutates a remittance. Its only job is to let logistics
 * staff see, for their own center, which riders still owe which sellers.
 */
class RemittanceController extends Controller
{
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $base = $this->baseQuery($user);

        $status = $request->query('status');
        if (in_array($status, ['pending', 'sent', 'confirmed'], true)) {
            $base->where('status', $status);
        }

        $rows = $base
            ->with([
                'delivery.transaction',
                'delivery.logisticsCenter',
                'delivery.destinationCenter',
                'rider',
                'order',
            ])
            ->latest()
            ->lazy(500)
            ->map(function (Remittance $remittance): array {
                $info = Cod::reconcile($remittance);
                $delivery = $remittance->delivery;

                return [
                    $remittance->order?->order_number ?? ($remittance->order_id ? '#' . $remittance->order_id : ''),
                    $delivery?->tracking_number,
                    $remittance->rider?->name,
                    $delivery?->logisticsCenter?->name,
                    $delivery?->destinationCenter?->name,
                    $remittance->status,
                    $info['expected'],
                    $info['received'],
                    $info['rider_fee'],
                    $info['seller_amount'],
                    $info['variance'],
                    $info['state'],
                    $remittance->sent_at?->format('Y-m-d H:i:s'),
                    $remittance->confirmed_at?->format('Y-m-d H:i:s'),
                    $remittance->created_at?->format('Y-m-d H:i:s'),
                ];
            });

        return CsvExport::stream(
            'invoiz-logistics-cod-remittances-' . now()->format('Y-m-d') . '.csv',
            [
                'Order Number',
                'Tracking Number',
                'Rider',
                'Origin Center',
                'Destination Center',
                'Remittance Status',
                'COD To Collect',
                'Cash Collected',
                'Rider Fee',
                'For Seller',
                'Variance',
                'Reconciliation State',
                'Sent At',
                'Confirmed At',
                'Created At',
            ],
            $rows,
            $user,
            'logistics.remittances',
            [
                'status' => in_array($status, ['pending', 'sent', 'confirmed'], true) ? $status : null,
                'center_scope' => ($user->isLogisticsAdmin() ? 'global' : (int) ($user->center_id ?? 0)),
            ]
        );
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        $base = $this->baseQuery($user);

        $status = $request->query('status');

        if (in_array($status, ['pending', 'sent', 'confirmed'], true)) {
            $base->where('status', $status);
        }

        $totals = [
            'pending' => (float) (clone $base)->where('status', 'pending')->sum('amount'),
            'sent' => (float) (clone $base)->where('status', 'sent')->sum('amount'),
            'confirmed' => (float) (clone $base)->where('status', 'confirmed')->sum('amount'),
            'total' => (float) (clone $base)->sum('amount'),
        ];

        $remittances = (clone $base)
            ->with(['delivery.transaction', 'rider', 'order'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $reconciliation = $remittances->getCollection()
            ->mapWithKeys(fn (Remittance $remittance) => [
                $remittance->id => Cod::reconcile($remittance),
            ]);

        $centerId = (int) ($user->center_id ?? 0);
        $orderIds = $remittances->getCollection()->pluck('order_id')->filter()->unique()->values();

        $hubEscrowQuery = \App\Models\EscrowRemittance::query()
            ->whereIn('recipient_role', [
                \App\Models\EscrowRemittance::ROLE_ORIGIN_HUB,
                \App\Models\EscrowRemittance::ROLE_DESTINATION_HUB
            ]);

        if (! $user->isLogisticsAdmin() && $centerId > 0) {
            $hubEscrowQuery->where('logistics_center_id', $centerId);
        }

        $hubAcceptedTotal = (float) (clone $hubEscrowQuery)->where('status', \App\Models\EscrowRemittance::STATUS_ACCEPTED)->sum('amount');
        $hubAwaitingAcceptTotal = (float) (clone $hubEscrowQuery)->where('status', \App\Models\EscrowRemittance::STATUS_REMITTED)->sum('amount');
        $hubPendingAdminTotal = (float) (clone $hubEscrowQuery)->where('status', \App\Models\EscrowRemittance::STATUS_PENDING_REMITTANCE)->sum('amount');
        $hubEarningsTotal = $hubAcceptedTotal; // Officially recorded in hub accounts once accepted
        $hubEscrows = (clone $hubEscrowQuery)->whereIn('order_id', $orderIds)->get()->groupBy('order_id');
        $hubPayoutsList = (clone $hubEscrowQuery)->with(['order', 'delivery'])->latest()->paginate(15, ['*'], 'payout_page')->withQueryString();

        return view('logistics.remittances.index', [
            'remittances' => $remittances,
            'totals' => $totals,
            'status' => $status,
            'reconciliation' => $reconciliation,
            'hubEarningsTotal' => $hubEarningsTotal,
            'hubAcceptedTotal' => $hubAcceptedTotal,
            'hubAwaitingAcceptTotal' => $hubAwaitingAcceptTotal,
            'hubPendingAdminTotal' => $hubPendingAdminTotal,
            'hubEscrows' => $hubEscrows,
            'hubPayoutsList' => $hubPayoutsList,
            'viewTab' => $request->query('tab', 'rider_handoffs'),
        ]);
    }

    public function confirmReceived(Request $request, Remittance $remittance): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        // Check center scope: admin can confirm any, staff can confirm their center's remittances
        if (! $user->isLogisticsAdmin()) {
            if (! $user->isLogisticsStaff()) {
                abort(403, 'Unauthorized to confirm remittances.');
            }
            $centerId = (int) ($user->center_id ?? 0);
            $delivery = $remittance->delivery;
            $riderCenterId = (int) ($remittance->rider?->center_id ?? 0);
            $deliveryCenterId = (int) ($delivery?->center_id ?? 0);
            $destCenterId = (int) ($delivery?->destination_center_id ?? 0);

            if ($centerId <= 0 || ($riderCenterId !== $centerId && $deliveryCenterId !== $centerId && $destCenterId !== $centerId)) {
                abort(403, 'You can only confirm remittances associated with your assigned logistics center.');
            }
        }

        if ($remittance->status === 'confirmed') {
            return back()->with('info', 'This remittance is already confirmed.');
        }

        $reference = $request->input('reference');
        \App\Support\Remittances::confirmByLogistics($remittance, $user, $reference);

        return back()->with('status', 'COD cash remittance of ₱' . number_format((float) $remittance->amount, 2) . ' confirmed received at hub.');
    }

    public function acceptHubPayout(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        $escrow = \App\Models\EscrowRemittance::findOrFail($id);

        if (! $user->isLogisticsAdmin()) {
            if (! $user->isLogisticsStaff()) {
                abort(403, 'Unauthorized.');
            }
            $centerId = (int) ($user->center_id ?? 0);
            if ($centerId <= 0 || (int) $escrow->logistics_center_id !== $centerId) {
                abort(403, 'Unauthorized to accept payout for another logistics center.');
            }
        }

        if ($escrow->status === \App\Models\EscrowRemittance::STATUS_ACCEPTED) {
            return back()->with('info', 'This hub income is already accepted.');
        }

        if ($escrow->status !== \App\Models\EscrowRemittance::STATUS_REMITTED) {
            return back()->with('error', 'This remittance cannot be accepted yet because it has not been released by Admin (Status: ' . $escrow->status . ').');
        }

        $escrow->accept($user, $request->input('note'));

        return back()->with('status', 'Hub income of ₱' . number_format((float) $escrow->amount, 2) . ' accepted and officially recorded in hub earnings.');
    }

    private function baseQuery(User $user): Builder
    {
        $query = Remittance::query();

        // Global logistics admins are unrestricted. Every other logistics
        // account is center-scoped, including accounts with no center: those
        // accounts must see no rows rather than every center's cash.
        if ($user->isLogisticsAdmin()) {
            return $query;
        }

        if (! $user->isLogisticsStaff()) {
            return $query->whereRaw('1 = 0');
        }

        $centerId = (int) ($user->center_id ?? 0);
        if ($centerId <= 0) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($scope) use ($centerId) {
            $scope->whereHas('delivery', function ($delivery) use ($centerId) {
                $delivery->where(function ($deliveryScope) use ($centerId) {
                    $deliveryScope->where('center_id', $centerId)
                        ->orWhere('destination_center_id', $centerId);
                });
            })->orWhereHas('rider', fn ($rider) => $rider->where('center_id', $centerId));
        });
    }
}
