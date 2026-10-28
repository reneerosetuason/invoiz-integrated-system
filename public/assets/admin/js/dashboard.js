var invoizData_067febb486 = JSON.parse(document.currentScript.dataset.config);

(function () {
    var dashboard = document.getElementById('adminDashboard');
    if (!dashboard) return;
    var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    function animateMetricValues() {
        if (prefersReducedMotion) return;
        dashboard.querySelectorAll('.sum-value').forEach(function (metric) {
            var textNode = Array.prototype.slice.call(metric.childNodes).find(function (node) {
                return node.nodeType === Node.TEXT_NODE && node.nodeValue.trim();
            });
            if (!textNode) return;
            var match = textNode.nodeValue.match(/^(\s*[^\d-]*)(-?[\d,]+(?:\.\d+)?)(\s*)$/);
            if (!match) return;
            var prefix = match[1], target = Number(match[2].replace(/,/g, '')), suffix = match[3];
            var decimals = (match[2].split('.')[1] || '').length;
            if (!Number.isFinite(target)) return;
            var start = performance.now(), duration = 680;
            function render(now) {
                var progress = Math.min(1, (now - start) / duration);
                var value = progress === 1 ? target : target * (1 - Math.pow(1 - progress, 3));
                textNode.nodeValue = prefix + value.toLocaleString('en-US', {minimumFractionDigits: decimals, maximumFractionDigits: decimals}) + suffix;
                if (progress < 1) requestAnimationFrame(render);
            }
            textNode.nodeValue = prefix + (0).toLocaleString('en-US', {minimumFractionDigits: decimals, maximumFractionDigits: decimals}) + suffix;
            requestAnimationFrame(render);
        });
    }
    var metricCards = dashboard.querySelector('.cards');
    if (metricCards && window.IntersectionObserver) {
        var metricObserver = new IntersectionObserver(function (entries) {
            if (entries.some(function (entry) { return entry.isIntersecting; })) {
                animateMetricValues();
                metricObserver.disconnect();
            }
        }, {threshold: .15});
        metricObserver.observe(metricCards);
    } else {
        animateMetricValues();
    }

    var filterButtons = dashboard.querySelectorAll('[data-status-filter]');
    var recentRows = Array.prototype.slice.call(dashboard.querySelectorAll('.recent-order'));
    var filterEmpty = dashboard.querySelector('.filter-empty');
    var filterToolbar = dashboard.querySelector('#orderFilterToolbar');
    var filterSummary = dashboard.querySelector('#orderFilterSummary');
    var clearFilterButton = dashboard.querySelector('#clearOrderFilter');
    var donutSlices = dashboard.querySelectorAll('.donut-slice[data-order-status]');
    var activeStatus = null;
    function applyStatusFilter(status, label) {
        activeStatus = status;
        var visibleCount = 0;
        recentRows.forEach(function (row) {
            var visible = !status || row.getAttribute('data-order-status') === status;
            row.hidden = !visible;
            if (visible) {
                visibleCount++;
                row.classList.remove('is-entering');
                void row.offsetWidth;
                row.classList.add('is-entering');
            }
        });
        if (filterEmpty) filterEmpty.hidden = !status || visibleCount > 0 || recentRows.length === 0;
        if (filterToolbar) filterToolbar.hidden = !status;
        if (filterSummary && status) filterSummary.textContent = 'Showing ' + label + ': ' + visibleCount + ' recent order' + (visibleCount === 1 ? '' : 's');
        filterButtons.forEach(function (button) {
            var selected = button.getAttribute('data-status-filter') === status;
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
            button.classList.toggle('is-active', selected);
        });
        donutSlices.forEach(function (slice) {
            var selected = slice.getAttribute('data-order-status') === status;
            slice.classList.toggle('is-muted', Boolean(status) && !selected);
            slice.classList.toggle('is-active', Boolean(status) && selected);
        });
    }
    filterButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var status = button.getAttribute('data-status-filter');
            var labelNode = button.querySelector('.legend-label');
            var label = labelNode ? labelNode.textContent.trim() : status;
            applyStatusFilter(activeStatus === status ? null : status, label);
        });
    });
    if (clearFilterButton) clearFilterButton.addEventListener('click', function () { applyStatusFilter(null, ''); });

    var salesChart = document.getElementById('salesChart');
    var salesSeries = invoizData_067febb486[0];
    if (!salesChart || !salesSeries.length) return;
    var ns = 'http://www.w3.org/2000/svg';
    var mobile = window.matchMedia('(max-width: 720px)');
    var chartFrame = salesChart.parentElement;
    var tooltip = dashboard.querySelector('#salesChartTooltip');
    var pointNodes = [];
    var activePoint = null;
    var hasDrawn = false;
    function createSvgNode(name, attributes, text) {
        var node = document.createElementNS(ns, name);
        Object.keys(attributes || {}).forEach(function (key) { node.setAttribute(key, attributes[key]); });
        if (text !== undefined) node.textContent = text;
        return node;
    }
    function axisLabel(value) {
        if (value >= 1000) return (value / 1000).toLocaleString('en-US', {minimumFractionDigits: 1, maximumFractionDigits: 1}) + 'k';
        return Math.round(value).toLocaleString('en-US');
    }
    function drawSalesChart() {
        var width = Math.max(180, Math.round(salesChart.getBoundingClientRect().width));
        var height = mobile.matches ? 220 : 280;
        var pad = {left: 70, right: 14, top: 16, bottom: 34};
        var plotWidth = Math.max(1, width - pad.left - pad.right);
        var plotHeight = height - pad.top - pad.bottom;
        var max = Math.max(1, Math.max.apply(null, salesSeries.map(function (point) { return Number(point.value); })));
        var pts = salesSeries.map(function (point, index) {
            return {x: pad.left + (salesSeries.length === 1 ? 0 : index / (salesSeries.length - 1) * plotWidth), y: pad.top + plotHeight - Number(point.value) / max * plotHeight, label: point.label, value: Number(point.value)};
        });
        salesChart.setAttribute('viewBox', '0 0 ' + width + ' ' + height);
        salesChart.setAttribute('height', height);
        salesChart.replaceChildren();
        pointNodes = [];
        [0, .25, .5, .75, 1].forEach(function (fraction) {
            var y = pad.top + plotHeight * fraction;
            var value = max * (1 - fraction);
            salesChart.appendChild(createSvgNode('text', {class: 'sales-tick', x: pad.left - 9, y: y + 4, 'text-anchor': 'end'}, '₱' + axisLabel(value)));
        });
        var labelCount = Math.min(5, Math.max(2, Math.floor(plotWidth / 48) + 1));
        var labelIndexes = [];
        for (var labelIndex = 0; labelIndex < labelCount; labelIndex++) {
            var pointIndex = Math.round(labelIndex * (pts.length - 1) / (labelCount - 1));
            if (labelIndexes.indexOf(pointIndex) < 0) labelIndexes.push(pointIndex);
        }
        labelIndexes.forEach(function (pointIndex) {
            var point = pts[pointIndex];
            salesChart.appendChild(createSvgNode('text', {class: 'sales-date-label', x: point.x, y: height - 8, 'text-anchor': pointIndex === 0 ? 'start' : (pointIndex === pts.length - 1 ? 'end' : 'middle')}, point.label));
        });
        var areaPoints = [pts[0].x + ',' + (pad.top + plotHeight)].concat(pts.map(function (point) { return point.x + ',' + point.y; })).concat([pts[pts.length - 1].x + ',' + (pad.top + plotHeight)]).join(' ');
        salesChart.appendChild(createSvgNode('polygon', {class: 'sales-area', points: areaPoints, 'aria-hidden': 'true'}));
        var line = createSvgNode('polyline', {class: 'sales-line', points: pts.map(function (point) { return point.x + ',' + point.y; }).join(' '), 'aria-hidden': 'true'});
        salesChart.appendChild(line);
        pts.forEach(function (point, index) {
            var marker = createSvgNode('circle', {class: 'sales-point', cx: point.x, cy: point.y, r: 3, 'aria-hidden': 'true', 'data-point-index': index});
            salesChart.appendChild(marker);
            pointNodes.push(marker);
        });
        if (!hasDrawn && !prefersReducedMotion && line.getTotalLength) {
            var lineLength = line.getTotalLength();
            line.style.strokeDasharray = lineLength;
            line.style.strokeDashoffset = lineLength;
            requestAnimationFrame(function () { line.style.strokeDashoffset = '0'; });
        }
        hasDrawn = true;
        if (activePoint !== null) showPoint(activePoint, document.activeElement === salesChart);
    }

    function showPoint(index, keyboard) {
        if (!pointNodes.length || !tooltip) return;
        activePoint = Math.max(0, Math.min(pointNodes.length - 1, index));
        pointNodes.forEach(function (marker, markerIndex) { marker.classList.toggle('is-active', markerIndex === activePoint); });
        var point = salesSeries[activePoint];
        var amount = Number(point.value).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        tooltip.textContent = point.label + ' · ' + String.fromCharCode(0x20B1) + amount;
        tooltip.classList.add('is-visible');
        tooltip.setAttribute('aria-hidden', keyboard ? 'false' : 'true');
        var markerRect = pointNodes[activePoint].getBoundingClientRect();
        var frameRect = chartFrame.getBoundingClientRect();
        var left = markerRect.left + markerRect.width / 2 - frameRect.left;
        tooltip.style.left = Math.max(80, Math.min(frameRect.width - 80, left)) + 'px';
        tooltip.style.top = Math.max(34, markerRect.top + markerRect.height / 2 - frameRect.top) + 'px';
        salesChart.setAttribute('aria-label', 'Sales on ' + point.label + ': ' + String.fromCharCode(0x20B1) + amount + '. Use the left and right arrow keys to inspect daily values.');
    }

    function hideTooltip() {
        if (tooltip) {
            tooltip.classList.remove('is-visible');
            tooltip.setAttribute('aria-hidden', 'true');
            tooltip.textContent = '';
        }
        pointNodes.forEach(function (marker) { marker.classList.remove('is-active'); });
    }

    function inspectNearestPoint(clientX) {
        var nearestIndex = 0, nearestDistance = Infinity;
        pointNodes.forEach(function (marker, index) {
            var rect = marker.getBoundingClientRect();
            var distance = Math.abs(clientX - (rect.left + rect.width / 2));
            if (distance < nearestDistance) { nearestDistance = distance; nearestIndex = index; }
        });
        showPoint(nearestIndex, false);
    }

    salesChart.addEventListener('pointermove', function (event) { if (event.pointerType !== 'touch') inspectNearestPoint(event.clientX); });
    salesChart.addEventListener('pointerdown', function (event) { if (event.pointerType === 'touch') inspectNearestPoint(event.clientX); });
    salesChart.addEventListener('pointerleave', function (event) { if (event.pointerType !== 'touch' && document.activeElement !== salesChart) hideTooltip(); });
    salesChart.addEventListener('focus', function () { showPoint(activePoint === null ? 0 : activePoint, true); });
    salesChart.addEventListener('blur', hideTooltip);
    salesChart.addEventListener('keydown', function (event) {
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
        event.preventDefault();
        var nextPoint = activePoint === null ? 0 : activePoint + (event.key === 'ArrowRight' ? 1 : -1);
        showPoint(Math.max(0, Math.min(salesSeries.length - 1, nextPoint)), true);
    });
    document.addEventListener('pointerdown', function (event) { if (event.pointerType === 'touch' && !chartFrame.contains(event.target)) hideTooltip(); });

    var resizeObserver = window.ResizeObserver ? new ResizeObserver(drawSalesChart) : null;
    if (resizeObserver) resizeObserver.observe(salesChart.parentElement);
    window.addEventListener('resize', drawSalesChart, {passive: true});
    if (mobile.addEventListener) mobile.addEventListener('change', drawSalesChart);
    else if (mobile.addListener) mobile.addListener(drawSalesChart);
    drawSalesChart();
})();
