var invoizData_e096283791 = JSON.parse(document.currentScript.dataset.config);

function salesPage() {
    return {
        form: { id: null, name: '', discount_type: 'percent', discount_value: '', starts_at: '', ends_at: '', status: 'active', products: [] },
        saleMap: invoizData_e096283791[0],
        openEdit(id) {
            const s = this.saleMap[id];
            if (!s) return;
            this.form = JSON.parse(JSON.stringify(s));
            this.$refs.editModal.showModal();
        }
    }
}
