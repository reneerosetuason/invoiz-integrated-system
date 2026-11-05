<?php

namespace App\Console\Commands;

use App\Models\Delivery;
use App\Services\ShipmentWeightService;
use Illuminate\Console\Command;

class RepairShipmentWeights extends Command
{
    protected $signature = 'shipments:repair-weights {--apply : Save missing weights; otherwise preview only} {--tracking= : Limit to a tracking number}';

    protected $description = 'Fill missing active shipment weights from complete inventory weights, preserving existing measurements.';

    public function handle(ShipmentWeightService $weights): int
    {
        $apply = (bool) $this->option('apply');
        $repaired = $unresolved = 0;
        Delivery::whereNull('weight')->whereNotNull('order_id')->whereNull('archived_at')
            ->whereNotIn('status', ['delivered', 'cancelled', 'returned'])
            ->when($this->option('tracking'), fn ($query, $tracking) => $query->where('tracking_number', $tracking))
            ->with('order.items')->chunkById(100, function ($deliveries) use ($weights, $apply, &$repaired, &$unresolved) {
                foreach ($deliveries as $delivery) {
                    $weight = $weights->fillMissing($delivery, $apply);
                    if ($weight === null) {
                        $unresolved++;
                        continue;
                    }
                    $repaired++;
                    $this->line($delivery->tracking_number.': '.number_format($weight, 2, '.', '').' kg');
                }
            });
        $this->info(($apply ? 'Saved' : 'Would save')." {$repaired} missing weights; {$unresolved} still need complete inventory weights or a logistics measurement.");

        return self::SUCCESS;
    }
}
