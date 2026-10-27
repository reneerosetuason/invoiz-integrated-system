<section class="delivery-panel" aria-labelledby="delivery-filters-title">
    <div class="delivery-panel-title">
        <h2 id="delivery-filters-title">Find a delivery</h2>
        <p class="text-sm text-gray-500">Search records or narrow the list below.</p>
    </div>
    <form method="GET" action="{{ route('deliveries.index') }}" class="delivery-filters delivery-find-form">
        @if($currentStatus)<input type="hidden" name="status" value="{{ $currentStatus }}">@endif
        <input type="hidden" name="per_page" value="{{ request('per_page', 10) }}">
        <div class="delivery-field delivery-field--search">
            <label for="delivery-search">Search deliveries</label>
            <div class="delivery-search-control"><x-icon name="search" class="delivery-control-icon" /><input id="delivery-search" type="search" name="search" value="{{ request('search') }}" placeholder="Tracking, order, name, phone or address"></div>
        </div>
        <details open class="delivery-filter-details" data-active="{{ request()->hasAny(['date_from', 'date_to', 'rider_id', 'vehicle_type', 'center_id', 'service_area_id', 'priority', 'sort', 'dir']) ? 'true' : 'false' }}">
            <summary>More filters &amp; sorting</summary>
            <div class="delivery-filters">
        <div class="delivery-field">
            <label for="delivery-date-from">Created from</label>
            <input id="delivery-date-from" type="date" name="date_from" value="{{ request('date_from') }}" max="{{ request('date_to') }}">
        </div>
        <div class="delivery-field">
            <label for="delivery-date-to">Created until</label>
            <input id="delivery-date-to" type="date" name="date_to" value="{{ request('date_to') }}" min="{{ request('date_from') }}">
        </div>
        <div class="delivery-field">
            <label for="delivery-rider">Rider</label>
            <select id="delivery-rider" name="rider_id">
                <option value="">All riders</option>
                @foreach($filterRiders as $r)
                    <option value="{{ $r->id }}" @selected(request('rider_id') == $r->id)>{{ $r->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="delivery-field">
            <label for="delivery-vehicle">Vehicle</label>
            <select id="delivery-vehicle" name="vehicle_type">
                <option value="">All vehicles</option>
                @foreach($vehicleTypes as $vt)
                    <option value="{{ $vt }}" @selected(request('vehicle_type') === $vt)>{{ ucfirst($vt) }}</option>
                @endforeach
            </select>
        </div>
        @if(Auth::user()->isAdmin())
            <div class="delivery-field">
                <label for="delivery-center">Handling center</label>
                <select id="delivery-center" name="center_id">
                    <option value="">All centers</option>
                    @foreach($filterCenters as $c)
                        <option value="{{ $c->id }}" @selected((int) request('center_id') === $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="delivery-field">
            <label for="delivery-service-area">Service area</label>
            <select id="delivery-service-area" name="service_area_id">
                <option value="">All service areas</option>
                @foreach($filterServiceAreas as $sa)
                    <option value="{{ $sa->id }}" @selected((int) request('service_area_id') === $sa->id)>{{ $sa->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="delivery-field">
            <label for="delivery-priority">Priority</label>
            <select id="delivery-priority" name="priority">
                <option value="">All priorities</option>
                @foreach($filterPriorities as $p)
                    <option value="{{ $p }}" @selected(request('priority') === $p)>{{ ucfirst($p) }}</option>
                @endforeach
            </select>
        </div>
        <div class="delivery-field">
            <label for="delivery-sort">Sort by</label>
            <select id="delivery-sort" name="sort">
                @foreach(['date'=>'Date created', 'tracking_number'=>'Tracking number', 'sender'=>'Sender', 'recipient'=>'Recipient', 'rider'=>'Rider', 'destination'=>'Destination', 'service_area'=>'Service area', 'center'=>'Handling center', 'priority'=>'Priority', 'amount'=>'Amount', 'status'=>'Status'] as $key=>$label)
                    <option value="{{ $key }}" @selected(request('sort', 'date') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="delivery-field">
            <label for="delivery-direction">Sort order</label>
            <select id="delivery-direction" name="dir">
                <option value="desc" @selected(request('dir', 'desc') === 'desc')>Descending / newest first</option>
                <option value="asc" @selected(request('dir') === 'asc')>Ascending / oldest first</option>
            </select>
        </div>
            </div>
        </details>
        <div class="delivery-filter-actions">
            <button type="submit" class="delivery-button delivery-button--primary">Apply filters</button>
            @if($hasFilters || request('sort') || request('dir'))
                <a href="{{ route('deliveries.index') }}" class="delivery-button">Clear filters</a>
            @endif
        </div>
    </form>
</section>
