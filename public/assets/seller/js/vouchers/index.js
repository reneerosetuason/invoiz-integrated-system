var invoizData_5ca9eb0abf = JSON.parse(document.currentScript.dataset.config);

document.addEventListener('alpine:init', () => {
    Alpine.data('editVoucher', () => ({
        editing: { code: '', name: '', description: '', discount_type: 'fixed', discount_value: 0, min_spend: 0, max_discount: '', valid_from: '', valid_until: '', usage_limit: '', status: 'active', has_claims: false },
        editAction: '',
        openEdit(id) {
            const v = invoizData_5ca9eb0abf[0];
            if (!v[id]?.update_url) return;
            this.editing = { ...v[id] };
            this.editAction = v[id].update_url;
            this.$refs.editModal.showModal();
        }
    }));
});
