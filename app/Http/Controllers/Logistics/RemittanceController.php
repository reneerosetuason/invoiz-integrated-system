<?php

namespace App\Http\Controllers\Logistics;

use App\Models\Remittance;
use App\Models\LogisticsCenter;
use App\Models\User;
use App\Support\Cod;
use App\Support\CsvExport;
use App\Support\LogisticsAccess;
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

        return view('logistics.remittances.index', [
            'remittances' => $remittances,
            'totals' => $totals,
            'status' => $status,
            'reconciliation' => $reconciliation,
        ]);
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
