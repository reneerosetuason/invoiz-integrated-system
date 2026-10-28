document.querySelectorAll('[data-sort-center]').forEach((centerSelect) => {
            const areaSelect = document.getElementById(centerSelect.dataset.areaTarget);
            if (!areaSelect) return;

            const filterAreas = () => {
                Array.from(areaSelect.options).forEach((option) => {
                    if (!option.value) return;
                    option.hidden = option.dataset.centerId !== centerSelect.value;
                    if (option.hidden && option.selected) areaSelect.value = '';
                });
            };

            centerSelect.addEventListener('change', filterAreas);
            filterAreas();
        });
