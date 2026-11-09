@php($onlineRiders = \App\Services\ReturnSettlementService::onlineRiderOptions((int)$riderCenter))
<label>{{ $riderLabel }}<select name="rider_id" @required($riderRequired??false)>
    <option value="">{{ $onlineRiders->isEmpty()?'No online riders available':'Choose an online rider' }}</option>
    @foreach($onlineRiders as $rider)<option value="{{ $rider->id }}">{{ $rider->name }} (#{{ $rider->id }}) · Online</option>@endforeach
</select></label>
@if($onlineRiders->isEmpty())<p class="review-warning">No online approved riders at this hub. Ask a rider to go online, then refresh this page.</p>@endif
