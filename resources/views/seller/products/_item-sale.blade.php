@php
    $hasItemSale = $saleProduct && $saleProduct->discount_percent > 0;
@endphp
<div class="mb-5 rounded-xl border border-borderline bg-basebg p-4" x-data="{ itemSale: @js((bool)old('item_sale_enabled', $hasItemSale)) }">
    <input type="hidden" name="item_sale_enabled" value="0">
    <label class="flex items-center gap-3 text-sm font-semibold">
        <input type="checkbox" name="item_sale_enabled" value="1" x-model="itemSale">
        Put this item on sale
    </label>
    <p class="mt-2 text-xs text-ink-light">Enter the regular price below, then a lower sale price here. The discount must round to at least 1% off. Buyers see the original price crossed out and the discount percentage automatically.</p>
    <div class="mt-3" x-show="itemSale" x-cloak>
        <label class="field-label" for="item-discounted-price">Discounted price (PHP)</label>
        <input id="item-discounted-price" type="number" name="discounted_price" step="0.01" min="1" :required="itemSale" :disabled="!itemSale" class="input" value="{{ old('discounted_price', $hasItemSale ? $saleProduct->price : '') }}" placeholder="Must be lower than the regular price">
        <p class="mt-1 text-xs text-ink-light">This price stays active until you turn the item sale off. Turning it off restores your regular price. If a store campaign also applies, buyers get the better price; discounts do not stack.</p>
    </div>
</div>
