# Inter-hub driver earnings

## Inter-hub earnings

Only inter-hub driver payment changes. Buyers keep the existing checkout and shipping fee. Sellers use their existing product weight and dimensions; packaging cost and subsidy funding are not required.

```text
Volume = length_cm * width_cm * height_cm / 1,000,000
Utilization = max(total_kg / truck_payload_kg, total_volume / truck_volume)
Calculated trip cost = base + road_km * rate_per_km + utilization_bonus
Parcel budget = min(net product value * 5%, available recorded margin)
Driver payment = min(calculated trip cost, sum of parcel budgets)
Chargeable weight = max(actual_kg, length_cm * width_cm * height_cm / 5000)
```

The configurable percentage is in Admin Commission. Available margin, when existing product costs are recorded, subtracts product costs, commission, existing other logistics charges and a 5% product-value profit allowance. Missing product cost does not block checkout; the percentage limit still applies, but actual business profit cannot be guaranteed without costs. A product already priced below its other costs needs a price/cost review; an inter-hub fee cannot repair that loss.

Allocate the actual driver payment by chargeable weight, capping each parcel at its budget and redistributing the remainder among parcels with room. Allocations round to exact centavos and sum to the driver payout. There is no platform-funded difference. Confirm the resulting payout before dispatch; a zero-budget manifest needs logistics review/consolidation. No buyer or seller funding form is added.

COD and GCash use the same allocation, seller earnings, escrow reservation and Admin Commission accounting. Existing pickup, hub, last-mile, commission and buyer shipping rates remain in place. Paid trip snapshots and accepted return contracts retain their recorded amounts.

Returns for shipments using this pricing quote the reverse road distance and returned product quantities, weight and dimensions using the same capped driver calculation. Existing return responsibility, agreement, deposit verification, refund and remittance rules apply. Seller-fault full eligible refunds remain separate from return delivery payment. Keep-item refunds have no reverse transport. Refunds cannot guarantee a profit on returned or damaged merchandise. No new subsidy or fee reimbursement is created.
