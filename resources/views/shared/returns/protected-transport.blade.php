@if($agreement->platform_subsidy_cents)
<div data-protected-return-funding class="return-support-summary"><strong>Profit-protected return funding</strong><p>Transport services: {{ peso($agreement->transport_cents/100) }}. Original seller fee reimbursement: {{ peso($agreement->seller_reimbursement_cents/100) }}. Platform contribution: {{ peso($agreement->platform_subsidy_cents/100) }}. {{ ucfirst($agreement->payer) }} contribution: {{ peso($agreement->payerBudgetCents()/100) }}. Original buyer refunds remain separately funded. Seller fee reimbursement is a separate remittance after the buyer confirms their refund; it never reduces the buyer refund.</p></div>
@endif
@if($reverseTrip = $agreement->fee_snapshot['return_trip'] ?? null)
<div class="return-trip-summary">Return truck-trip quote: {{ number_format($reverseTrip['distance'], 3) }} km; {{ number_format($reverseTrip['weight'], 2) }} kg; {{ number_format($reverseTrip['volume'], 6) }} m3; load {{ number_format($reverseTrip['utilization'], 2) }}%; driver payment {{ peso($reverseTrip['total']/100) }}. {{ $reverseTrip['route_distance_source'] }}. This quote covers the returned parcels on this dedicated trip.</div>
@endif
