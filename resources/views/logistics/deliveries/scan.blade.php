<x-app-layout>
    @include('logistics.partials.delivery-theme')
    <div class="logistics-deliveries delivery-page-scan">
    <div class="delivery-head">
        <a href="{{ route('deliveries.index') }}"
           class="delivery-back" aria-label="Back to deliveries">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Parcel Scanner</h1>
            <p class="text-sm text-gray-500 mt-0.5">Scan a parcel's QR code to verify and move it from Received to Scanned.</p>
        </div>
    </div>

    @if($expect)
        <div class="mb-6 flex items-start gap-3 bg-teal-light border border-teal/20 rounded-2xl p-4">
            <svg class="h-5 w-5 flex-shrink-0 text-teal-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div class="text-sm text-teal-dark">
                <p class="font-semibold">Expected parcel: <span class="font-mono">{{ $expect }}</span></p>
                @if($expectedDelivery && $expectedDelivery->recipient_name)
                    <p class="mt-0.5">{{ $expectedDelivery->recipient_name }}</p>
                @endif
            </div>
        </div>
    @endif

    <div class="delivery-scan-grid grid gap-6"
         x-data="scanParcel()"
         data-expect="{{ $expect }}"
         data-submit-url="{{ route('deliveries.scan-verify', [], false) }}"
         x-cloak>

        {{-- Camera / Scanner --}}
        <div class="delivery-panel p-4 sm:p-6">
            <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
                <div class="flex items-center gap-2">
                    <div class="h-2 w-1 rounded-full bg-teal"></div>
                    <h2 class="text-base font-semibold text-gray-900">Camera Scanner</h2>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" x-show="['idle','stopped','denied','unsupported','error'].includes(state) || state === 'done'" x-cloak
                            @click="scanAnother(); start()"
                            class="inline-flex items-center gap-2 bg-teal hover:bg-teal-dark text-white font-semibold px-4 py-2.5 rounded-xl transition shadow-sm text-sm">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        <span x-text="state === 'done' ? 'Scan Another Parcel' : 'Start Camera'"></span>
                    </button>
                    <button type="button" x-show="state === 'running' || state === 'starting'" x-cloak
                            @click="stop()"
                            class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 font-semibold px-4 py-2.5 rounded-xl transition text-sm">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0zM9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/></svg>
                        Stop Camera
                    </button>
                </div>
            </div>

            <div class="delivery-camera relative overflow-hidden aspect-video">
                <video x-ref="video" autoplay playsinline muted
                       x-show="state === 'running' || state === 'starting'" x-cloak
                       class="absolute inset-0 w-full h-full object-cover"></video>
                <canvas x-ref="canvas" class="hidden"></canvas>

                {{-- Scan guide overlay --}}
                <div x-show="state === 'running'" x-cloak
                     class="absolute inset-0 z-10" aria-hidden="true">
                    <div class="scan-overlay"></div>
                </div>

                {{-- Placeholder when camera is not running --}}
                <div x-show="['idle','stopped','denied','unsupported','error','done'].includes(state)" x-cloak
                     class="absolute inset-0 z-10 flex flex-col items-center justify-center text-center px-6">
                    <svg class="h-12 w-12 text-gray-600 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <template x-if="state === 'done'">
                        <p class="text-gray-300 font-semibold text-sm">Parcel verified — press "Scan Another Parcel" to continue.</p>
                    </template>
                    <template x-if="state === 'denied'">
                        <p class="text-gray-300 font-semibold text-sm">Camera permission denied. Allow camera access or use manual entry below.</p>
                    </template>
                    <template x-if="state === 'unsupported'">
                        <p class="text-gray-300 font-semibold text-sm">Camera scanning is not supported in this browser. Use manual entry below.</p>
                    </template>
                    <template x-if="state !== 'done' && state !== 'denied' && state !== 'unsupported' && state !== 'error'">
                        <p class="text-gray-300 font-semibold text-sm">Press "Start Camera" to begin scanning.</p>
                    </template>
                </div>

                {{-- Busy overlay --}}
                <div x-show="busy" x-cloak
                     class="absolute inset-0 z-20 bg-black/60 flex items-center justify-center">
                    <p class="text-white font-semibold text-sm">Verifying parcel…</p>
                </div>
            </div>

            <p x-show="message" x-text="message" aria-live="polite"
               class="mt-3 text-sm text-gray-600" x-cloak></p>

            <p x-show="alertText" x-text="alertText" role="alert"
               class="mt-3 text-sm text-red-600" x-cloak></p>

            <p class="mt-2 text-xs text-gray-400">Frames are decoded on this device only — no images are uploaded.</p>
        </div>

        {{-- Result / Manual entry --}}
        <div class="delivery-panel delivery-scan-sidebar flex flex-col">

            {{-- Verification result --}}
            <div x-show="result" x-cloak tabindex="-1" x-ref="resultPanel" class="delivery-scan-result rounded-2xl outline-none">
                <template x-if="result">
                    <div :class="alreadyScanned ? 'bg-amber-50 border-amber-200' : 'bg-green-50 border-green-200'"
                         class="rounded-2xl border p-4">
                        <div class="flex items-start gap-3">
                            <div :class="alreadyScanned ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700'"
                                 class="h-9 w-9 rounded-full flex items-center justify-center flex-shrink-0">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p x-text="alreadyScanned ? 'Parcel Already Scanned' : 'Parcel Verified'" 
                                   :class="alreadyScanned ? 'text-amber-800' : 'text-green-800'"
                                   class="font-bold text-sm"></p>
                                <p class="mt-0.5 font-mono font-semibold text-gray-900 break-all" x-text="result.tracking_number"></p>
                                <dl class="mt-2 space-y-1 text-sm">
                                    <div><dt class="text-xs text-gray-500 inline">Recipient: </dt><dd class="inline font-medium text-gray-800" x-text="result.recipient_name || '—'"></dd></div>
                                    <div><dt class="text-xs text-gray-500 inline">Status: </dt><dd class="inline font-medium text-gray-800" x-text="result.parcel_status"></dd></div>
                                    <div><dt class="text-xs text-gray-500 inline">Scanned at: </dt><dd class="inline font-medium text-gray-800" x-text="result.scanned_at ? new Date(result.scanned_at).toLocaleString() : '—'"></dd></div>
                                </dl>
                            </div>
                        </div>

                        <p x-show="mismatch" role="alert" x-cloak
                           class="mt-3 text-xs text-amber-700 bg-amber-100 rounded-lg px-3 py-2"
                           x-text="'Scanned parcel does not match the expected parcel ' + expect + ' in the previous step. Review the details above before continuing.'"></p>

                        <a x-show="result.show_url" :href="result.show_url"
                           class="mt-4 inline-flex items-center gap-2 text-teal hover:text-teal-dark font-semibold text-sm">
                            View delivery details
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        </a>
                    </div>
                </template>
            </div>

            {{-- Registered parcels awaiting scan: gives the operator a valid
                 tracking number to test manual entry with, and a clear empty
                 state when nothing is ready at this center. --}}
            <div x-show="!result" x-cloak class="delivery-scan-queue rounded-2xl border border-gray-200 bg-gray-50/60 p-4">
                <div class="flex items-center justify-between gap-3 mb-2">
                    <h3 class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Parcels Awaiting Scan</h3>
                    <span class="text-[11px] text-gray-400">{{ $awaitingScan->count() }} shown</span>
                </div>
                @if($awaitingScan->isEmpty())
                    <p class="text-sm text-gray-500">No parcels are awaiting scan right now. Receive a parcel at your center first, then scan it or enter its tracking number.</p>
                @else
                    <p class="text-xs text-gray-500 mb-2">Choose a registered parcel to fill the manual entry field:</p>
                    <ul class="space-y-1.5">
                        @foreach($awaitingScan as $parcel)
                            <li>
                                <button type="button" @click="formCode = @js($parcel->tracking_number); $refs.manualCode.focus()"
                                        class="w-full flex items-center justify-between gap-3 rounded-xl bg-white border border-gray-200 hover:border-teal px-3 py-2 text-left transition">
                                    <span class="font-mono text-xs font-semibold text-gray-900">{{ $parcel->tracking_number }}</span>
                                    <span class="text-xs text-gray-500 truncate">{{ $parcel->recipient_name ?: '—' }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Manual entry fallback --}}
            <div class="delivery-manual">
                <div class="flex items-center gap-2 mb-3">
                    <div class="h-2 w-1 rounded-full bg-teal"></div>
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Manual Entry</h3>
                </div>
                <p class="text-xs text-gray-500 mb-3">Camera not working? Type the parcel's tracking number.</p>
                <form @submit.prevent="submitManual()">
                    <label for="manual_code" class="block text-sm font-medium text-gray-700 mb-1">Tracking Number</label>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <input type="text" id="manual_code" name="tracking_number"
                               x-ref="manualCode"
                               x-model="formCode"
                               autocomplete="off" autocapitalize="characters" spellcheck="false"
                               placeholder="TRK-YYYYMMDD-XXXX"
                               class="flex-1 min-w-0 rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm font-mono">
                        <button type="submit"
                                :disabled="busy"
                                class="bg-teal hover:bg-teal-dark disabled:opacity-60 disabled:cursor-not-allowed text-white font-semibold px-5 py-2.5 rounded-xl transition shadow-sm text-sm whitespace-nowrap">
                            Verify parcel
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-gray-400">Format: <span class="font-mono">TRK-</span> followed by the date and 4-character code.</p>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="{{ asset('vendor/jsqr/jsQR.js') }}"></script>
        <link rel="stylesheet" href="{{ asset('assets/logistics/css/deliveries/scan.css') }}">
        <script src="{{ asset('assets/logistics/js/deliveries/scan.js') }}" data-config="{{ json_encode([($expect), (route('deliveries.scan-verify', [], false))], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) }}"></script>
    @endpush
    </div>
</x-app-layout>
