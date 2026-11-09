<?php

namespace Tests\Feature;

use Tests\TestCase;

class LogisticsWaybillPrintLayoutTest extends TestCase
{
    private function cssRule(string $css, string $selector): string
    {
        $pattern = '/' . preg_quote($selector, '/') . '\s*\{([^{}]*)\}/s';
        $this->assertSame(1, preg_match($pattern, $css, $matches), "Missing CSS rule: {$selector}");

        return $matches[1];
    }

    public function test_logistics_waybill_print_styles_declare_a6_label_at_upper_left_of_a4(): void
    {
        $cssPath = public_path('assets/logistics/css/deliveries/show.css');
        $this->assertFileExists($cssPath);
        $css = file_get_contents($cssPath);

        $page = $this->cssRule($css, '@page');
        $this->assertStringContainsString('size: A4 portrait;', $page);
        $this->assertStringContainsString('margin: 0;', $page);

        $pageBox = $this->cssRule($css, 'html, body');
        $this->assertStringContainsString('width: 210mm !important;', $pageBox);
        $this->assertStringContainsString('height: 297mm !important;', $pageBox);
        $this->assertStringContainsString('margin: 0 !important;', $pageBox);
        $this->assertStringContainsString('overflow: hidden !important;', $pageBox);

        $label = $this->cssRule($css, 'body.printing-delivery-waybill > #shipping-label');
        $this->assertStringContainsString('position: absolute !important;', $label);
        $this->assertStringContainsString('top: 5mm !important;', $label);
        $this->assertStringContainsString('left: 5mm !important;', $label);
        $this->assertStringContainsString('width: 105mm !important;', $label);
        $this->assertStringContainsString('height: 148.5mm !important;', $label);
        $this->assertStringContainsString('box-sizing: border-box !important;', $label);
        $this->assertStringContainsString('transform: none !important;', $label);
        $this->assertStringNotContainsString('position: fixed', $css);
        $this->assertStringNotContainsString('translate(-50%', $css);

        foreach (['page-break-before', 'page-break-after', 'page-break-inside', 'break-before', 'break-after', 'break-inside'] as $property) {
            $this->assertStringContainsString("{$property}: avoid !important;", $label);
        }

        $this->assertStringContainsString('overflow: hidden !important;', $label);

        $sectionDividerPattern = '/\.waybill-tracking,\s*\.waybill-barcode,\s*\.waybill-parties,\s*\.waybill-logistics,\s*\.waybill-payment-parcel,\s*\.waybill-items\s*\{([^{}]*)\}/s';
        $this->assertSame(1, preg_match($sectionDividerPattern, $css, $sectionDivider));
        $this->assertStringContainsString('border-bottom-width: 1px !important;', $sectionDivider[1]);
    }

    public function test_logistics_waybill_print_styles_isolate_it_from_dashboard(): void
    {
        $view = file_get_contents(resource_path('views/logistics/deliveries/show.blade.php'));
        $label = file_get_contents(resource_path('views/shared/waybill/label.blade.php'));
        $script = file_get_contents(public_path('assets/logistics/js/deliveries/show.js'));
        $css = file_get_contents(public_path('assets/logistics/css/deliveries/show.css'));

        $this->assertStringContainsString('body.printing-delivery-waybill > :not(#shipping-label)', $css);
        $this->assertStringContainsString('display: none !important;', $this->cssRule($css, 'body.printing-delivery-waybill > :not(#shipping-label)'));
        $this->assertStringContainsString("@include('shared.waybill.label')", $view);
        $this->assertStringContainsString('id="shipping-label"', $label);
        $this->assertStringContainsString('id="parcel-barcode"', $label);
        $this->assertSame(1, substr_count($label, 'id="parcel-barcode"'));
        $this->assertStringNotContainsString('parcel-qr', $label);
        $this->assertStringNotContainsString('QR VERIFICATION', $label);
        $this->assertStringContainsString('SHIPPING WAYBILL', $label);
        $this->assertStringContainsString('WAYBILL / TRACKING', $label);
        $this->assertStringContainsString('SHIPPED BY / SELLER', $label);
        $this->assertStringContainsString('DELIVER TO / BUYER', $label);
        $this->assertStringContainsString('DELIVERY / LOGISTICS', $label);
        $this->assertStringContainsString('PAYMENT', $label);
        $this->assertStringContainsString('PARCEL INFORMATION', $label);
        $this->assertStringContainsString('ORDER ITEMS', $label);
        $this->assertStringContainsString('Subtotal:', $label);
        $this->assertStringContainsString('Delivery Fee:', $label);
        $this->assertStringContainsString('TOTAL:', $label);
        $this->assertStringContainsString('waybill-footer', $label);
        $this->assertStringContainsString('border-b-2 border-[#111111]', $label);
        $this->assertStringContainsString("@push('styles')", $view);
        $this->assertStringNotContainsString('qrcodejs', $view);
        $this->assertStringContainsString("window.addEventListener('beforeprint', isolateWaybillForPrint)", $script);
        $this->assertStringContainsString("window.addEventListener('afterprint', restoreWaybillAfterPrint)", $script);
        $this->assertStringContainsString("document.body.classList.add('printing-delivery-waybill')", $script);
        $this->assertStringContainsString("document.body.classList.remove('printing-delivery-waybill')", $script);
        $this->assertStringContainsString("format: 'CODE128'", $script);
        $this->assertStringNotContainsString('QRCode', $script);
    }

    public function test_logistics_waybill_keeps_brand_accents_and_one_scannable_barcode_in_print(): void
    {
        $css = file_get_contents(public_path('assets/logistics/css/deliveries/show.css'));

        $this->assertStringContainsString('#16697A', $css);
        $this->assertStringContainsString('#0E4A57', $css);
        $this->assertStringContainsString('#F0A202', $css);

        $this->assertStringNotContainsString('#parcel-qr', $css);

        $barcode = $this->cssRule($css, '#shipping-label #parcel-barcode');
        $this->assertStringContainsString('height: 11mm !important;', $barcode);

        $screenLabel = $this->cssRule($css, '.delivery-waybill-preview #shipping-label');
        $this->assertStringContainsString('width: 105mm;', $screenLabel);
        $this->assertStringContainsString('box-sizing: border-box;', $screenLabel);
    }
}
