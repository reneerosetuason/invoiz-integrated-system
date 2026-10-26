<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Http\Request;

/**
 * Product gallery: a product may have 1–9 images (JPG/PNG). The first
 * uploaded image becomes the main/cover image.
 */
class ProductImages
{
    public const MAX = 9;

    public static function save(Product $product, Request $request): void
    {
        $files = $request->file('images', []);
        if (empty($files)) {
            return;
        }

        $existing = $product->images()->count();
        $slots = max(0, self::MAX - $existing);
        $i = 0;

        foreach ((array) $files as $file) {
            if ($i >= $slots) {
                break;
            }

            $path = $file->store('products', 'public');

            $product->images()->create([
                'path'       => $path,
                'image_path' => $path,
                'is_main'    => ($existing === 0 && $i === 0),
                'sort_order' => $existing + $i,
            ]);

            if (empty($product->image) && $existing === 0 && $i === 0) {
                $product->update(['image' => $path]);
            }

            $i++;
        }
    }
}
