var invoizData_5ca9eb0abf = JSON.parse(document.currentScript.dataset.config);

document.addEventListener('alpine:init', () => {
    Alpine.data('editVoucher', () => ({
        editing: null,
        editAction: '',
        openEdit(id) {
            const v = invoizData_5ca9eb0abf[0];
            this.editing = v[id];
            this.editAction = invoizData_5ca9eb0abf[1] + '/' + id;
            this.$refs.editModal.showModal();
        }
    }));
});
