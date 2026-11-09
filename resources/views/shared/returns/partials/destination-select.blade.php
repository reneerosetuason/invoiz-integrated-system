@php
    $recommendedId=\App\Services\ReturnFeeService::defaultRoute($returnRequest??$case)['seller_center_id'];
    $recommendedHub=\App\Models\LogisticsCenter::find($recommendedId);
    $destinationOptions=\App\Services\ReturnRouting::options()->reject(fn($h)=>(int)$h->id===(int)($excludeCenter??0));
    $selectedDestination=(int)($selectedCenter??$recommendedId);
    if(!$destinationOptions->contains('id',$selectedDestination))$selectedDestination=$destinationOptions->contains('id',$recommendedId)?(int)$recommendedId:0;
@endphp
<label>{{ $destinationLabel??'Destination logistics hub' }}<select name="{{ $destinationField??'destination_center_id' }}" @required($destinationRequired??true)>
    <option value="">Choose an active destination hub</option>
    @foreach($destinationOptions as $hub)<option value="{{ $hub->id }}" @selected((int)$hub->id===$selectedDestination)>{{ $hub->name }}{{ (int)$hub->id===(int)$recommendedId?' — Recommended: original seller hub':'' }}</option>@endforeach
</select></label>
@if($recommendedHub && !$destinationOptions->contains('id',$recommendedId))<p class="review-warning">{{ $recommendedHub->name }} is the original seller hub but is unavailable for this route. Choose an active alternative.</p>@endif
<p>The original order's seller-side hub is recommended. A different active hub can receive the return and arrange delivery to the seller. Paid routes can change only while preserving the agreed services and total.</p>
