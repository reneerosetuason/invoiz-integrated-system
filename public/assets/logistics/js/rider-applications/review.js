document.querySelectorAll('[data-rider-approval]').forEach((form) => {
    const center = form.querySelector('[name="center_id"]');
    const area = form.querySelector('[name="service_area_id"]');
    const approve = form.querySelector('[data-approve-rider]');
    const hint = form.querySelector('#service-area-hint');
    const createLink = form.querySelector('[data-create-service-area]');
    if (!approve) return;

    let isSubmitting = false;

    function updateApproval() {
        if (!center) return;
        if (area) {
            approve.disabled = !center.value || area.disabled || !area.value;
        } else {
            approve.disabled = !center.value;
        }
    }

    if (center && area && hint && createLink) {
        const choices = Array.from(area.options).filter((option) => option.value);
        const createUrl = new URL(createLink.href);

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
        updateAreas();
    } else if (center) {
        center.addEventListener('change', updateApproval);
        updateApproval();
    }

    form.addEventListener('submit', (event) => {
        if (isSubmitting) {
            event.preventDefault();
            return;
        }

        if (approve.disabled) {
            event.preventDefault();
            if (area && area.disabled) {
                (center || area).focus();
            } else if (area) {
                area.focus();
            } else if (center) {
                center.focus();
            }
            return;
        }

        isSubmitting = true;
        approve.disabled = true;
        approve.style.pointerEvents = 'none';
        approve.classList.add('opacity-75', 'cursor-not-allowed');
        approve.innerHTML = `
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
            </svg>
            <span>Processing...</span>
        `;
    });
});
