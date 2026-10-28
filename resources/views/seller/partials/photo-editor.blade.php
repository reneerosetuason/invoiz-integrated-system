<link rel="stylesheet" href="{{ asset('assets/seller/css/partials/photo-editor.css') }}">

<dialog id="seller-photo-dialog" class="seller-photo-dialog" aria-labelledby="seller-photo-title">
    <div class="seller-photo-editor">
        <header class="seller-photo-editor-head">
            <div>
                <h2 id="seller-photo-title">Adjust photo</h2>
                <p>Drag to position, zoom in, or rotate before saving.</p>
            </div>
            <button type="button" class="seller-photo-editor-close" data-photo-close aria-label="Cancel photo editing">&times;</button>
        </header>
        <div class="seller-photo-editor-main">
            <div class="seller-photo-editor-stage" data-photo-stage>
                <canvas data-photo-canvas aria-label="Photo crop preview"></canvas>
            </div>
            <div class="seller-photo-editor-controls">
                <div class="seller-photo-editor-aspect" data-photo-aspect-control>
                    <label class="seller-photo-editor-control-label" for="seller-photo-aspect">Crop shape</label>
                    <select id="seller-photo-aspect" data-photo-aspect-select>
                        <option value="1">Square</option>
                        <option value="1.333333">Landscape · 4:3</option>
                        <option value="0.75">Portrait · 3:4</option>
                    </select>
                </div>
                <div class="seller-photo-editor-zoom">
                    <label class="seller-photo-editor-control-label" for="seller-photo-zoom">
                        <span>Zoom</span><output data-photo-zoom-value>1×</output>
                    </label>
                    <input id="seller-photo-zoom" type="range" min="1" max="3" step="0.01" value="1" data-photo-zoom>
                </div>
                <div class="seller-photo-editor-rotate">
                    <button type="button" data-photo-rotate="-90">Rotate left</button>
                    <button type="button" data-photo-rotate="90">Rotate right</button>
                    <button type="button" data-photo-reset>Reset</button>
                </div>
            </div>
            <p class="seller-photo-editor-hint" data-photo-hint>Move the photo inside the frame to choose what stays in the crop.</p>
            <p class="seller-photo-editor-error" data-photo-error role="alert"></p>
        </div>
        <footer class="seller-photo-editor-foot">
            <span class="seller-photo-editor-count" data-photo-count aria-live="polite"></span>
            <div class="seller-photo-editor-actions">
                <button type="button" data-photo-close>Cancel</button>
                <button type="button" data-photo-apply>Apply crop</button>
            </div>
        </footer>
    </div>
</dialog>
<script src="{{ asset('assets/seller/js/seller-photo-editor.js') }}?v={{ filemtime(public_path('assets/seller/js/seller-photo-editor.js')) }}" defer></script>
