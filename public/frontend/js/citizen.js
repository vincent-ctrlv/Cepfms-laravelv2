/**
 * CEPFMS — Citizen submission form behavior
 * Location pin map (San Jose del Monte only), drag-and-drop photo uploads,
 * character counter, inline validation, local draft autosave/restore, async submit.
 */
(() => {
  const form = document.getElementById('submission-form');
  if (!form) return;

  const MAX_FILES = 5;
  let selectedFiles = [];
  let formDirty = false;

  // ---- Barangay: typed with suggestions, snapped to the official name ----
  const barangayInput = form.querySelector('[name="barangay"]');
  const streetInput = form.querySelector('[name="street"]');
  const latInput = document.getElementById('latitude');
  const lngInput = document.getElementById('longitude');
  const BARANGAYS = [...document.querySelectorAll('#barangay-list option')].map(o => o.value);
  const norm = (v) => (v || '').toLowerCase()
    .replace(/[–—]/g, '-').replace(/\s*-\s*/g, '-')
    .replace(/ñ/g, 'n').replace(/^(barangay|brgy\.?)\s+/, '').trim();

  /** Official name for what the citizen typed ("brgy. muzon  south" → "Muzon South"), or null. */
  function canonicalBarangay(v) {
    const n = norm(v);
    return n ? BARANGAYS.find(b => norm(b) === n) || null : null;
  }

  // ---- Location pin map (San Jose del Monte only) --------------------------
  // The map lives in the (initially hidden) Select Location dialog; mapApi lets
  // the dialog code resize it on open and put the pin back on Cancel.
  let mapApi = null;
  const mapEl = document.getElementById('location-map');
  if (mapEl && window.L) {
    const clearPinBtn = document.getElementById('clear-pin-btn');
    const mapHint = document.getElementById('location-map-hint');
    const defaultHint = mapHint.textContent;

    // Same outline the server checks against ([lng, lat] points).
    const ring = JSON.parse(mapEl.dataset.boundary || '[]');
    const outline = ring.map(([lng, lat]) => [lat, lng]);
    const cityBounds = L.latLngBounds(outline);

    function insideCity(lat, lng) {
      let inside = false;
      for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
        const [xi, yi] = ring[i];
        const [xj, yj] = ring[j];
        if ((yi > lat) !== (yj > lat) && lng < (xj - xi) * (lat - yi) / (yj - yi) + xi) inside = !inside;
      }
      return inside;
    }

    const map = L.map(mapEl, {
      maxBounds: cityBounds.pad(0.15),
      maxBoundsViscosity: 1.0,
      minZoom: 12,
      scrollWheelZoom: false,
    }).fitBounds(cityBounds);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
    }).addTo(map);

    L.polygon(outline, { color: '#2563eb', weight: 2, dashArray: '6 4', fillOpacity: 0.05, interactive: false }).addTo(map);

    let marker = null;
    let lastGood = null;

    function setHint(text, isError) {
      mapHint.textContent = text;
      mapHint.classList.toggle('text-red-600', Boolean(isError));
      mapHint.classList.toggle('text-slate', !isError);
    }

    /**
     * @param {boolean} fromAddress true when the pin comes from the typed
     *   barangay/street — then we don't reverse-lookup and overwrite what
     *   the citizen just typed.
     */
    function placePin(latlng, fromAddress = false) {
      if (!insideCity(latlng.lat, latlng.lng)) {
        setHint('That spot is outside San Jose del Monte. Please pin a location inside the outlined area.', true);
        if (marker) marker.setLatLng(lastGood); // snap a dragged pin back
        return;
      }
      lastGood = latlng;
      if (!marker) {
        marker = L.marker(latlng, { draggable: true, keyboard: true, title: 'Selected location' }).addTo(map);
        marker.on('dragend', () => placePin(marker.getLatLng()));
      } else {
        marker.setLatLng(latlng);
      }
      latInput.value = latlng.lat.toFixed(6);
      lngInput.value = latlng.lng.toFixed(6);
      clearPinBtn.classList.remove('hidden');
      formDirty = true;
      if (fromAddress) return;
      setHint('Pin placed. Looking up the address…', false);
      lookupAddress(latlng);
    }

    // ---- Fill Barangay + Street from the pin (OpenStreetMap Nominatim) ----
    let autoStreet = '';      // last street we filled in, so we never overwrite the citizen's own typing
    let lookupTimer = null;
    let lookupAbort = null;

    // In SJDM, OSM's "quarter" is the barangay (e.g. "Muzon South", "Mulawin")
    // and "suburb" the wider district, so try quarter first. A short name like
    // "Mulawin" also matches the full official name "Francisco Homes-Mulawin".
    function matchBarangay(addr) {
      for (const c of [addr.quarter, addr.suburb, addr.village, addr.neighbourhood].filter(Boolean)) {
        const n = norm(c);
        const exact = BARANGAYS.find(b => norm(b) === n);
        if (exact) return exact;
        const partOf = BARANGAYS.filter(b => norm(b).endsWith('-' + n));
        if (partOf.length === 1) return partOf[0];
      }
      return null;
    }

    // Nominatim allows ~1 request/second. Instead of a fixed delay on every
    // click, only wait out whatever is left of that second since the last call.
    let lastNominatimAt = 0;
    function nominatimWait() {
      return Math.max(0, lastNominatimAt + 1000 - Date.now());
    }
    // Re-dropping the pin on the same spot (e.g. a drag snapped back) reuses the answer.
    const reverseCache = new Map();

    function lookupAddress(latlng) {
      clearTimeout(lookupTimer);
      lookupAbort?.abort();
      const key = `${latlng.lat.toFixed(5)},${latlng.lng.toFixed(5)}`;
      lookupTimer = setTimeout(async () => {
        lookupAbort = new AbortController();
        try {
          let addr = reverseCache.get(key);
          if (!addr) {
            lastNominatimAt = Date.now();
            const url = `https://nominatim.openstreetmap.org/reverse?format=json&zoom=18&addressdetails=1&lat=${latlng.lat}&lon=${latlng.lng}`;
            const res = await fetch(url, { headers: { 'Accept-Language': 'en' }, signal: lookupAbort.signal });
            addr = (await res.json()).address || {};
            reverseCache.set(key, addr);
          }

          const brgy = matchBarangay(addr);
          if (brgy) {
            barangayInput.value = brgy;
            validateField('barangay');
          }
          const street = addr.road || addr.neighbourhood || addr.hamlet || '';
          if (street && (streetInput.value.trim() === '' || streetInput.value === autoStreet)) {
            streetInput.value = street;
            autoStreet = street;
          }
          saveDraft();
          setHint(brgy
            ? 'Barangay and street filled in from the pin — please check they are correct.'
            : 'Pin placed. We couldn\'t tell the barangay from this spot — please choose it above.', false);
        } catch (e) {
          if (e.name === 'AbortError') return;
          setHint('Pin placed. Address lookup is unavailable right now — please choose the barangay above.', false);
        }
      }, reverseCache.has(key) ? 0 : nominatimWait());
    }

    map.on('click', (e) => placePin(e.latlng));

    function clearPin() {
      marker?.remove();
      marker = null;
      lastGood = null;
      latInput.value = '';
      lngInput.value = '';
      clearPinBtn.classList.add('hidden');
      setHint(defaultHint, false);
    }
    clearPinBtn.addEventListener('click', clearPin);

    // ---- Locate the typed Barangay + Street on the map ---------------------
    // Only real typing/choosing fires these events; values the pin lookup
    // fills in programmatically don't, so the two directions can't loop.
    const b = cityBounds;
    const viewbox = `${b.getWest()},${b.getNorth()},${b.getEast()},${b.getSouth()}`;
    let searchTimer = null;
    let searchAbort = null;

    async function searchPlace(query) {
      searchAbort?.abort();
      searchAbort = new AbortController();
      const wait = nominatimWait();
      if (wait) await new Promise(r => setTimeout(r, wait));
      lastNominatimAt = Date.now();
      const url = `https://nominatim.openstreetmap.org/search?format=json&limit=5&countrycodes=ph&bounded=1&viewbox=${viewbox}&q=${encodeURIComponent(query)}`;
      const res = await fetch(url, { headers: { 'Accept-Language': 'en' }, signal: searchAbort.signal });
      const hits = await res.json();
      const hit = hits.find(h => insideCity(+h.lat, +h.lon));
      return hit ? L.latLng(+hit.lat, +hit.lon) : null;
    }

    function locateTypedAddress() {
      clearTimeout(searchTimer);
      const brgy = canonicalBarangay(barangayInput.value);
      const street = streetInput.value.trim();
      if (!brgy) return;
      searchTimer = setTimeout(async () => {
        try {
          if (street) {
            setHint('Finding this address on the map…', false);
            const spot = await searchPlace(`${street}, ${brgy}, San Jose del Monte, Bulacan`);
            if (spot) {
              map.setView(spot, 17);
              placePin(spot, true);
              setHint('Pin placed at the address you typed — drag it if the exact spot is different.', false);
              return;
            }
          }
          // No street, or the street wasn't found: show the barangay so the
          // citizen can tap the exact spot. No pin — a barangay's middle
          // isn't where the issue is.
          const area = await searchPlace(`${brgy}, San Jose del Monte, Bulacan`);
          if (area) map.setView(area, 16);
          setHint(street
            ? 'We couldn\'t find that street on the map. Tap the exact spot to place the pin.'
            : 'Showing your barangay. Tap the exact spot on the map to place a pin.', false);
        } catch (e) {
          if (e.name !== 'AbortError') setHint('Map search is unavailable right now — you can tap the map to place the pin.', false);
        }
      }, 900);
    }

    barangayInput.addEventListener('input', locateTypedAddress);
    streetInput.addEventListener('input', locateTypedAddress);

    // ---- Pin the citizen's current position (browser geolocation) ----------
    // A fix can arrive seconds after the tap; locateRun lets Cancel (or a
    // second tap) discard one that is still on its way.
    const locateBtn = L.DomUtil.create('button', 'sf-locate-btn');
    locateBtn.type = 'button';
    locateBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>Use my location';
    let locateRun = 0;
    // Only offered when the browser can share its location at all.
    if (navigator.geolocation) {
      const LocateControl = L.Control.extend({
        onAdd() {
          // A tap on the button must not also drop a pin on the map under it.
          L.DomEvent.disableClickPropagation(locateBtn);
          return locateBtn;
        },
      });
      new LocateControl({ position: 'topright' }).addTo(map);
      locateBtn.addEventListener('click', () => {
        const run = ++locateRun;
        locateBtn.disabled = true;
        setHint('Finding your location…', false);
        navigator.geolocation.getCurrentPosition((pos) => {
          if (run !== locateRun) return;
          locateBtn.disabled = false;
          const spot = L.latLng(pos.coords.latitude, pos.coords.longitude);
          if (!insideCity(spot.lat, spot.lng)) {
            setHint('Your current location is outside San Jose del Monte. Tap the map to place the pin instead.', true);
            return;
          }
          map.setView(spot, 17);
          placePin(spot);
        }, (err) => {
          if (run !== locateRun) return;
          locateBtn.disabled = false;
          setHint(err.code === err.PERMISSION_DENIED
            ? 'Location access is blocked. Allow it in your browser settings, or tap the map to place the pin.'
            : 'We couldn\'t get your location. Tap the map to place the pin.', true);
        }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
      });
    }

    mapApi = {
      // Leaflet can't measure a map inside a hidden dialog, so size it on open.
      show() {
        map.invalidateSize();
        if (marker) map.setView(marker.getLatLng(), Math.max(map.getZoom(), 16));
        else map.fitBounds(cityBounds);
      },
      // Cancel: drop pending lookups (they'd refill the fields) and restore the old pin.
      restore(lat, lng) {
        clearTimeout(lookupTimer); lookupAbort?.abort();
        clearTimeout(searchTimer); searchAbort?.abort();
        locateRun++;
        locateBtn.disabled = false;
        if (lat !== '' && lng !== '') placePin(L.latLng(+lat, +lng), true);
        else clearPin();
        setHint(defaultHint, false);
      },
    };
  }

  // ---- Description character counter -------------------------------------
  const descTextarea = document.getElementById('description');
  const descCounter = document.getElementById('description-counter');
  function updateDescCounter() {
    const len = descTextarea.value.length;
    if (descCounter) {
      descCounter.textContent = len < 10 ? `${len} / 10 characters minimum` : `${len} characters`;
      descCounter.classList.toggle('text-red-500', len > 0 && len < 10);
      descCounter.classList.toggle('text-slate', len === 0 || len >= 10);
    }
  }
  descTextarea?.addEventListener('input', updateDescCounter);
  updateDescCounter();

  // ---- Prefill from the homepage "Send a Suggestion" quick-start widget --
  const quickDraft = sessionStorage.getItem('cc-quick-suggestion');
  if (quickDraft && descTextarea) {
    descTextarea.value = quickDraft;
    updateDescCounter();
    sessionStorage.removeItem('cc-quick-suggestion');
  }

  // ---- Inline validation (complements native browser validation) ---------
  // Rejects obvious keyboard mashing. Mirrors SpamService::unreadableReason().
  function unreadableReason(text, minWords = 1) {
    const lower = text.toLowerCase();
    const words = lower.trim().split(/\s+/u).filter(w => /\p{L}{2,}|\p{N}/u.test(w));
    if (words.length < minWords) return 'too_few_words';
    if (/\p{L}{26,}/u.test(lower)) return 'long_word';
    if (/(\p{L})\1{4,}/u.test(lower)) return 'repeated_letters';
    if (/[b-df-hj-np-tv-xz]{7,}/u.test(lower)) return 'consonant_run';
    const letters = lower.replace(/[^\p{L}]+/gu, '');
    if (letters.length >= 10 && (letters.match(/[aeiouy]/g) || []).length / letters.length < 0.15) return 'no_vowels';
    return null;
  }

  const validators = {
    subject: (el) => {
      if (el.value.trim().length === 0) return 'Please enter a subject.';
      return !unreadableReason(el.value) || "This doesn't look readable. Please write your feedback in a few words.";
    },
    description: (el) => {
      if (el.value.trim().length < 10) return 'Please provide a bit more detail (at least 10 characters).';
      return !unreadableReason(el.value, 3) || "This doesn't look readable. Please write your feedback in a few real words (at least 3).";
    },
    barangay: (el) => {
      if (el.value.trim() === '') return 'Please enter the barangay where this is happening.';
      const name = canonicalBarangay(el.value);
      if (!name) return 'Please pick a barangay in San Jose del Monte from the suggestions.';
      el.value = name;
      return true;
    },
    full_name: (el) => el.value.trim().length > 0 || 'Please enter your full name.',
    email: (el) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(el.value.trim()) || 'Please enter a valid email address.',
    phone: (el) => /^(09|\+639)\d{9}$/.test(el.value.replace(/[\s\-()]/g, '')) || 'Please enter a valid mobile number (e.g. 09171234567).',
    consent: (el) => el.checked || 'Please agree to the Privacy Policy and Terms of Service to continue.',
  };

  function showFieldError(name, message) {
    const errEl = document.getElementById(`${name}-error`);
    const inputEl = form.querySelector(`[name="${name}"]`);
    if (errEl) {
      errEl.textContent = message || '';
      errEl.classList.toggle('hidden', !message);
    }
    inputEl?.classList.toggle('border-red-400', Boolean(message));
    // The barangay input sits in the (usually closed) location dialog, so its
    // error is also shown on the Location field in the form itself.
    if (name === 'barangay') {
      const locErr = document.getElementById('location-error');
      locErr.textContent = message || '';
      locErr.classList.toggle('hidden', !message);
      locTrigger.classList.toggle('border-red-400', Boolean(message));
    }
  }

  function validateField(name) {
    const el = form.querySelector(`[name="${name}"]`);
    const validator = validators[name];
    if (!el || !validator) return true;
    const result = validator(el);
    if (result === true) {
      showFieldError(name, null);
      return true;
    }
    showFieldError(name, result);
    return false;
  }

  Object.keys(validators).forEach(name => {
    const el = form.querySelector(`[name="${name}"]`);
    el?.addEventListener(el.type === 'checkbox' ? 'change' : 'blur', () => validateField(name));
  });

  // Photos live in selectedFiles (not a named input), so they're checked separately.
  function validatePhotos() {
    const ok = selectedFiles.length > 0;
    const errEl = document.getElementById('photos-error');
    errEl.textContent = ok ? '' : 'Please attach at least one photo.';
    errEl.classList.toggle('hidden', ok);
    document.getElementById('dropzone').classList.toggle('border-red-400', !ok);
    return ok;
  }

  function validateAll() {
    const fieldsOk = Object.keys(validators).map(validateField).every(Boolean);
    return validatePhotos() && fieldsOk;
  }

  // ---- Select Location dialog ---------------------------------------------
  // The form shows one "Location" field; tapping it opens the barangay, street
  // and map. Cancel puts everything back the way it was when the dialog opened.
  const locTrigger = document.getElementById('location-trigger');
  const locModal = document.getElementById('location-modal');
  const locSummary = document.getElementById('location-summary');
  const locSub = document.getElementById('location-sub');
  let locSnapshot = null;
  // Same test as the .sf-mouse-only / .sf-touch-only CSS.
  const pickVerb = matchMedia('(hover: hover) and (pointer: fine)').matches ? 'Click' : 'Tap';

  function updateLocationSummary() {
    const text = [streetInput.value.trim(), barangayInput.value.trim()].filter(Boolean).join(', ');
    const pinned = latInput.value !== '';
    locSummary.textContent = text || (pinned ? 'Pinned on map' : `${pickVerb} to select location`);
    locSummary.classList.toggle('is-empty', !text && !pinned);
    locSub.textContent = pinned ? 'Pinned on map' : 'No map pin';
    locSub.classList.toggle('hidden', !text);
  }

  function openLocation() {
    locSnapshot = { barangay: barangayInput.value, street: streetInput.value, lat: latInput.value, lng: lngInput.value };
    locModal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    mapApi?.show();
    locModal.querySelector('.sf-modal-panel').focus();
  }

  function closeLocation() {
    locModal.classList.add('hidden');
    document.body.style.overflow = '';
    locTrigger.focus();
  }

  function cancelLocation() {
    barangayInput.value = locSnapshot.barangay;
    streetInput.value = locSnapshot.street;
    mapApi?.restore(locSnapshot.lat, locSnapshot.lng);
    showFieldError('barangay', null);
    updateLocationSummary();
    closeLocation();
  }

  function saveLocation() {
    if (!validateField('barangay')) {
      barangayInput.focus();
      return;
    }
    updateLocationSummary();
    saveDraft();
    closeLocation();
  }

  updateLocationSummary(); // in case the browser kept values across a reload
  locTrigger.addEventListener('click', openLocation);
  document.getElementById('location-save').addEventListener('click', saveLocation);
  document.getElementById('location-cancel').addEventListener('click', cancelLocation);
  document.getElementById('location-close').addEventListener('click', cancelLocation);
  locModal.addEventListener('click', (e) => { if (e.target === locModal) cancelLocation(); });
  locModal.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { e.preventDefault(); cancelLocation(); }
    // Enter in the barangay/street fields would otherwise submit the whole form.
    if (e.key === 'Enter' && e.target.matches('input')) { e.preventDefault(); saveLocation(); }
  });

  // ---- Draft autosave / restore (localStorage) ----------------------------
  const DRAFT_KEY = 'civicconnect_draft_v1';
  const DRAFT_FIELDS = ['subject', 'description', 'category', 'priority', 'barangay', 'street', 'full_name', 'email', 'phone'];
  const bannerWrap = document.getElementById('draft-banner-wrap');

  function saveDraft() {
    const data = {};
    DRAFT_FIELDS.forEach(name => {
      const el = form.querySelector(`[name="${name}"]`);
      if (el) data[name] = el.value;
    });
    try { localStorage.setItem(DRAFT_KEY, JSON.stringify(data)); } catch (e) { /* storage unavailable, ignore */ }
  }

  function clearDraft() {
    try { localStorage.removeItem(DRAFT_KEY); } catch (e) { /* ignore */ }
  }

  function restoreDraft(data) {
    DRAFT_FIELDS.forEach(name => {
      const el = form.querySelector(`[name="${name}"]`);
      if (el && data[name]) el.value = data[name];
    });
    updateDescCounter();
    updateLocationSummary();
  }

  function offerDraftRestore() {
    let saved;
    try { saved = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch (e) { saved = null; }
    if (!saved || !saved.description) return;

    const banner = document.createElement('div');
    banner.className = 'card p-3 mb-4 flex flex-wrap items-center justify-between gap-2 text-xs';
    banner.innerHTML = `
      <span class="text-navy">You have an unsaved draft from earlier. Restore it?</span>
      <span class="flex gap-3">
        <button type="button" id="restore-draft-btn" class="text-teal font-semibold hover:underline">Restore</button>
        <button type="button" id="discard-draft-btn" class="text-slate hover:underline">Discard</button>
      </span>
    `;
    bannerWrap.appendChild(banner);
    document.getElementById('restore-draft-btn').addEventListener('click', () => {
      restoreDraft(saved);
      banner.remove();
      CivicConnect.toast('Draft restored.', 'info', 2500);
    });
    document.getElementById('discard-draft-btn').addEventListener('click', () => {
      clearDraft();
      banner.remove();
    });
  }
  offerDraftRestore();

  let draftTimer;
  form.addEventListener('input', () => {
    formDirty = true;
    clearTimeout(draftTimer);
    draftTimer = setTimeout(saveDraft, 500);
  });

  // Warn before leaving the page if there's unsaved input.
  window.addEventListener('beforeunload', (e) => {
    if (formDirty) {
      e.preventDefault();
      e.returnValue = '';
    }
  });

  // ---- Drag & drop photo upload (keyboard-accessible) ---------------------
  const dropzone     = document.getElementById('dropzone');
  const fileInput    = document.getElementById('photo-input');
  const previewsWrap = document.getElementById('photo-previews');
  const prompt       = document.getElementById('dropzone-prompt');
  const addMoreBar   = document.getElementById('dropzone-add-more');

  // Click anywhere on the dropzone (except the add-more bar and remove btns) → open picker
  dropzone.addEventListener('click', (e) => {
    if (e.target.closest('#dropzone-add-more') || e.target.closest('.remove-photo')) return;
    // The hidden file inputs live inside the dropzone, so their own .click()
    // bubbles up here. Reopening the gallery then would replace the camera
    // that "Take a photo" just opened.
    if (e.target.matches('input[type="file"]') || e.target.closest('#camera-btn')) return;
    fileInput.click();
  });
  // "add more photos" bar click (propagates up but we intercept it here too)
  addMoreBar.addEventListener('click', (e) => { e.stopPropagation(); fileInput.click(); });

  dropzone.addEventListener('keydown', (e) => {
    if (e.target !== dropzone) return; // let buttons inside handle their own keys
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click(); }
  });

  // "Take a photo": opens the rear camera on phones (a normal file picker on desktop).
  const cameraInput = document.getElementById('camera-input');
  document.getElementById('camera-btn')?.addEventListener('click', (e) => {
    e.stopPropagation(); // don't also open the regular picker
    cameraInput.click();
  });
  cameraInput?.addEventListener('change', (e) => {
    addFiles(e.target.files);
    cameraInput.value = ''; // allow taking another photo
  });
  ['dragenter', 'dragover'].forEach(evt =>
    dropzone.addEventListener(evt, (e) => { e.preventDefault(); dropzone.classList.add('dragover'); })
  );
  ['dragleave', 'drop'].forEach(evt =>
    dropzone.addEventListener(evt, (e) => { e.preventDefault(); dropzone.classList.remove('dragover'); })
  );
  dropzone.addEventListener('drop', (e) => addFiles(e.dataTransfer.files));
  fileInput.addEventListener('change', (e) => addFiles(e.target.files));

  function addFiles(fileList) {
    const incoming = Array.from(fileList);
    if (selectedFiles.length + incoming.length > MAX_FILES) {
      CivicConnect.toast(`You may upload a maximum of ${MAX_FILES} images.`, 'warning');
    }
    for (const file of incoming) {
      if (selectedFiles.length >= MAX_FILES) break;
      if (!file.type.startsWith('image/')) {
        CivicConnect.toast(`${file.name} is not a supported image type.`, 'error');
        continue;
      }
      if (file.size > 5 * 1024 * 1024) {
        CivicConnect.toast(`${file.name} exceeds the 5MB limit.`, 'error');
        continue;
      }
      selectedFiles.push(file);
    }
    formDirty = true;
    renderPreviews();
    if (selectedFiles.length) validatePhotos();
  }

  function renderPreviews() {
    const hasFiles = selectedFiles.length > 0;

    // Toggle prompt vs. previews grid
    prompt.classList.toggle('hidden', hasFiles);
    previewsWrap.classList.toggle('hidden', !hasFiles);

    // Show "add more" strip only when there's room for more photos
    addMoreBar.classList.toggle('hidden', !hasFiles || selectedFiles.length >= MAX_FILES);

    previewsWrap.innerHTML = '';
    selectedFiles.forEach((file, idx) => {
      const url  = URL.createObjectURL(file);
      const item = document.createElement('div');
      item.className = 'relative rounded overflow-hidden border border-borderc aspect-square';
      item.innerHTML = `
        <img src="${url}" class="w-full h-full object-cover" alt="Preview of ${CivicConnect.escapeHtml(file.name)}">
        <button type="button" data-idx="${idx}" aria-label="Remove ${CivicConnect.escapeHtml(file.name)}" class="remove-photo absolute top-1 right-1 bg-navy/80 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs leading-none">&times;</button>
      `;
      previewsWrap.appendChild(item);
    });
    previewsWrap.querySelectorAll('.remove-photo').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation(); // don't fire the dropzone click
        selectedFiles.splice(Number(btn.dataset.idx), 1);
        renderPreviews();
      });
    });
  }

  // ---- Submit -------------------------------------------------------------
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    if (!validateAll()) {
      CivicConnect.toast('Please fix the highlighted fields before submitting.', 'warning');
      const firstError = form.querySelector('.border-red-400');
      firstError?.focus();
      return;
    }

    const btn = document.getElementById('submit-btn');
    const btnHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<span class="inline-flex items-center justify-center gap-2">
      <svg class="animate-spin" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".25"/><path d="M22 12a10 10 0 00-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
      Submitting…</span>`;
    const overlay = showSubmitOverlay();

    const fd = new FormData(form);
    fd.delete('photos[]');
    selectedFiles.forEach(file => fd.append('photos[]', file));
    fd.append('csrf_token', CivicConnect.csrfToken());

    const result = await CivicConnect.apiFetch(`${window.APP_URL || ''}/api/submissions/create`, {
      method: 'POST',
      body: fd,
    });

    if (result.success) {
      formDirty = false;
      clearDraft();
      window.location.href = `${window.APP_URL || ''}/citizen/success?ref=${encodeURIComponent(result.data.reference_number)}`;
    } else {
      overlay.remove();
      btn.disabled = false;
      btn.innerHTML = btnHtml;
    }
  });

  /**
   * Full-screen "please wait" layer while the submission is sent and
   * classified (AI classification can take several seconds). Stays up on
   * success until the redirect to the confirmation page happens.
   */
  function showSubmitOverlay() {
    const el = document.createElement('div');
    el.className = 'fixed inset-0 z-50 flex items-center justify-center bg-navy/40 backdrop-blur-sm px-4';
    el.setAttribute('role', 'status');
    el.setAttribute('aria-live', 'polite');
    el.innerHTML = `
      <div class="bg-white rounded-lg shadow-lg px-6 py-5 flex flex-col items-center text-center max-w-xs w-full">
        <svg class="animate-spin text-teal mb-3" width="36" height="36" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".2"/><path d="M22 12a10 10 0 00-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
        <p class="text-sm font-semibold text-navy">Submitting your feedback…</p>
        <p data-hint class="text-xs text-slate mt-1">Please don't close or refresh this page.</p>
      </div>`;
    document.body.appendChild(el);
    const hint = el.querySelector('[data-hint]');
    const t1 = setTimeout(() => { hint.textContent = 'Reviewing and classifying your feedback…'; }, 4000);
    const t2 = setTimeout(() => { hint.textContent = 'Almost done — this can take up to half a minute.'; }, 12000);
    const remove = el.remove.bind(el);
    el.remove = () => { clearTimeout(t1); clearTimeout(t2); remove(); };
    return el;
  }
})();
