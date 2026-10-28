// Full PSGC region list (no key needed). The server-rendered options
            // stay as the instant fallback; the complete national list replaces
            // them once loaded, preserving the current selection.
            (function () {
                function fillRegions() {
                    var sel = document.getElementById('region');
                    if (!sel) return;
                    fetch('https://psgc.gitlab.io/api/regions/', { headers: { Accept: 'application/json' } })
                        .then(function (res) { if (!res.ok) throw new Error('regions'); return res.json(); })
                        .then(function (list) {
                            if (!Array.isArray(list) || !list.length) return;
                            var cur = sel.value;
                            sel.querySelectorAll('option[data-psgc]').forEach(function (o) { o.remove(); });
                            list.forEach(function (rg) {
                                var o = document.createElement('option');
                                o.value = rg.code;
                                o.textContent = (rg.regionName ? rg.regionName + ' – ' : '') + rg.name;
                                o.setAttribute('data-psgc', '1');
                                if (rg.code === cur) o.selected = true;
                                sel.appendChild(o);
                            });
                        })
                        .catch(function () { /* keep the fallback options */ });
                }
                if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fillRegions);
                else fillRegions();
            })();
