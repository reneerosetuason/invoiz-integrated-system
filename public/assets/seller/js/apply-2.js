(function () {
  const form = document.querySelector('form[enctype="multipart/form-data"]');
  if (!form) return;

  const storeName = document.getElementById('store-name');
  const validStoreName = /^[\p{L}\p{M}\p{N}][\p{L}\p{M}\p{N}\s&'’.,()+\-\/#]{1,49}$/u;
  storeName.addEventListener('input', () => {
    storeName.setCustomValidity(validStoreName.test(storeName.value.trim()) ? '' : 'Use 2–50 characters. Letters, numbers, spaces, and common business punctuation are allowed.');
  });

  const tin = document.getElementById('seller-tin');
  tin.addEventListener('input', () => { tin.value = tin.value.replace(/\D/g, '').slice(0, 12); });
  const postalCode = document.getElementById('seller-postal-code');
  postalCode.addEventListener('input', () => { postalCode.value = postalCode.value.replace(/\D/g, '').slice(0, 4); });

  form.querySelectorAll('input[type="file"]').forEach((input) => {
    input.addEventListener('change', () => {
      const tooLarge = input.files && input.files[0] && input.files[0].size > 5 * 1024 * 1024;
      input.setCustomValidity(tooLarge ? 'Each uploaded file must be 5 MB or smaller.' : '');
    });
  });
})();
