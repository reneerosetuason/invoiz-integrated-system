<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class ViewStructureTest extends TestCase
{
    public function test_every_template_has_an_owner_and_uses_external_styles_and_scripts(): void
    {
        foreach (File::allFiles(resource_path('views')) as $file) {
            $name = str_replace('\\', '/', $file->getRelativePathname());
            $this->assertMatchesRegularExpression('~^(buyer|seller|logistics|admin|shared)/~', $name);
            $source = $file->getContents();
            $this->assertDoesNotMatchRegularExpression('~<style\b~i', $source, $name);
            preg_match_all('~<script\b([^>]*)>~i', $source, $scripts);
            foreach ($scripts[1] as $attributes) {
                $this->assertMatchesRegularExpression('~\bsrc\s*=~', $attributes, $name);
            }
            preg_match_all("~@(?:extends|include)\('([^']+)'~", $source, $includes);
            foreach ($includes[1] as $include) {
                $this->assertTrue(View::exists($include), $name.' includes '.$include);
            }
            preg_match_all("~(?:asset|public_path)\('([^']+)'\)~", $source, $assets);
            foreach ($assets[1] as $asset) {
                if (str_starts_with($asset, 'assets/')) {
                    $this->assertFileExists(public_path($asset), $name.' loads '.$asset);
                }
            }
            preg_match_all("~@vite\('([^']+)'\)~", $source, $entries);
            foreach ($entries[1] as $entry) {
                $this->assertFileExists(base_path($entry), $name.' builds '.$entry);
            }
        }
    }

    public function test_shared_components_keep_their_existing_blade_names(): void
    {
        $html = Blade::render('<x-primary-button>Continue</x-primary-button>');
        $this->assertStringContainsString('<button', $html);
        $this->assertStringContainsString('Continue', $html);
    }

    public function test_address_initializers_keep_separate_server_data_without_inline_scripts(): void
    {
        $first = view('shared.partials.psgc-address-fields', ['prefix' => 'billing'])->render();
        $second = view('shared.partials.psgc-address-fields', ['prefix' => 'shipping'])->render();
        foreach ([$first, $second] as $html) {
            preg_match('~data-config="([^"]+)"~', $html, $match);
            $config = json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR);
            $this->assertCount(7, $config);
            $this->assertStringContainsString('assets/shared/js/partials/psgc-address-fields.js', $html);
        }
        $this->assertNotSame($first, $second);
    }
}
