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
        $script = file_get_contents(public_path('assets/logistics/js/deliveries/show.js'));
        $css = file_get_contents(public_path('assets/logistics/css/deliveries/show.css'));

        $this->assertStringContainsString('body.printing-delivery-waybill > :not(#shipping-label)', $css);
        $this->assertStringContainsString('display: none !important;', $this->cssRule($css, 'body.printing-delivery-waybill > :not(#shipping-label)'));
        $this->assertStringContainsString('id="shipping-label"', $view);
        $this->assertStringContainsString('id="parcel-barcode"', $view);
        $this->assertStringContainsString('id="parcel-qr"', $view);
        $this->assertStringContainsString('SHIPPING WAYBILL', $view);
        $this->assertStringContainsString('WAYBILL / TRACKING', $view);
        $this->assertStringContainsString('SHIPPED BY / SELLER', $view);
        $this->assertStringContainsString('DELIVER TO / BUYER', $view);
        $this->assertStringContainsString('DELIVERY / LOGISTICS', $view);
        $this->assertStringContainsString('PAYMENT', $view);
        $this->assertStringContainsString('PARCEL INFORMATION', $view);
        $this->assertStringContainsString('ORDER ITEMS', $view);
        $this->assertStringContainsString('Subtotal:', $view);
        $this->assertStringContainsString('Delivery Fee:', $view);
        $this->assertStringContainsString('TOTAL:', $view);
        $this->assertStringContainsString('waybill-footer', $view);
        $this->assertStringContainsString('border-b-2 border-[#111111]', $view);
        $this->assertStringContainsString("@push('styles')", $view);
        $this->assertStringContainsString("window.addEventListener('beforeprint', isolateWaybillForPrint)", $script);
        $this->assertStringContainsString("window.addEventListener('afterprint', restoreWaybillAfterPrint)", $script);
        $this->assertStringContainsString("document.body.classList.add('printing-delivery-waybill')", $script);
        $this->assertStringContainsString("document.body.classList.remove('printing-delivery-waybill')", $script);
    }

    public function test_logistics_waybill_keeps_brand_accents_and_scannable_codes_in_print(): void
    {
        $css = file_get_contents(public_path('assets/logistics/css/deliveries/show.css'));

        $this->assertStringContainsString('#16697A', $css);
        $this->assertStringContainsString('#0E4A57', $css);
        $this->assertStringContainsString('#F0A202', $css);

        $qr = $this->cssRule($css, '#shipping-label #parcel-qr');
        $this->assertStringContainsString('width: 19mm !important;', $qr);
        $this->assertStringContainsString('height: 19mm !important;', $qr);

        $barcode = $this->cssRule($css, '#shipping-label #parcel-barcode');
        $this->assertStringContainsString('height: 11mm !important;', $barcode);

        $screenLabel = $this->cssRule($css, '.delivery-waybill-preview #shipping-label');
        $this->assertStringContainsString('width: 105mm;', $screenLabel);
        $this->assertStringContainsString('box-sizing: border-box;', $screenLabel);
    }
}
