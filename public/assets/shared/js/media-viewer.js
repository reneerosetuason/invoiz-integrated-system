/* Shared, permission-preserving in-page image/document previews. */
(() => {
  if (window.invoizMediaViewerReady) return;
  window.invoizMediaViewerReady = true;
  let dialog, stage, title, zoom, opener, previousOverflow, blobUrl, pending;
  function clear() {
    pending?.abort(); pending = null;
    stage.replaceChildren(); stage.classList.remove('is-zoomed');
    if (blobUrl) URL.revokeObjectURL(blobUrl);
    blobUrl = null; zoom.hidden = true; zoom.textContent = 'Zoom';
  }
  function init() {
    if (dialog) return;
    dialog = document.createElement('dialog'); dialog.className = 'media-viewer';
    dialog.setAttribute('aria-labelledby', 'media-viewer-title');
    dialog.innerHTML = '<header><h2 id="media-viewer-title">Image preview</h2><button type="button" data-zoom hidden>Zoom</button><button type="button" data-close aria-label="Close image preview">Close</button></header><div class="media-viewer-stage"></div>';
    document.body.append(dialog);
    stage = dialog.querySelector('.media-viewer-stage'); title = dialog.querySelector('h2'); zoom = dialog.querySelector('[data-zoom]');
    dialog.querySelector('[data-close]').addEventListener('click', () => dialog.close());
    zoom.addEventListener('click', () => { zoom.textContent = stage.classList.toggle('is-zoomed') ? 'Fit' : 'Zoom'; });
    dialog.addEventListener('click', e => { if (e.target === dialog) dialog.close(); });
    dialog.addEventListener('close', () => {
      clear(); document.body.style.overflow = previousOverflow;
      if (opener?.isConnected) opener.focus({preventScroll:true});
    });
  }
  function status(text) {
    const p = document.createElement('p'); p.className = 'media-viewer-status'; p.setAttribute('role', 'status'); p.textContent = text; stage.replaceChildren(p);
  }
  function showImage(url) {
    const img = new Image(); img.alt = title.textContent;
    img.onload = () => { if (dialog.open && stage.contains(img)) zoom.hidden = false; };
    img.onerror = () => { if (dialog.open && stage.contains(img)) status('This image could not be loaded. Close the preview and try again.'); };
    stage.replaceChildren(img); img.src = url;
  }
  async function show(trigger, url, documentPreview) {
    init(); opener = trigger; clear();
    title.textContent = trigger.dataset.imageTitle || trigger.querySelector('img')?.alt || trigger.getAttribute('aria-label') || trigger.textContent.trim() || 'Image preview';
    previousOverflow = document.body.style.overflow; document.body.style.overflow = 'hidden';
    dialog.showModal();
    if (!documentPreview) { showImage(url); return; }
    status('Loading preview…');
    const controller = new AbortController(); pending = controller;
    try {
      const response = await fetch(url, {credentials:'same-origin', signal:controller.signal});
      if (!response.ok) throw new Error('Unavailable');
      const blob = await response.blob();
      if (controller.signal.aborted || !dialog.open) return;
      if (!blob.type.startsWith('image/') && blob.type !== 'application/pdf') throw new Error('Unsupported preview');
      blobUrl = URL.createObjectURL(blob);
      if (blob.type.startsWith('image/')) showImage(blobUrl);
      else { const frame = document.createElement('iframe'); frame.title = title.textContent; frame.src = blobUrl; stage.replaceChildren(frame); }
    } catch (e) { if (e.name !== 'AbortError' && dialog.open) status('This file cannot be previewed, or you no longer have access to it.'); }
  }
  document.addEventListener('click', e => {
    const trigger = e.target.closest('[data-image-viewer], a[href]');
    if (!trigger || trigger.hasAttribute('download') || trigger.closest('.media-viewer')) return;
    const url = trigger.getAttribute('href') || trigger.dataset.imageSrc || (trigger.tagName === 'IMG' ? trigger.currentSrc || trigger.src : null);
    if (!url) return;
    const explicit = trigger.hasAttribute('data-image-viewer');
    const address = new URL(url, location.href);
    if (!['http:', 'https:', 'blob:'].includes(address.protocol)) return;
    const imageFile = /\.(avif|png|jpe?g|gif|webp|bmp|svg)$/i.test(address.pathname);
    if (!explicit && !imageFile) return;
    e.preventDefault(); e.stopImmediatePropagation();
    show(trigger, address.href, trigger.dataset.imageViewer === 'document' || /\.pdf$/i.test(address.pathname));
  }, true);
  document.addEventListener('keydown', e => {
    if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('img[data-image-viewer]')) { e.preventDefault(); e.target.click(); }
  });
})();
