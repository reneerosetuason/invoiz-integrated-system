<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Product;
use App\Models\ProductVariant;

class ShipmentMeasurementService
{
    /** Preserve recorded measurements. Missing dimensions use a conservative stacked product envelope. */
    public function fillMissing(Delivery $delivery): void
    {
        if (collect(['weight', 'length_cm', 'width_cm', 'height_cm'])->every(fn ($field) => $delivery->$field !== null)) {
            return;
        }
        if (! $delivery->order) {
            return;
        }
        $items = $delivery->order->items;
        if ($delivery->seller_id) {
            $items = $items->where('seller_id', $delivery->seller_id);
        } elseif ($items->pluck('seller_id')->unique()->count() !== 1) {
            return;
        }
        if ($items->isEmpty()) {
            return;
        }
        $products = Product::withTrashed()->whereIn('id', $items->pluck('product_id'))->get()->keyBy('id');
        $variants = ProductVariant::withTrashed()->whereIn('id', $items->map(fn ($i) => $i->product_variant_id ?: $i->variant_id)->filter())->get()->keyBy('id');
        $weight = 0;
        $length = $width = $height = 0;
        $validWeight = $validSize = true;
        foreach ($items as $item) {
            $product = $products->get($item->product_id);
            $variant = $variants->get($item->product_variant_id ?: $item->variant_id);
            $unitWeight = $variant && (int) $variant->product_id === (int) $item->product_id
                ? ShipmentWeightService::kilograms($variant->weight) : null;
            $unitWeight ??= ShipmentWeightService::kilograms($product?->weight);
            $quantity = (int) $item->quantity;
            $validWeight = $validWeight && $unitWeight !== null && $quantity > 0;
            $weight += ($unitWeight ?? 0) * $quantity;
            // Seller product form stores centimeters. Reject unsupported units rather than misreading them.
            $matched = preg_match('/^\s*(\d+(?:\.\d+)?)\s*(?:x|\x{00D7}|\*|by)\s*(\d+(?:\.\d+)?)\s*(?:x|\x{00D7}|\*|by)\s*(\d+(?:\.\d+)?)\s*(?:cm)?\s*$/iu', (string) $product?->dimensions, $size);
            if (! $matched || $quantity <= 0 || min((float) $size[1], (float) $size[2], (float) $size[3]) <= 0) {
                $validSize = false;

                continue;
            }
            $length = max($length, (float) $size[1]);
            $width = max($width, (float) $size[2]);
            $height += (float) $size[3] * $quantity;
        }
        if ($delivery->weight === null && $validWeight && $weight > 0 && $weight <= 999999.99) {
            $delivery->weight = ceil(round($weight * 100, 8)) / 100;
        }
        if ($validSize && max($length, $width, $height) <= 10000) {
            foreach (['length_cm' => $length, 'width_cm' => $width, 'height_cm' => $height] as $field => $value) {
                if ($delivery->$field === null) {
                    $delivery->$field = ceil(round($value * 100, 8)) / 100;
                }
            }
        }
    }
}
