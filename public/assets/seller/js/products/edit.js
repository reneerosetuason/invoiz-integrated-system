var invoizData_9e87fd3265 = JSON.parse(document.currentScript.dataset.config);

function productForm() {
    return {
        variants: invoizData_9e87fd3265[0],
        addVariant() {
            this.variants.push({ type: '', value: '', adjustment: 0, stock: 0 });
        }
    }
}
