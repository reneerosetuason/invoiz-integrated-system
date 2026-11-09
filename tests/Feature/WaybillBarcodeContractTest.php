<?php

namespace Tests\Feature;

use Tests\TestCase;

class WaybillBarcodeContractTest extends TestCase
{
    private function sharedLabel(): string
    {
        $path = resource_path('views/shared/waybill/label.blade.php');
        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    public function test_shared_waybill_renders_exactly_one_code128_barcode_and_no_qr(): void
    {
        $label = $this->sharedLabel();

        $this->assertSame(1, substr_count($label, 'id="parcel-barcode"'));
        $this->assertStringContainsString('CODE128 of the tracking number', $label);
        $this->assertStringContainsString('aria-label="Barcode for {{ $delivery->tracking_number }}"', $label);

        // The tracking number stays readable beside the barcode.
        $this->assertStringContainsString('{{ $delivery->tracking_number }}', $label);

        // No second identifier or QR workflow remains on the label.
        $this->assertStringNotContainsString('parcel-qr', $label);
        $this->assertStringNotContainsString('QR VERIFICATION', $label);
        $this->assertStringNotContainsString('qrcode', $label);
    }

    public function test_barcode_encodes_the_canonical_tracking_number_in_both_waybill_views(): void
    {
        $script = file_get_contents(public_path('assets/logistics/js/deliveries/show.js'));
        $this->assertStringContainsString("var trackingNumber = invoizData_68dbadcd18[0];", $script);
        $this->assertStringContainsString('JsBarcode(barEl, trackingNumber, {', $script);
        $this->assertStringContainsString("format: 'CODE128'", $script);
        $this->assertStringNotContainsString('QRCode', $script);

        $seller = file_get_contents(resource_path('views/seller/orders/waybill.blade.php'));
        $logistics = file_get_contents(resource_path('views/logistics/deliveries/show.blade.php'));

        foreach ([$seller, $logistics] as $view) {
            $this->assertStringContainsString("@include('shared.waybill.label')", $view);
            $this->assertStringContainsString('JsBarcode.all.min.js', $view);
            $this->assertMatchesRegularExpression(
                '/json_encode\(\[\(?\$delivery->tracking_number\)?\]/',
                $view
            );
            $this->assertStringNotContainsString('qrcodejs', $view);
        }
    }

    public function test_logistics_web_scanner_decodes_code128_and_keeps_manual_entry(): void
    {
        $script = file_get_contents(public_path('assets/logistics/js/deliveries/scan.js'));
        $view = file_get_contents(resource_path('views/logistics/deliveries/scan.blade.php'));

        // The camera path reads the Code128 waybill barcode first and still
        // decodes QR only as a fallback for labels already in the field.
        $this->assertStringContainsString("formats: ['code128']", $script);
        $this->assertStringContainsString('window.BarcodeDetector', $script);
        $this->assertStringContainsString('window.jsQR', $script);
        $this->assertStringContainsString('^TRK-\\d{8}-[A-Z0-9]{4}$', $script);

        // Every decoded value goes through the same backend endpoint.
        $this->assertStringContainsString("body: JSON.stringify({ tracking_number: value })", $script);
        $this->assertStringContainsString("route('deliveries.scan-verify'", $view);

        // Manual entry is preserved for devices without camera decoding.
        $this->assertStringContainsString('x-ref="manualCode"', $view);
        $this->assertStringContainsString('@submit.prevent="submitManual()"', $view);
        $this->assertStringContainsString('name="tracking_number"', $view);
    }

    public function test_flutter_parcel_scanners_are_configured_for_code128_with_manual_fallback(): void
    {
        $scanners = [
            'rider_mobile/lib/screens/logistics/logistics_scan_screen.dart',
            'rider_mobile/lib/screens/scan/scan_parcel_screen.dart',
            'rider_mobile/lib/screens/deliveries/pickup_screen.dart',
        ];

        foreach ($scanners as $relative) {
            $source = file_get_contents(base_path($relative));
            $this->assertStringContainsString(
                'formats: const [BarcodeFormat.code128]',
                $source,
                "{$relative} must scan the waybill Code128 barcode"
            );
            $this->assertStringNotContainsString(
                'Scan the QR code',
                $source,
                "{$relative} must not instruct users to scan a QR code"
            );
        }

        // Manual tracking-number entry remains in each scanner.
        $this->assertStringContainsString('Enter tracking number', file_get_contents(base_path($scanners[0])));
        $this->assertStringContainsString('Enter tracking number', file_get_contents(base_path($scanners[1])));
        $this->assertStringContainsString('Enter tracking number', file_get_contents(base_path($scanners[2])));
    }
}
