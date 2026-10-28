document.addEventListener('DOMContentLoaded', () => {
        const selectAll = document.getElementById('bulk-select-all');
        const countLabel = document.getElementById('bulk-selected-count');
        const checkboxes = () => Array.from(document.querySelectorAll('.bulk-delivery-checkbox'));

        if (!selectAll || !countLabel) return;

        const updateCount = () => {
            const boxes = checkboxes();
            const selected = boxes.filter((box) => box.checked).length;

            countLabel.textContent = `${selected} selected${selected === 25 ? ' (maximum)' : ''}`;
            boxes.forEach(box => { box.disabled = selected >= 25 && !box.checked; });
            selectAll.checked = boxes.length > 0 && selected === Math.min(boxes.length, 25);
            selectAll.indeterminate = selected > 0 && !selectAll.checked;
            const submit = document.querySelector('#bulk-assign-form button[type=submit]');
            if (submit) submit.disabled = selected === 0;
        };

        selectAll.addEventListener('change', () => {
            checkboxes().forEach((box, index) => { box.checked = selectAll.checked && index < 25; });
            updateCount();
        });

        checkboxes().forEach((box) => box.addEventListener('change', updateCount));

        updateCount();
    });
