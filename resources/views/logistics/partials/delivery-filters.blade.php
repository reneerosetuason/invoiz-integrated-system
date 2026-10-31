<div class="card p-4">
    <form method="GET" action="{{ route('deliveries.index') }}" class="space-y-3">
        @if($currentStatus)<input type="hidden" name="status" value="{{ $currentStatus }}">@endif
        <input type="hidden" name="per_page" value="{{ request('per_page', 10) }}">

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="sm:col-span-2">
                <label class="field-label" for="delivery-search">Search Deliveries</label>
                <input id="delivery-search" type="search" name="search" value="{{ request('search') }}" placeholder="Tracking #, order #, sender, recipient, phone..." class="input">
            </div>
            <div>
                <label class="field-label" for="delivery-rider">Rider</label>
                <select id="delivery-rider" name="rider_id" class="select">
                    <option value="">All riders</option>
                    @foreach($filterRiders as $r)
                        <option value="{{ $r->id }}" @selected(request('rider_id') == $r->id)>{{ $r->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="field-label" for="delivery-service-area">Service Area</label>
                <select id="delivery-service-area" name="service_area_id" class="select">
                    <option value="">All service areas</option>
                    @foreach($filterServiceAreas as $sa)
                        <option value="{{ $sa->id }}" @selected((int) request('service_area_id') === $sa->id)>{{ $sa->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="field-label" for="delivery-priority">Priority</label>
                <select id="delivery-priority" name="priority" class="select">
                    <option value="">All priorities</option>
                    @foreach($filterPriorities as $p)
                        <option value="{{ $p }}" @selected(request('priority') === $p)>{{ ucfirst($p) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 pt-1">
            <div>
                <label class="field-label" for="delivery-date-from">From Date</label>
                <input id="delivery-date-from" type="date" name="date_from" value="{{ request('date_from') }}" max="{{ request('date_to') }}" class="input">
            </div>
            <div>
                <label class="field-label" for="delivery-date-to">To Date</label>
                <input id="delivery-date-to" type="date" name="date_to" value="{{ request('date_to') }}" min="{{ request('date_from') }}" class="input">
            </div>
            <div>
                <label class="field-label" for="delivery-vehicle">Vehicle</label>
                <select id="delivery-vehicle" name="vehicle_type" class="select">
                    <option value="">All vehicles</option>
                    @foreach($vehicleTypes as $vt)
                        <option value="{{ $vt }}" @selected(request('vehicle_type') === $vt)>{{ ucfirst($vt) }}</option>
                    @endforeach
                </select>
            </div>
            @if(Auth::user()->isAdmin())
                <div>
                    <label class="field-label" for="delivery-center">Handling Center</label>
                    <select id="delivery-center" name="center_id" class="select">
                        <option value="">All centers</option>
                        @foreach($filterCenters as $c)
                            <option value="{{ $c->id }}" @selected((int) request('center_id') === $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label class="field-label" for="delivery-sort">Sort By</label>
                <select id="delivery-sort" name="sort" class="select">
                    @foreach(['date'=>'Date created', 'tracking_number'=>'Tracking number', 'sender'=>'Sender', 'recipient'=>'Recipient', 'rider'=>'Rider', 'destination'=>'Destination', 'service_area'=>'Service area', 'center'=>'Handling center', 'priority'=>'Priority', 'amount'=>'Amount', 'status'=>'Status'] as $key=>$label)
                        <option value="{{ $key }}" @selected(request('sort', 'date') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="field-label" for="delivery-direction">Order</label>
                <select id="delivery-direction" name="dir" class="select">
                    <option value="desc" @selected(request('dir', 'desc') === 'desc')>Newest first</option>
                    <option value="asc" @selected(request('dir') === 'asc')>Oldest first</option>
                </select>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#E8E6E0]">
            @if($hasFilters || request('sort') || request('dir'))
                <a href="{{ route('deliveries.index') }}" class="btn btn-ghost">Reset Filters</a>
            @endif
            <button type="submit" class="btn btn-primary">Apply Filters</button>
        </div>
    </form>
</div>
