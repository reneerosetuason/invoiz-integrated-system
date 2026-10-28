function productForm() {
    return {
        variants: [],
        addVariant() {
            this.variants.push({ type: '', value: '', adjustment: 0, stock: 0 });
        }
    }
}
