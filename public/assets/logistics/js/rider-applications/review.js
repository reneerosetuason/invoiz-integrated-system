document.querySelectorAll('[data-rider-approval]').forEach((form) => {
    const center = form.querySelector('[name="center_id"]');
    const area = form.querySelector('[name="service_area_id"]');
    const approve = form.querySelector('[data-approve-rider]');
    const hint = form.querySelector('#service-area-hint');
    const createLink = form.querySelector('[data-create-service-area]');
    if (!center || !area || !approve || !hint || !createLink) return;

    const choices = Array.from(area.options).filter((option) => option.value);
    const createUrl = new URL(createLink.href);

    function updateApproval() {
        approve.disabled = !center.value || area.disabled || !area.value;
    }

    function updateAreas() {
        const previous = area.value;
        const matching = choices.filter((option) => option.dataset.centerId === center.value);
        const prompt = !center.value ? 'Select a center first'
            : matching.length ? 'Select service area' : 'No active service areas';
        area.replaceChildren(new Option(prompt, ''), ...matching.map((option) => option.cloneNode(true)));
        area.value = matching.some((option) => option.value === previous) ? previous : '';
        area.disabled = !center.value || matching.length === 0;
        hint.textContent = !center.value ? 'Select a center to see its active service areas.'
            : matching.length ? 'Only active service areas for the selected center are listed.'
                : 'This center has no active service areas. Add one below, then reload this page before approving.';
        const url = new URL(createUrl);
        if (center.value) url.searchParams.set('logistics_center_id', center.value);
        createLink.href = url.href;
        updateApproval();
    }

    center.addEventListener('change', updateAreas);
    area.addEventListener('change', updateApproval);
    form.addEventListener('submit', (event) => {
        if (approve.disabled) {
            event.preventDefault();
            (area.disabled ? center : area).focus();
        }
    });
    updateAreas();
});
