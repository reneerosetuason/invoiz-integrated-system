(function () {
  'use strict';

  const dialog = document.getElementById('seller-photo-dialog');
  if (!dialog || typeof dialog.showModal !== 'function') return;

  const canvas = dialog.querySelector('[data-photo-canvas]');
  const context = canvas.getContext('2d');
  const stage = dialog.querySelector('[data-photo-stage]');
  const aspectSelect = dialog.querySelector('[data-photo-aspect-select]');
  const aspectControl = dialog.querySelector('[data-photo-aspect-control]');
  const zoomInput = dialog.querySelector('[data-photo-zoom]');
  const zoomValue = dialog.querySelector('[data-photo-zoom-value]');
  const countLabel = dialog.querySelector('[data-photo-count]');
  const errorLabel = dialog.querySelector('[data-photo-error]');
  const hint = dialog.querySelector('[data-photo-hint]');
  const applyButton = dialog.querySelector('[data-photo-apply]');
  const priorSelections = new WeakMap();

  let batch = null;
  let currentImage = null;
  let currentUrl = null;
  let zoom = 1;
  let rotation = 0;
  let panX = 0;
  let panY = 0;
  let aspect = 1;
  let dragging = false;
  let lastPointer = null;
  let busy = false;

  function showError(message) {
    errorLabel.textContent = message || '';
    errorLabel.classList.toggle('is-visible', Boolean(message));
  }

  function setInputFiles(input, files) {
    try {
      const transfer = new DataTransfer();
      files.forEach(function (file) { transfer.items.add(file); });
      input.files = transfer.files;
      return true;
    } catch (error) {
      return false;
    }
  }

  function dispatchInputChange(input) {
    input.dataset.photoEditorDispatch = '1';
    input.dispatchEvent(new Event('change', { bubbles: true }));
    delete input.dataset.photoEditorDispatch;
  }

  function loadImage(file) {
    return new Promise(function (resolve, reject) {
      const image = new Image();
      const url = URL.createObjectURL(file);
      image.onload = function () { resolve({ image: image, url: url }); };
      image.onerror = function () {
        URL.revokeObjectURL(url);
        reject(new Error('This photo could not be opened. Try a JPG, PNG, or WebP image.'));
      };
      image.src = url;
    });
  }

  function rotatedBounds(image, angle) {
    const radians = angle * Math.PI / 180;
    const cosine = Math.abs(Math.cos(radians));
    const sine = Math.abs(Math.sin(radians));
    return {
      width: image.naturalWidth * cosine + image.naturalHeight * sine,
      height: image.naturalWidth * sine + image.naturalHeight * cosine
    };
  }

  function paint(target, width, height) {
    target.width = width;
    target.height = height;
    const targetContext = target === canvas ? context : target.getContext('2d');
    targetContext.clearRect(0, 0, width, height);

    if (!currentImage) return;

    const bounds = rotatedBounds(currentImage, rotation);
    const fillScale = Math.max(width / bounds.width, height / bounds.height);
    const scale = fillScale * zoom;
    const displayScale = width / canvas.width;

    targetContext.save();
    targetContext.translate(width / 2 + panX * displayScale, height / 2 + panY * displayScale);
    targetContext.rotate(rotation * Math.PI / 180);
    targetContext.scale(scale, scale);
    targetContext.drawImage(
      currentImage,
      -currentImage.naturalWidth / 2,
      -currentImage.naturalHeight / 2
    );
    targetContext.restore();
  }

  function render() {
    if (!currentImage) return;
    const width = 900;
    const height = Math.round(width / aspect);
    canvas.width = width;
    canvas.height = height;
    stage.style.setProperty('--photo-aspect', String(aspect));
    paint(canvas, width, height);
  }

  function clampPan() {
    if (!currentImage) return;
    const bounds = rotatedBounds(currentImage, rotation);
    const fillScale = Math.max(canvas.width / bounds.width, canvas.height / bounds.height);
    const maxX = Math.max(0, (bounds.width * fillScale * zoom - canvas.width) / 2);
    const maxY = Math.max(0, (bounds.height * fillScale * zoom - canvas.height) / 2);
    panX = Math.max(-maxX, Math.min(maxX, panX));
    panY = Math.max(-maxY, Math.min(maxY, panY));
  }

  function resetAdjustments() {
    zoom = 1;
    rotation = 0;
    panX = 0;
    panY = 0;
    zoomInput.value = '1';
    zoomValue.value = '1×';
    zoomValue.textContent = '1×';
    render();
  }

  async function loadCurrentPhoto() {
    const activeBatch = batch;
    const file = activeBatch.files[activeBatch.index];
    if (currentUrl) URL.revokeObjectURL(currentUrl);
    currentImage = null;
    resetAdjustments();
    showError('');
    countLabel.textContent = batch.files.length > 1
      ? 'Photo ' + (batch.index + 1) + ' of ' + batch.files.length
      : 'Photo';

    try {
      const loaded = await loadImage(file);
      if (batch !== activeBatch) {
        URL.revokeObjectURL(loaded.url);
        return;
      }
      currentImage = loaded.image;
      currentUrl = loaded.url;
      render();
    } catch (error) {
      if (batch !== activeBatch) return;
      showError(error.message);
      applyButton.disabled = true;
    }
  }

  function updatePreview(input, files, urls) {
    const previewKey = input.dataset.photoPreviewKey;
    if (previewKey) {
      document.querySelectorAll('[data-photo-preview-target-key]').forEach(function (target) {
        if (target.dataset.photoPreviewTargetKey !== previewKey) return;
        target.replaceChildren();
        if (urls[0]) {
          const image = document.createElement('img');
          image.className = 'seller-photo-preview';
          image.src = urls[0];
          image.alt = 'Edited photo preview';
          target.appendChild(image);
          target.hidden = false;
        }
      });
    }

    const galleryKey = input.dataset.photoGalleryKey;
    if (galleryKey) {
      document.querySelectorAll('[data-photo-gallery-target-key]').forEach(function (target) {
        if (target.dataset.photoGalleryTargetKey !== galleryKey) return;
        target.replaceChildren();
        target.classList.add('seller-photo-gallery-preview');
        urls.forEach(function (url, index) {
          const image = document.createElement('img');
          image.src = url;
          image.alt = 'Edited gallery photo ' + (index + 1);
          target.appendChild(image);
        });
      });
    }
  }

  function restorePreviousSelection(input, selection) {
    if (selection.files.length) {
      if (!setInputFiles(input, selection.files)) {
        input.value = '';
        return false;
      }
    } else {
      input.value = '';
    }
    input.setCustomValidity('');
    dispatchInputChange(input);
    return true;
  }

  function finishBatch() {
    const finished = batch;
    const oldSelection = priorSelections.get(finished.input) || { files: [], urls: [] };

    if (!setInputFiles(finished.input, finished.cropped)) {
      busy = false;
      applyButton.disabled = false;
      showError('Your browser could not attach the edited photo. Please use an up-to-date browser and try again.');
      return;
    }

    finished.input.setCustomValidity('');
    priorSelections.set(finished.input, {
      files: finished.cropped.slice(),
      urls: finished.previewUrls.slice()
    });
    updatePreview(finished.input, finished.cropped, finished.previewUrls);
    oldSelection.urls.forEach(URL.revokeObjectURL);
    dispatchInputChange(finished.input);
    closeDialog();
  }

  function closeDialog() {
    if (currentUrl) URL.revokeObjectURL(currentUrl);
    currentUrl = null;
    currentImage = null;
    if (dialog.open) dialog.close();
    if (batch) batch.input.focus();
    batch = null;
    busy = false;
    applyButton.disabled = false;
    showError('');
  }

  function cancelBatch() {
    if (!batch || busy) return;
    const cancelled = batch;
    const previous = priorSelections.get(cancelled.input) || { files: [], urls: [] };
    cancelled.previewUrls.forEach(URL.revokeObjectURL);
    restorePreviousSelection(cancelled.input, previous);
    closeDialog();
  }

  async function beginBatch(input) {
    if (batch || !input.files || !input.files.length) return;
    const files = Array.from(input.files);
    if (files.some(function (file) { return !file.type.startsWith('image/'); })) {
      input.value = '';
      input.setCustomValidity('Choose an image file to edit.');
      input.reportValidity();
      return;
    }

    const fixedAspect = Number(input.dataset.photoAspect);
    aspectControl.hidden = Number.isFinite(fixedAspect) && fixedAspect > 0;
    aspect = aspectControl.hidden ? fixedAspect : Number(aspectSelect.value) || 1;
    if (!aspectControl.hidden) aspectSelect.value = String(aspect);
    hint.textContent = aspectControl.hidden
      ? 'This square crop is sized for your store picture.'
      : 'Drag to position the photo. You can change the crop shape, zoom, or rotate it.';

    batch = {
      input: input,
      files: files,
      previous: priorSelections.get(input) || { files: [], urls: [] },
      maxBytes: Number(input.dataset.photoMaxBytes) || 5 * 1024 * 1024,
      outputFormat: input.dataset.photoOutputFormat,
      cropped: [],
      previewUrls: [],
      index: 0
    };
    countLabel.textContent = '';
    applyButton.disabled = true;
    if (!dialog.open) dialog.showModal();
    const activeBatch = batch;
    await loadCurrentPhoto();
    if (batch === activeBatch) applyButton.disabled = !currentImage;
  }

  function fileFromCanvas(file) {
    const mime = batch.outputFormat === 'png'
      ? 'image/png'
      : ['image/jpeg', 'image/png', 'image/webp'].includes(file.type)
      ? file.type
      : 'image/jpeg';
    const stem = file.name.replace(/\.[^.]+$/, '') || 'photo';
    const options = [1200, 1000, 850, 700, 600, 500];
    const maxBytes = batch.maxBytes;
    const maxMegabytes = Math.floor(maxBytes / (1024 * 1024));

    function attempt(sizeIndex) {
      if (sizeIndex >= options.length) {
        return Promise.reject(new Error('The edited photo is still over ' + maxMegabytes + ' MB. Try a different image.'));
      }
      const width = options[sizeIndex];
      const height = Math.round(width / aspect);
      const output = document.createElement('canvas');
      paint(output, width, height);

      return new Promise(function (resolve, reject) {
        output.toBlob(function (blob) {
          if (!blob) {
            reject(new Error('The photo could not be saved. Try another image.'));
            return;
          }
          if (blob.size > maxBytes) {
            attempt(sizeIndex + 1).then(resolve).catch(reject);
            return;
          }
          const outputMime = blob.type || mime;
          const extension = outputMime === 'image/jpeg' ? 'jpg' : outputMime.split('/')[1] || 'jpg';
          resolve(new File([blob], stem + '-edited.' + extension, {
            type: outputMime,
            lastModified: Date.now()
          }));
        }, mime, mime === 'image/png' ? undefined : 0.92);
      });
    }

    return attempt(0);
  }

  document.addEventListener('change', function (event) {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || !input.matches('[data-photo-editor]')) return;
    if (input.dataset.photoEditorDispatch === '1') return;
    beginBatch(input);
  });

  aspectSelect.addEventListener('change', function () {
    aspect = Number(aspectSelect.value) || 1;
    panX = 0;
    panY = 0;
    render();
  });

  zoomInput.addEventListener('input', function () {
    zoom = Number(zoomInput.value) || 1;
    clampPan();
    zoomValue.value = zoom.toFixed(1).replace(/\.0$/, '') + '×';
    zoomValue.textContent = zoomValue.value;
    render();
  });

  dialog.querySelectorAll('[data-photo-rotate]').forEach(function (button) {
    button.addEventListener('click', function () {
      rotation = (rotation + Number(button.dataset.photoRotate) + 360) % 360;
      panX = 0;
      panY = 0;
      render();
    });
  });

  dialog.querySelector('[data-photo-reset]').addEventListener('click', resetAdjustments);

  canvas.addEventListener('pointerdown', function (event) {
    if (!currentImage) return;
    dragging = true;
    lastPointer = { x: event.clientX, y: event.clientY };
    canvas.setPointerCapture(event.pointerId);
  });

  canvas.addEventListener('pointermove', function (event) {
    if (!dragging || !lastPointer) return;
    const rect = canvas.getBoundingClientRect();
    panX += (event.clientX - lastPointer.x) * (canvas.width / rect.width);
    panY += (event.clientY - lastPointer.y) * (canvas.height / rect.height);
    lastPointer = { x: event.clientX, y: event.clientY };
    clampPan();
    render();
  });

  function stopDragging() {
    dragging = false;
    lastPointer = null;
  }
  canvas.addEventListener('pointerup', stopDragging);
  canvas.addEventListener('pointercancel', stopDragging);

  dialog.querySelectorAll('[data-photo-close]').forEach(function (button) {
    button.addEventListener('click', cancelBatch);
  });
  dialog.addEventListener('cancel', function (event) {
    event.preventDefault();
    cancelBatch();
  });
  dialog.addEventListener('click', function (event) {
    if (event.target === dialog) cancelBatch();
  });

  applyButton.addEventListener('click', async function () {
    if (!batch || !currentImage || busy) return;
    const activeBatch = batch;
    busy = true;
    applyButton.disabled = true;
    showError('');
    try {
      const file = activeBatch.files[activeBatch.index];
      const cropped = await fileFromCanvas(file);
      activeBatch.cropped.push(cropped);
      activeBatch.previewUrls.push(URL.createObjectURL(cropped));
      if (currentUrl) URL.revokeObjectURL(currentUrl);
      currentUrl = null;
      currentImage = null;
      activeBatch.index += 1;

      if (activeBatch.index < activeBatch.files.length) {
        busy = false;
        await loadCurrentPhoto();
        if (batch === activeBatch) applyButton.disabled = !currentImage;
        return;
      }
      finishBatch();
    } catch (error) {
      busy = false;
      applyButton.disabled = false;
      showError(error.message || 'The photo could not be cropped. Try again.');
    }
  });
})();
