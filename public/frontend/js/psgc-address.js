/**
 * Cascading Philippine address picker.
 *
 * Region -> Province -> City/Municipality -> Barangay dropdowns backed by the
 * free, public PSGC API (https://psgc.gitlab.io). No API key or account is
 * required. Values are submitted as plain names so they drop into the existing
 * address columns.
 *
 * Usage:
 *   window.initPsgcAddress({
 *     region: 'my_region', province: 'my_province',
 *     city: 'my_city', barangay: 'my_barangay',
 *     values: { region, province, city, barangay },
 *     placeholders: { region, province, city, barangay },
 *     regionFromProvince: false
 *   });
 */
(function () {
  'use strict';

  if (window.initPsgcAddress) {
    return;
  }

  var API = 'https://psgc.gitlab.io/api';
  var jsonCache = {};
  var regionsPromise = null;
  var provinceRegionPromise = null;

  function getJson(url) {
    if (!jsonCache[url]) {
      jsonCache[url] = fetch(url, { headers: { Accept: 'application/json' } })
        .then(function (response) {
          if (!response.ok) {
            throw new Error('HTTP ' + response.status);
          }
          return response.json();
        })
        .then(function (data) {
          return Array.isArray(data) ? data : [];
        })
        .catch(function () {
          return [];
        });
    }
    return jsonCache[url];
  }

  function getRegions() {
    if (!regionsPromise) {
      regionsPromise = getJson(API + '/regions/');
    }
    return regionsPromise;
  }

  function el(id) {
    if (!id) {
      return null;
    }
    return typeof id === 'string' ? document.getElementById(id) : id;
  }

  function fill(select, placeholder, items) {
    if (!select) {
      return;
    }
    var fragment = document.createDocumentFragment();
    var head = document.createElement('option');
    head.value = '';
    head.textContent = placeholder;
    fragment.appendChild(head);
    (items || []).forEach(function (item) {
      var option = document.createElement('option');
      option.value = item.name;
      option.textContent = item.name;
      if (item.code) {
        option.setAttribute('data-code', item.code);
      }
      if (item.kind) {
        option.setAttribute('data-kind', item.kind);
      }
      fragment.appendChild(option);
    });
    select.innerHTML = '';
    select.appendChild(fragment);
  }

  function ensure(select, value) {
    if (!select || !value) {
      return;
    }
    var exists = false;
    for (var i = 0; i < select.options.length; i++) {
      if (select.options[i].value === value) {
        exists = true;
        break;
      }
    }
    if (!exists) {
      var option = document.createElement('option');
      option.value = value;
      option.textContent = value;
      select.appendChild(option);
    }
    select.value = value;
  }

  function current(select) {
    return select && select.selectedIndex >= 0 ? select.options[select.selectedIndex] : null;
  }

  function currentCode(select) {
    var option = current(select);
    return option ? option.getAttribute('data-code') || '' : '';
  }

  function currentKind(select) {
    var option = current(select);
    return option ? option.getAttribute('data-kind') || '' : '';
  }

  function provinceRegionMap() {
    if (!provinceRegionPromise) {
      provinceRegionPromise = getRegions().then(function (regions) {
        return Promise.all(regions.map(function (region) {
          return getJson(API + '/regions/' + encodeURIComponent(region.code) + '/provinces')
            .then(function (provinces) {
              return provinces.map(function (province) {
                return { name: province.name, regionName: region.name };
              });
            });
        })).then(function (grouped) {
          var map = {};
          grouped.forEach(function (provinces) {
            provinces.forEach(function (province) {
              map[province.name.toLowerCase()] = province.regionName;
            });
          });
          return map;
        });
      });
    }
    return provinceRegionPromise;
  }

  window.initPsgcAddress = function (cfg) {
    var regionSel = el(cfg.region);
    var provSel = el(cfg.province);
    var citySel = el(cfg.city);
    var brgySel = el(cfg.barangay);
    if (!regionSel || !provSel || !citySel) {
      return;
    }

    var values = cfg.values || {};
    var provided = cfg.placeholders || {};
    var placeholders = {
      region: provided.region || 'Select region...',
      province: provided.province || 'Select province...',
      city: provided.city || 'Select city / municipality...',
      barangay: provided.barangay || 'Select barangay...'
    };

    function clearCity() {
      fill(citySel, placeholders.city, []);
      citySel.disabled = true;
    }

    function clearBarangay() {
      if (!brgySel) {
        return;
      }
      fill(brgySel, placeholders.barangay, []);
      brgySel.disabled = true;
    }

    function loadProvinces(regionCode) {
      fill(provSel, 'Loading...', []);
      provSel.disabled = true;
      clearCity();
      clearBarangay();
      return Promise.all([
        getJson(API + '/regions/' + encodeURIComponent(regionCode) + '/provinces'),
        getJson(API + '/regions/' + encodeURIComponent(regionCode) + '/cities')
      ]).then(function (result) {
        var provinces = result[0] || [];
        if (provinces.length) {
          fill(provSel, placeholders.province, provinces.map(function (province) {
            return { name: province.name, code: province.code, kind: 'province' };
          }));
        } else {
          fill(provSel, placeholders.city, (result[1] || []).map(function (city) {
            return { name: city.name, code: city.code, kind: 'city' };
          }));
        }
        provSel.disabled = false;
      });
    }

    function loadCities(provinceCode) {
      fill(citySel, 'Loading...', []);
      citySel.disabled = true;
      clearBarangay();
      return getJson(API + '/provinces/' + encodeURIComponent(provinceCode) + '/cities-municipalities')
        .then(function (items) {
          fill(citySel, placeholders.city, items.map(function (municipality) {
            return { name: municipality.name, code: municipality.code };
          }));
          citySel.disabled = false;
        });
    }

    function loadBarangays(cityCode) {
      if (!brgySel) {
        return Promise.resolve();
      }
      fill(brgySel, 'Loading...', []);
      brgySel.disabled = true;
      return getJson(API + '/cities-municipalities/' + encodeURIComponent(cityCode) + '/barangays')
        .then(function (items) {
          fill(brgySel, placeholders.barangay, items.map(function (barangay) {
            return { name: barangay.name, code: barangay.code };
          }));
          brgySel.disabled = false;
        });
    }

    function handleProvinceChange() {
      var kind = currentKind(provSel);
      var code = currentCode(provSel);
      clearCity();
      clearBarangay();
      if (!code) {
        return Promise.resolve();
      }
      if (kind === 'city') {
        // Regions without provinces (e.g. NCR): the city is the city level.
        fill(citySel, placeholders.city, [{ name: provSel.value, code: code }]);
        citySel.value = provSel.value;
        citySel.disabled = false;
        return loadBarangays(code);
      }
      return loadCities(code);
    }

    regionSel.addEventListener('change', function () {
      var code = currentCode(regionSel);
      fill(provSel, placeholders.province, []);
      provSel.disabled = true;
      clearCity();
      clearBarangay();
      if (code) {
        loadProvinces(code);
      }
    });

    provSel.addEventListener('change', function () {
      handleProvinceChange();
    });

    citySel.addEventListener('change', function () {
      clearBarangay();
      var code = currentCode(citySel);
      if (code) {
        loadBarangays(code);
      }
    });

    function restore() {
      return getRegions().then(function (regions) {
        fill(regionSel, placeholders.region, regions.map(function (region) {
          return { name: region.name, code: region.code };
        }));
        if (values.region) {
          ensure(regionSel, values.region);
        }
        if (!currentCode(regionSel) && cfg.regionFromProvince && values.province) {
          return provinceRegionMap().then(function (map) {
            var resolved = map[String(values.province).toLowerCase()];
            if (resolved) {
              ensure(regionSel, resolved);
            }
          });
        }
      }).then(function () {
        var regionCode = currentCode(regionSel);
        if (!regionCode) {
          return undefined;
        }
        return loadProvinces(regionCode).then(function () {
          if (!values.province) {
            return undefined;
          }
          ensure(provSel, values.province);
          var provinceCode = currentCode(provSel);
          if (!provinceCode) {
            return undefined;
          }
          if (currentKind(provSel) === 'city') {
            fill(citySel, placeholders.city, [{ name: values.province, code: provinceCode }]);
            citySel.value = values.province;
            citySel.disabled = false;
            return loadBarangays(provinceCode).then(function () {
              if (values.barangay) {
                ensure(brgySel, values.barangay);
              }
            });
          }
          return loadCities(provinceCode).then(function () {
            if (values.city) {
              ensure(citySel, values.city);
            }
            var cityCode = currentCode(citySel);
            if (!cityCode) {
              return undefined;
            }
            return loadBarangays(cityCode).then(function () {
              if (values.barangay) {
                ensure(brgySel, values.barangay);
              }
            });
          });
        });
      }).then(function () {
        // Safety net: never drop a saved value, even when it is not part of
        // the current PSGC dataset. Values that exist must stay submittable.
        if (values.province) {
          ensure(provSel, values.province);
        }
        if (values.city) {
          ensure(citySel, values.city);
        }
        if (brgySel && values.barangay) {
          ensure(brgySel, values.barangay);
        }
        if (provSel.value) {
          provSel.disabled = false;
        }
        if (citySel.value) {
          citySel.disabled = false;
        }
        if (brgySel && brgySel.value) {
          brgySel.disabled = false;
        }
      });
    }

    restore();
  };
})();
