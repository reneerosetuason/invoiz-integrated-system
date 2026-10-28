document.querySelectorAll('input[name="reason"]').forEach(function (input) {
      input.addEventListener('change', function () {
        var wrap = document.getElementById('cancel-other-wrap');
        var details = document.getElementById('cancel-reason-details');
        var isOther = this.value === 'other';
        wrap.style.display = isOther ? 'block' : 'none';
        details.required = isOther;
      });
    });
    var selectedReason = document.querySelector('input[name="reason"]:checked');
    if (selectedReason) selectedReason.dispatchEvent(new Event('change'));
