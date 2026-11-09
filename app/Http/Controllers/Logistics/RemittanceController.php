<?php

namespace App\Http\Controllers\Logistics;

use App\Models\EscrowRemittance;
use App\Models\LogisticsCenter;
use App\Models\Remittance;
use App\Models\User;
use App\Support\Cod;
use App\Support\CsvExport;
use App\Support\LogisticsAccess;
use App\Support\Remittances;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * COD handoff oversight and scoped receipt confirmation for hub payouts.
 */
class RemittanceController extends Controller
{
    public function hubPayouts(Request $request): View
    {
        $request->validate(['status' => ['nullable', 'in:held_in_escrow,pending_remittance,remitted,accepted,voided']]);
        $base = $this->hubPayoutQuery($request->user());
        $all=\App\Support\RemittanceFeed::combine($base->with(['order','delivery','logisticsCenter','hubTransfer'])->get(),
            \App\Support\RemittanceFeed::returns($request->user(),LogisticsAccess::isGlobal($request->user())));
        $totals=\App\Support\RemittanceFeed::totals($all);
        $filtered=$request->filled('status')?$all->where('status',$request->input('status'))->values():$all;
        $payouts=\App\Support\RemittanceFeed::paginate($filtered);

        return view('logistics.remittances.hub-payouts', compact('payouts', 'totals'));
    }

    private function hubPayoutQuery(User $user): Builder
    {
        $query = EscrowRemittance::whereIn('recipient_role', [
            EscrowRemittance::ROLE_ORIGIN_HUB,
            EscrowRemittance::ROLE_DESTINATION_HUB,
        ])->where('amount', '>', 0);
        if (LogisticsAccess::isGlobal($user)) {
            return $query;
        }
        $companyId = LogisticsAccess::companyId($user);
        $centerId = LogisticsAccess::centerId($user);
        if ($centerId === 0 || (! $companyId && $centerId === null)) {
            return $query->whereRaw('1 = 0');
        }
        if ($companyId > 0) {
            $query->whereIn('logistics_center_id', LogisticsCenter::select('id')->where('logistics_company_id', $companyId));
        }
        if ($centerId !== null) {
            $query->where('logistics_center_id', $centerId);
        }

        return $query;
    }

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
                    $remittance->order?->order_number ?? ($remittance->order_id ? '#'.$remittance->order_id : ''),
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
            'invoiz-logistics-cod-remittances-'.now()->format('Y-m-d').'.csv',
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
                'center_scope' => LogisticsAccess::isGlobal($user)
                    ? 'global'
                    : (LogisticsAccess::centerId($user) ?? 'company'),
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

        $hubEscrowQuery = $this->hubPayoutQuery($user);

        $hubAcceptedTotal = (float) (clone $hubEscrowQuery)->where('status', EscrowRemittance::STATUS_ACCEPTED)->sum('amount');
        $hubAwaitingAcceptTotal = (float) (clone $hubEscrowQuery)->where('status', EscrowRemittance::STATUS_REMITTED)->sum('amount');
        $hubPendingAdminTotal = (float) (clone $hubEscrowQuery)->where('status', EscrowRemittance::STATUS_PENDING_REMITTANCE)->sum('amount');
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

    public function confirmReceived(Request $request, Remittance $remittance): RedirectResponse
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
        Remittances::confirmByLogistics($remittance, $user, $reference);

        return back()->with('status', 'COD cash remittance of ₱'.number_format((float) $remittance->amount, 2).' confirmed received at hub.');
    }

    public function acceptHubPayout(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $escrow = $this->hubPayoutQuery($user)->findOrFail($id);
        abort_unless($escrow->canAcceptHubPayout($user), 403, 'Only the receiving hub or its company manager can confirm this payout.');
        $request->validate(['confirm_received' => ['required', 'accepted'], 'note' => ['nullable', 'string', 'max:1000']]);

        if ($escrow->status === EscrowRemittance::STATUS_ACCEPTED) {
            return back()->with('info', 'This hub income is already accepted.');
        }

        if ($escrow->status !== EscrowRemittance::STATUS_REMITTED) {
            return back()->with('error', 'This remittance cannot be accepted yet because it has not been released by Admin (Status: '.$escrow->status.').');
        }

        if (! $escrow->accept($user, $request->input('note'))) {
            return back()->with('info', 'This payout was already processed. Refresh the remittances page.');
        }

        return back()->with('success', 'Hub income of ₱'.number_format((float) $escrow->amount, 2).' accepted and officially recorded in hub earnings.');
    }

    private function baseQuery(User $user): Builder
    {
        $query = Remittance::query();

        // Global accounts are unrestricted. Everyone else is company-scoped;
        // staff with an assigned center are narrowed to that hub. Accounts
        // without a valid company/center assignment see no rows.
        if (LogisticsAccess::isGlobal($user)) {
            return $query;
        }

        $companyId = LogisticsAccess::companyId($user);
        $centerId = LogisticsAccess::centerId($user);

        if ($centerId === 0 || $companyId === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($companyId > 0) {
            $companyCenters = LogisticsCenter::query()
                ->select('id')
                ->where('logistics_company_id', $companyId);

            return $query->where(function ($scope) use ($centerId, $companyCenters) {
                $scope->whereHas('delivery', function ($delivery) use ($centerId, $companyCenters) {
                    $delivery->where(function ($companyScope) use ($companyCenters) {
                        $companyScope->whereIn('center_id', $companyCenters)
                            ->orWhereIn('destination_center_id', $companyCenters);
                    });

                    if ($centerId !== null && $centerId > 0) {
                        $delivery->where(function ($centerScope) use ($centerId) {
                            $centerScope->where('center_id', $centerId)
                                ->orWhere('destination_center_id', $centerId);
                        });
                    }
                })->orWhereHas('rider', function ($rider) use ($centerId, $companyCenters) {
                    $rider->whereIn('center_id', $companyCenters);

                    if ($centerId !== null && $centerId > 0) {
                        $rider->where('center_id', $centerId);
                    }
                });
            });
        }

        // Staff at a legacy hub without a company are narrowed to that hub.
        if ($centerId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($scope) use ($centerId) {
            $scope->whereHas('delivery', function ($delivery) use ($centerId) {
                $delivery->where('center_id', $centerId)
                    ->orWhere('destination_center_id', $centerId);
            })->orWhereHas('rider', fn ($rider) => $rider->where('center_id', $centerId));
        });
    }
}
