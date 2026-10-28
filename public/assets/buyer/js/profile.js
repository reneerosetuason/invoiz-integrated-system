(() => {
  const input = document.getElementById('profile_picture');
  const dialog = document.getElementById('profile-crop-dialog');
  const canvas = document.getElementById('profile-crop-canvas');
  const zoomInput = document.getElementById('profile-crop-zoom');
  const zoomValue = document.getElementById('profile-crop-zoom-value');
  const applyButton = document.getElementById('profile-crop-apply');
  const cropStatus = document.getElementById('profile-crop-status');
  const photoStatus = document.getElementById('profile-photo-status');
  const context = canvas?.getContext('2d');

  if (!input || !dialog || !canvas || !context || !zoomInput || !applyButton) return;

  const previewTargets = [
    document.querySelector('.profile-photo-thumb'),
    document.querySelector('.profile-avatar'),
  ].filter(Boolean);
  const initialPreviews = previewTargets.map((element) =>
    Array.from(element.childNodes, (node) => node.cloneNode(true))
  );
  const cropSize = canvas.width;
  let image = null;
  let imageUrl = null;
  let imageFile = null;
  let baseScale = 1;
  let zoom = 1;
  let offsetX = 0;
  let offsetY = 0;
  let dragStart = null;
  let stagedFile = null;
  let stagedPreviewUrl = null;
  let cropWasApplied = false;

  function setStatus(message) {
    photoStatus.textContent = message;
  }

  function setCropError(message) {
    cropStatus.textContent = message;
  }

  function renderPreview(url) {
    previewTargets.forEach((element, index) => {
      if (!url) {
        element.replaceChildren(...initialPreviews[index].map((node) => node.cloneNode(true)));
        return;
      }

      const preview = document.createElement('img');
      preview.src = url;
      preview.alt = index === 0 ? 'Profile picture thumbnail' : 'Profile picture';
      element.replaceChildren(preview);
    });
  }

  function setInputFile(file) {
    try {
      const transfer = new DataTransfer();
      if (file) transfer.items.add(file);
      input.files = transfer.files;
      return true;
    } catch (error) {
      return false;
    }
  }

  function restoreStagedSelection() {
    if (stagedFile) {
      if (!setInputFile(stagedFile)) {
        stagedFile = null;
        if (stagedPreviewUrl) URL.revokeObjectURL(stagedPreviewUrl);
        stagedPreviewUrl = null;
        input.value = '';
        renderPreview(null);
        setStatus('Your browser could not restore the previous crop. Please choose the photo again.');
        return;
      }
      renderPreview(stagedPreviewUrl);
      return;
    }

    input.value = '';
    renderPreview(null);
  }

  function imageDimensions() {
    const scale = baseScale * zoom;
    return { width: image.naturalWidth * scale, height: image.naturalHeight * scale };
  }

  function keepImageInFrame() {
    const dimensions = imageDimensions();
    offsetX = Math.min(0, Math.max(cropSize - dimensions.width, offsetX));
    offsetY = Math.min(0, Math.max(cropSize - dimensions.height, offsetY));
  }

  function drawCrop() {
    if (!image) return;
    const dimensions = imageDimensions();
    context.clearRect(0, 0, cropSize, cropSize);
    context.drawImage(image, offsetX, offsetY, dimensions.width, dimensions.height);
  }

  function setZoom(nextZoom) {
    if (!image) return;
    const previous = imageDimensions();
    const centerX = (cropSize / 2 - offsetX) / previous.width;
    const centerY = (cropSize / 2 - offsetY) / previous.height;
    zoom = Number(nextZoom);
    const next = imageDimensions();
    offsetX = cropSize / 2 - centerX * next.width;
    offsetY = cropSize / 2 - centerY * next.height;
    keepImageInFrame();
    drawCrop();
    zoomValue.textContent = `${Math.round(zoom * 100)}%`;
  }

  function cleanupSourceImage() {
    image = null;
    imageFile = null;
    if (imageUrl) URL.revokeObjectURL(imageUrl);
    imageUrl = null;
  }

  function loadImage(file) {
    return new Promise((resolve, reject) => {
      const url = URL.createObjectURL(file);
      const nextImage = new Image();
      nextImage.onload = () => resolve({ image: nextImage, url });
      nextImage.onerror = () => {
        URL.revokeObjectURL(url);
        reject(new Error('This image could not be opened. Choose a JPG, PNG, or WEBP image.'));
      };
      nextImage.src = url;
    });
  }

  input.addEventListener('change', async () => {
    const selectedFile = input.files?.[0];
    if (!selectedFile) return;

    setStatus('');
    setCropError('');
    const allowedMime = ['', 'image/jpeg', 'image/png', 'image/webp'].includes(selectedFile.type);
    const allowedExtension = /\.(jpe?g|png|webp)$/i.test(selectedFile.name);
    if (!allowedMime || !allowedExtension) {
      restoreStagedSelection();
      setStatus('Choose a JPG, PNG, or WEBP image.');
      return;
    }

    try {
      const loaded = await loadImage(selectedFile);
      image = loaded.image;
      imageUrl = loaded.url;
      imageFile = selectedFile;
      baseScale = Math.max(cropSize / image.naturalWidth, cropSize / image.naturalHeight);
      zoom = 1;
      const dimensions = imageDimensions();
      offsetX = (cropSize - dimensions.width) / 2;
      offsetY = (cropSize - dimensions.height) / 2;
      zoomInput.value = '1';
      drawCrop();
      dialog.showModal();
      window.requestAnimationFrame(() => canvas.focus({ preventScroll: true }));
    } catch (error) {
      restoreStagedSelection();
      cleanupSourceImage();
      setStatus(error.message || 'This image could not be opened. Choose another photo.');
    }
  });

  zoomInput.addEventListener('input', () => setZoom(zoomInput.value));

  canvas.addEventListener('pointerdown', (event) => {
    if (!image) return;
    dragStart = { pointerId: event.pointerId, x: event.clientX, y: event.clientY, offsetX, offsetY };
    canvas.setPointerCapture(event.pointerId);
    canvas.classList.add('is-dragging');
    event.preventDefault();
  });

  canvas.addEventListener('pointermove', (event) => {
    if (!dragStart || dragStart.pointerId !== event.pointerId) return;
    const bounds = canvas.getBoundingClientRect();
    offsetX = dragStart.offsetX + (event.clientX - dragStart.x) * cropSize / bounds.width;
    offsetY = dragStart.offsetY + (event.clientY - dragStart.y) * cropSize / bounds.height;
    keepImageInFrame();
    drawCrop();
  });

  function stopDragging(event) {
    if (!dragStart || (event && dragStart.pointerId !== event.pointerId)) return;
    dragStart = null;
    canvas.classList.remove('is-dragging');
  }

  canvas.addEventListener('pointerup', stopDragging);
  canvas.addEventListener('pointercancel', stopDragging);
  canvas.addEventListener('lostpointercapture', stopDragging);
  canvas.addEventListener('keydown', (event) => {
    const movement = event.shiftKey ? 24 : 10;
    if (event.key === 'ArrowLeft') offsetX += movement;
    else if (event.key === 'ArrowRight') offsetX -= movement;
    else if (event.key === 'ArrowUp') offsetY += movement;
    else if (event.key === 'ArrowDown') offsetY -= movement;
    else return;
    event.preventDefault();
    keepImageInFrame();
    drawCrop();
  });

  document.querySelectorAll('[data-crop-cancel]').forEach((button) => {
    button.addEventListener('click', () => dialog.close());
  });

  dialog.addEventListener('click', (event) => {
    if (event.target === dialog) dialog.close();
  });

  dialog.addEventListener('close', () => {
    if (!cropWasApplied) {
      restoreStagedSelection();
      setStatus('Crop cancelled. Your previous selection is unchanged.');
    }
    cropWasApplied = false;
    cleanupSourceImage();
  });

  applyButton.addEventListener('click', () => {
    if (!image || !imageFile) return;
    setCropError('');
    const outputType = imageFile.type === 'image/png' ? 'image/png' : 'image/jpeg';
    try {
      canvas.toBlob((blob) => {
        if (!blob) {
          setCropError('The cropped photo could not be prepared. Please try another image.');
          return;
        }

        const extension = blob.type === 'image/png' ? 'png' : 'jpg';
        const croppedFile = new File([blob], `profile-picture.${extension}`, { type: blob.type, lastModified: Date.now() });
        if (!setInputFile(croppedFile)) {
          setCropError('Your browser could not prepare the cropped photo. Please try a current browser.');
          return;
        }

        if (stagedPreviewUrl) URL.revokeObjectURL(stagedPreviewUrl);
        stagedFile = croppedFile;
        stagedPreviewUrl = URL.createObjectURL(blob);
        renderPreview(stagedPreviewUrl);
        setStatus('Crop ready. Choose Save changes below to upload your photo.');
        cropWasApplied = true;
        dialog.close();
      }, outputType, 0.92);
    } catch (error) {
      setCropError('The cropped photo could not be prepared. Please try another image.');
    }
  });
})();
