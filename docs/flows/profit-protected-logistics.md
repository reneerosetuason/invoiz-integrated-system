# Inter-hub driver earnings

## Inter-hub earnings

Current policy: version 3. New inter-hub quotes use the agreed per-parcel formula:

```text
Parcel fee = round_centavo(20 + road_km * 0.20 + actual_kg * road_km * 0.04)
Truck-trip driver payment = sum of individually rounded parcel fees
Seller inter-hub deduction = that shipment's actual parcel fee
```

Rates can be configured in Admin Commission: handling per parcel, PHP/km per parcel, and PHP/kg/km. Weight is actual kilograms; parcel and truck dimensions are not required for inter-hub payment. Weight limits and parcel capacity remain checked. Road distance and product measurements remain automatic. Each Delivery is one consolidated parcel; product quantity contributes to weight, not an extra handling charge per unit.

A 1 kg parcel over 91.972 km pays PHP 42.07. Twenty identical parcels pay PHP 841.40. For a PHP 599 product with the existing PHP 50 buyer shipping, PHP 59.90 commission, PHP 30 each for pickup/origin/destination, and PHP 50 last-mile pay, the seller receives PHP 407.03. The buyer shipping offsets the last-mile fee. Product price and production cost do not cap or calculate driver payment.

COD and GCash settlement use the same recorded allocations and commission ledger. GCash still needs verified full escrow funding. Banking details, work completion, disputes, outgoing references and recipient acceptance retain their existing checks. The order-level zero inter-hub accounting row is labeled as included in the truck trip; the separate TRIP payment is the actual driver payout, preventing double payment.

New return agreements record inter_hub_pricing_version=3, including returns for orders shipped under older policies. Physical cross-hub returns quote the reverse road distance and returned product quantity/actual weight using this same formula. Their existing pickup, handling and delivery fees remain separate. Seller-fault eligible customer refunds are not silently reduced by transport; buyer-fault deductions follow the accepted agreement. Refund-only/keep-item resolutions have no physical reverse trip.

Each confirmed truck payment and accepted/funded return quote preserves its recorded amount and rates. Existing snapshots using pricing versions 1 or 2 are not retroactively repriced. A new reviewed reservation or new return contract uses version 3. Confirmed rates remain frozen when Admin changes settings. No subsidy, product-cost requirement or percentage cap is introduced by this policy.

Full physical returns reuse recorded shipment or confirmed-manifest measurements when available, so later product edits do not change the original parcel weight. Partial returns use the returned product quantities; genuinely missing measurements require logistics review instead of an invented weight.
