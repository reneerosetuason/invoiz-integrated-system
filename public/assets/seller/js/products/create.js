var invoizProductFormConfig = JSON.parse(document.currentScript.dataset.config);

function productForm() {
    return {
        variants: invoizProductFormConfig[0],
        generalStock: Number(invoizProductFormConfig[1] || 0),
        get totalVariantStock() {
            return this.variants.reduce((sum, v) => sum + Math.max(0, Number(v.stock) || 0), 0);
        },
        get overAllocated() {
            return this.totalVariantStock > Math.max(0, Number(this.generalStock) || 0);
        },
        addVariant() {
            this.variants.push({ id: null, type: '', value: '', adjustment: 0, stock: 0 });
        }
    };
}
