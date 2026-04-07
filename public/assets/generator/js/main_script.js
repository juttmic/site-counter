// Font Awesome (for the change icon)
if (!document.getElementById('fa-cdn')) {
  const link = document.createElement('link');
  link.id = 'fa-cdn';
  link.rel = 'stylesheet';
  link.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css';
  document.head.appendChild(link);
}

let isInitialLoad = true;
let initialLoadComplete = false;

function toggleInputs(isUserAction = false) {
  const type = document.getElementById('timer_type').value;
  const fixedDateInput = document.getElementById('fixed_date_input');
  const durationInput = document.getElementById('duration_input');
  const durationField = durationInput ? durationInput.querySelector('input[name="duration_seconds"]') : null;
  
  fixedDateInput.classList.toggle('d-none', type !== 'fixed_date');
  durationInput.classList.toggle('d-none', type !== 'reload_based');
  
  // Only set default value when user explicitly switches to Evergreen (not on initial page load)
  // This ensures the field starts empty before skin selection
  // IMPORTANT: When switching to fixed_date, do NOT clear or modify duration_seconds
  // The fields should remain independent - entering a date should not affect duration_seconds
  if (type === 'reload_based' && durationField && isUserAction && initialLoadComplete) {
    const currentValue = parseInt(durationField.value, 10);
    if (isNaN(currentValue) || currentValue <= 0 || durationField.value === '' || durationField.value === null || durationField.value === undefined) {
      durationField.value = '1'; // Default to 1 second when user switches to Evergreen
      // Trigger change event to update preview immediately
      durationField.dispatchEvent(new Event('change', { bubbles: true }));
      durationField.dispatchEvent(new Event('input', { bubbles: true }));
    }
  }
  // When switching to fixed_date, we intentionally do NOT touch duration_seconds
  // This keeps the fields independent as requested
}

document.addEventListener('DOMContentLoaded', () => {
  toggleInputs(false); // Initial load, don't set default

  // Persist user's timezone so backend can interpret datetime-local as local time.
  (function initClientTimezone() {
    const tzEl = document.getElementById('client_timezone');
    if (!tzEl) return;
    try {
      tzEl.value = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
    } catch (e) {
      tzEl.value = '';
    }
  })();

  // Create page: ensure the date-time picker starts at "now" (current local time).
  // We store the initial value so skin selection can safely replace it with the sample time
  // only if the user hasn't edited it.
  (function initTargetDateTimeToNow() {
    const form = document.getElementById('timer_form');
    const isEdit = form?.dataset.isEdit === '1';
    if (isEdit) {
      const input = document.querySelector('input[name="target_datetime"]');
      if (!input) return;
      const utcVal = input.dataset.utc;
      if (utcVal) {
        try {
          const localVal = toLocalDateTimeInputValue(new Date(utcVal));
          if (localVal) {
            input.value = localVal;
          }
        } catch (e) {
          console.log(e);
        }
      }
      input.dataset.userTouched = '0';
      const markTouched = () => { input.dataset.userTouched = '1'; };
      input.addEventListener('change', markTouched);
      input.addEventListener('input', markTouched);
      return;
    }

    const input = document.querySelector('input[name="target_datetime"]');
    if (!input) return;

    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    const nowVal = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;

    // If server rendered an empty value, fill with current time.
    if (!input.value) {
      input.value = nowVal;
    }

    input.dataset.initialValue = input.value || nowVal;
    input.dataset.userTouched = '0';

    const markTouched = () => { input.dataset.userTouched = '1'; };
    input.addEventListener('change', markTouched);
    input.addEventListener('input', markTouched);
  })();

  // Mark initial load as complete after a short delay to ensure all setup is done
  setTimeout(() => {
    initialLoadComplete = true;
    isInitialLoad = false;
  }, 100);
});

function enforceMinTargetDateTime() {
  const input = document.querySelector('input[name="target_datetime"]');
  if (!input) return;
  const now = new Date();
  const pad = (n) => n.toString().padStart(2, '0');
  const minVal = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
  input.min = minVal;
}

function findFieldByKey(key) {
  return document.querySelector(`[data-key="${key}"]`) || document.querySelector(`input[name="${key}"]`);
}

function firstNumber(...vals) {
  for (const v of vals) {
    if (v === '' || v === null || v === undefined) continue;
    const n = Number(v);
    if (!isNaN(n)) return n;
  }
  return NaN;
}

function invertHexColor(hex) {
  if (typeof hex !== 'string') return '#000000';
  let h = hex.trim().replace('#','');
  if (h.length === 3) {
    h = h.split('').map(c => c + c).join('');
  }
  if (h.length !== 6) return '#000000';
  const r = 255 - parseInt(h.slice(0,2), 16);
  const g = 255 - parseInt(h.slice(2,4), 16);
  const b = 255 - parseInt(h.slice(4,6), 16);
  const toHex = (v) => v.toString(16).padStart(2, '0');
  const inv = `#${toHex(r)}${toHex(g)}${toHex(b)}`.toUpperCase();
  // If pure black, nudge to a dark gray to ensure visibility on white backgrounds
  // if (inv === '#000000') {
  //   return '#111111';
  // }
  return inv;
}

function parseOverrides() {
  try {
    return JSON.parse(document.getElementById('skin_overrides_input')?.value || '{}') || {};
  } catch (e) {
    return {};
  }
}

/** Mirror TimerGifGenerator::resolveVisibilityLayout — effective timer width/height used when drawing. */
function resolveTimerDimensionsFromVisibilityLayouts(defaults, overrides) {
  const skin = { ...defaults, ...(overrides || {}) };
  const layouts = skin.visibilityLayouts;
  if (!layouts || typeof layouts !== 'object') {
    return {
      width: firstNumber(skin.width, 400),
      height: firstNumber(skin.height, 160),
    };
  }
  const showDays = (skin.showDays ?? true) === true;
  const showSeconds = (skin.showSeconds ?? true) === true;
  let layout = null;
  if (!showDays && !showSeconds && layouts.daysAndSecondsHidden) {
    layout = layouts.daysAndSecondsHidden;
  } else if (!showDays && layouts.daysHidden) {
    layout = layouts.daysHidden;
  } else if (!showSeconds && layouts.secondsHidden) {
    layout = layouts.secondsHidden;
  } else if (layouts.allVisible) {
    layout = layouts.allVisible;
  }
  if (!layout || typeof layout !== 'object') {
    return {
      width: firstNumber(skin.width, 400),
      height: firstNumber(skin.height, 160),
    };
  }
  let scaleW = 1.0;
  let scaleH = 1.0;
  const av = layouts.allVisible;
  if (av && typeof av === 'object') {
    const baseW = firstNumber(av.width);
    const baseH = firstNumber(av.height);
    const skinW = firstNumber(skin.width);
    const skinH = firstNumber(skin.height);
    if (baseW > 0 && skinW > 0 && baseW !== skinW) {
      scaleW = skinW / baseW;
    }
    if (baseH > 0 && skinH > 0 && baseH !== skinH) {
      scaleH = skinH / baseH;
    }
  }
  const lw = firstNumber(layout.width);
  const lh = firstNumber(layout.height);
  let width = lw;
  let height = lh;
  if (scaleW !== 1.0 && Number.isFinite(lw)) {
    width = Math.max(1, Math.round(lw * scaleW));
  }
  if (scaleH !== 1.0 && Number.isFinite(lh)) {
    height = Math.max(1, Math.round(lh * scaleH));
  }
  return {
    width: Number.isFinite(width) ? width : firstNumber(skin.width, 400),
    height: Number.isFinite(height) ? height : firstNumber(skin.height, 160),
  };
}

/** Approximate imagettfbbox vertical span for custom heading text (~asc+desc for skin TTFs). */
function headingTextBlockHeightPx(defaults, overrides) {
  const fontSizeRaw = firstNumber(
    findFieldByKey('fontSize')?.value,
    overrides.fontSize,
    defaults.fontSize
  );
  const fontSize = Number.isFinite(fontSizeRaw) ? fontSizeRaw : 36;
  const customInput = document.querySelector('input[name="customTextFontSize"]');
  const customN = Number(customInput?.value);
  const em = (Number.isFinite(customN) && customN >= 12)
    ? Math.round(customN)
    : Math.max(12, Math.round(fontSize * 0.5));
  return Math.max(12, Math.round(em * 1.22));
}

function resolveSizingSnapshot() {
  const overrides = parseOverrides();
  const defaults = currentSkinMetadata?.defaults || {};
  const borderWidth = Math.max(0, firstNumber(
    findFieldByKey('borderWidth')?.value,
    overrides.borderWidth,
    defaults.borderWidth
  ));
  const edgePad = Math.max(2, borderWidth);

  const gifHeight = firstNumber(
    findFieldByKey('outputHeight')?.value,
    findFieldByKey('height')?.value,
    overrides.outputHeight,
    overrides.height,
    defaults.outputHeight,
    defaults.height
  );

  const vis = resolveTimerDimensionsFromVisibilityLayouts(defaults, overrides);
  const timerHeight = firstNumber(vis.height, overrides.height, defaults.height, 160);

  const fontSizeRaw = firstNumber(
    findFieldByKey('fontSize')?.value,
    overrides.fontSize,
    defaults.fontSize
  );
  const fontSize = isNaN(fontSizeRaw) ? 36 : fontSizeRaw;
  /** Vertical space occupied by heading (px), aligned with TimerGifGenerator bbox — not the font em size. */
  const textSize = headingTextBlockHeightPx(defaults, overrides);

  const textVal = (document.querySelector('input[name="custom_text"]')?.value || '').trim();
  const textPos = document.querySelector('select[name="text_position"]')?.value || 'none';
  const wantsText = textVal !== '' && textPos !== 'none';

  const MIN_CUSTOM_TEXT_DISTANCE = -400;
  const MAX_CUSTOM_TEXT_DISTANCE = 400;
  const distanceRaw = Number(document.querySelector('input[name="customTextDistance"]')?.value);
  let distance = isNaN(distanceRaw) ? 0 : distanceRaw;
  distance = Math.max(MIN_CUSTOM_TEXT_DISTANCE, Math.min(MAX_CUSTOM_TEXT_DISTANCE, distance));

  const innerStack = wantsText ? Math.max(0, textSize + distance) : 0;
  const combinedHeight = timerHeight + innerStack + (edgePad * 2);
  const maxDistance = wantsText
    ? Math.max(0, Math.floor(gifHeight - (edgePad * 2) - timerHeight - textSize))
    : Math.max(0, Math.floor(gifHeight - (edgePad * 2) - timerHeight));

  return {
    overrides,
    defaults,
    borderWidth,
    edgePad,
    gifHeight,
    timerHeight,
    fontSize,
    textSize,
    wantsText,
    distance,
    combinedHeight,
    maxDistance,
  };
}

/**
 * At default GIF height, negative padding cannot go past full vertical overlap of the heading:
 * text + pad >= 0  =>  pad >= -textSize (same units as TimerGifGenerator natural stack).
 */
function minNegativePaddingWhenAtDefaultHeight(textSizePx) {
  const te = Number.isFinite(textSizePx) ? textSizePx : 0;
  if (te <= 0) return -400;
  return Math.max(-400, -Math.round(te));
}

/** "Default height" band: metadata default or the layout minimum at padding 0, whichever is larger. */
function isGifHeightAtDefaultBand(effectiveGifH, metaDefH, timerH, textH, edgePad) {
  if (!Number.isFinite(effectiveGifH) || !Number.isFinite(metaDefH) || metaDefH <= 0) {
    return false;
  }
  const layoutMin = Math.max(
    metaDefH,
    Math.ceil(naturalLayoutNeedForPad(timerH, textH, edgePad, 0))
  );
  return effectiveGifH <= layoutMin + 2;
}

function clampCustomTextDistance() {
  const distanceInput = document.querySelector('input[name="customTextDistance"]');
  if (!distanceInput) return;
  const sizing = resolveSizingSnapshot();
  const gifHeight = sizing.gifHeight;
  const timerHeight = sizing.timerHeight;
  const MIN_CUSTOM_TEXT_DISTANCE = -400;
  const MAX_CUSTOM_TEXT_DISTANCE = 400;

  if (isNaN(gifHeight) || isNaN(timerHeight)) {
    return;
  }

  const metaDefH = getSkinDefaultOutputHeight();
  /** Upper bound for padding: room inside max GIF height (2× skin default), not current height — so positive padding can grow until sync hits maxH. */
  let maxPositivePadding = sizing.maxDistance;
  if (sizing.wantsText && Number.isFinite(metaDefH) && metaDefH > 0) {
    const capH = Math.floor(metaDefH * 2);
    maxPositivePadding = Math.max(
      0,
      Math.floor(capH - sizing.edgePad * 2 - timerHeight - sizing.textSize)
    );
  }

  const desired = Number(distanceInput.value);
  if (isNaN(desired)) {
    return;
  }
  let next = desired;
  if (desired < MIN_CUSTOM_TEXT_DISTANCE) {
    next = MIN_CUSTOM_TEXT_DISTANCE;
  } else if (desired > MAX_CUSTOM_TEXT_DISTANCE) {
    next = MAX_CUSTOM_TEXT_DISTANCE;
  } else if (desired > maxPositivePadding) {
    next = maxPositivePadding;
  }
  const gifFromDom = findGifHeightField()?.value;
  const gifDomN = Number(gifFromDom);
  const effectiveGifH = Number.isFinite(gifDomN) ? gifDomN : gifHeight;
  if (
    sizing.wantsText &&
    Number.isFinite(metaDefH) &&
    metaDefH > 0 &&
    isGifHeightAtDefaultBand(effectiveGifH, metaDefH, timerHeight, sizing.textSize, sizing.edgePad)
  ) {
    const padFloorNeg = minNegativePaddingWhenAtDefaultHeight(sizing.textSize);
    if (next < padFloorNeg) {
      next = padFloorNeg;
    }
  }

  if (next !== desired) {
    distanceInput.value = next;
  }
}

function findGifHeightField() {
  return findFieldByKey('outputHeight') || findFieldByKey('height');
}

/** Skin default GIF height (floor used by syncGifHeightDeltaFromPaddingInput). */
function getSkinDefaultOutputHeight() {
  const d = currentSkinMetadata?.defaults || {};
  return firstNumber(d.outputHeight, d.height);
}

function headingWantsCustomText() {
  const textVal = (document.querySelector('input[name="custom_text"]')?.value || '').trim();
  const textPos = document.querySelector('select[name="text_position"]')?.value || 'none';
  return textVal !== '' && textPos !== 'none';
}

/** Last padding value used for GIF height sync (see syncGifHeightDeltaFromPaddingInput). */
function initPaddingHeightSyncState() {
  const distanceInput = document.querySelector('input[name="customTextDistance"]');
  if (!distanceInput) return;
  const p = Number(distanceInput.value);
  distanceInput.dataset.heightSyncPad = String(Number.isFinite(p) ? p : 0);
}

/** Timer + heading stack height for a padding value (matches TimerGifGenerator layout). */
function naturalLayoutNeedForPad(timerH, textH, edgePad, pad) {
  const p = Number.isFinite(pad) ? pad : 0;
  const th = Number.isFinite(timerH) ? timerH : 0;
  const te = Number.isFinite(textH) ? textH : 0;
  const ep = Number.isFinite(edgePad) ? edgePad : 2;
  return th + Math.max(0, te + p) + 2 * ep;
}

/**
 * When header text is on, GIF/canvas height tracks padding (floor: skin default, cap: 2× default).
 * Nonnegative padding: 1:1 with layout need. When padding decreases, height follows the change in
 * natural layout need (timer + max(0, text + pad) + 2×edge) while current height is above default,
 * so the canvas does not keep extra slack that vertically centers the stack and pulls top headings
 * away from the top edge. At default height, further negative padding does not shrink height.
 * Increasing from negative: height grows when layout need exceeds the skin default GIF height.
 */
function syncGifHeightDeltaFromPaddingInput(distanceInput) {
  if (!headingWantsCustomText()) {
    initPaddingHeightSyncState();
    return;
  }

  const hf = findGifHeightField();
  if (!hf || !currentSkinMetadata?.defaults) return;

  const defH = firstNumber(
    currentSkinMetadata.defaults.outputHeight,
    currentSkinMetadata.defaults.height
  );
  if (!Number.isFinite(defH) || defH <= 0) return;

  const maxH = Math.floor(defH * 2);

  let curPad = Number(distanceInput.value);
  if (!Number.isFinite(curPad)) curPad = 0;

  let prevPad = Number(distanceInput.dataset.heightSyncPad);
  if (!Number.isFinite(prevPad)) prevPad = curPad;

  const deltaPad = curPad - prevPad;
  if (deltaPad === 0) return;

  let curH = Number(hf.value);
  if (!Number.isFinite(curH)) curH = defH;

  const sizing = resolveSizingSnapshot();
  const { edgePad, timerHeight, textSize: textH } = sizing;
  const prevNeed = naturalLayoutNeedForPad(timerHeight, textH, edgePad, prevPad);
  const curNeed = naturalLayoutNeedForPad(timerHeight, textH, edgePad, curPad);
  const needDelta = Math.round(curNeed - prevNeed);

  /** Shrink GIF height with layout need, but not below skin default. */
  const shrinkTowardDefaultFollowingLayout = () => {
    if (curH <= defH) {
      return 0;
    }
    let d = needDelta;
    if (curH + d < defH) {
      d = defH - curH;
    }
    return d;
  };

  let heightDelta;
  if (prevPad >= 0 && curPad >= 0) {
    heightDelta = deltaPad;
  } else if (prevPad >= 0 && curPad < 0) {
    heightDelta = needDelta;
    if (curH + heightDelta < defH) {
      heightDelta = defH - curH;
    }
  } else if (deltaPad < 0) {
    heightDelta = shrinkTowardDefaultFollowingLayout();
  } else {
    heightDelta = Math.round(Math.max(curNeed, defH) - Math.max(prevNeed, defH));
  }

  let nextH = curH + heightDelta;
  if (nextH < defH) nextH = defH;
  if (nextH > maxH) nextH = maxH;

  hf.value = String(Math.round(nextH));
}

function adjustGifHeightForPaddingClampCorrection(padDelta) {
  if (!padDelta || !headingWantsCustomText()) return;
  const hf = findGifHeightField();
  if (!hf || !currentSkinMetadata?.defaults) return;
  const defH = firstNumber(
    currentSkinMetadata.defaults.outputHeight,
    currentSkinMetadata.defaults.height
  );
  if (!Number.isFinite(defH) || defH <= 0) return;
  const maxH = Math.floor(defH * 2);
  let h = Number(hf.value);
  if (!Number.isFinite(h)) h = defH;
  h += padDelta;
  if (h < defH) h = defH;
  if (h > maxH) h = maxH;
  hf.value = String(Math.round(h));
}

function enforceGifHeightMinimum() {
  const heightField = findGifHeightField();
  if (!heightField) return;
  const sizing = resolveSizingSnapshot();

  if (isNaN(sizing.gifHeight) || isNaN(sizing.timerHeight)) {
    return;
  }

  const required = Math.max(0, Math.ceil(sizing.combinedHeight || 0));
  if (!isNaN(required) && required > 0 && sizing.gifHeight < required) {
    const maxAttr = heightField.max !== '' && heightField.max != null ? Number(heightField.max) : Number.NaN;
    const applied = Number.isFinite(maxAttr) ? Math.min(required, maxAttr) : required;
    heightField.value = applied;
    const msg = `GIF height must be at least ${required}px to fit the timer and text.`;
    if (window.showToast) {
      // window.showToast(msg, 'warning');
    } else {
      console.warn(msg);
    }
    // Keep overrides in sync with the adjusted value
    if (typeof syncOverrides === 'function') {
      syncOverrides();
    }
  }
}

document.addEventListener('DOMContentLoaded', () => {
  enforceMinTargetDateTime();
  const targetInput = document.querySelector('input[name="target_datetime"]');
  if (targetInput) {
    targetInput.addEventListener('change', enforceMinTargetDateTime);
    // Ensure that changing the date does not affect the duration_seconds field
    // The fields are independent - entering a date should not populate or modify duration_seconds
  }
});

// Initialize static color picker for customTextColor and sync to skin_overrides
document.addEventListener('DOMContentLoaded', function() {
  const customTextColorInput = document.querySelector('input[name="customTextColor"].color-picker-native');
  if (customTextColorInput) {
    const wrapper = customTextColorInput.closest('.modern-color-picker');
    const swatch = wrapper.querySelector('.color-picker-swatch');
    const hexSpan = wrapper.querySelector('.color-picker-hex');
    const display = wrapper.querySelector('.color-picker-display');
    
    const updateDisplay = () => {
      const newColor = customTextColorInput.value;
      swatch.style.setProperty('--swatch-color', newColor);
      hexSpan.textContent = newColor.toUpperCase();
      // Sync to skin_overrides when changed
      syncOverrides();
    };
    
    customTextColorInput.addEventListener('input', updateDisplay);
    customTextColorInput.addEventListener('change', updateDisplay);
    display.addEventListener('click', () => customTextColorInput.click());
    swatch.addEventListener('click', () => customTextColorInput.click());
  }
  
  // Sync distance field to skin_overrides when changed; keep GIF height in sync with padding
  const distanceInput = document.querySelector('input[name="customTextDistance"]');
  if (distanceInput) {
    initPaddingHeightSyncState();
    const handleDistanceChange = () => {
      // Clamp first (esp. -textSize floor at default height) so sync never applies height from an illegal pad.
      clampCustomTextDistance();
      const padBeforeSync = (() => {
        const n = Number(distanceInput.value);
        return Number.isFinite(n) ? n : 0;
      })();

      syncGifHeightDeltaFromPaddingInput(distanceInput);

      clampCustomTextDistance();

      const padAfter = (() => {
        const n = Number(distanceInput.value);
        return Number.isFinite(n) ? n : 0;
      })();

      if (padAfter !== padBeforeSync) {
        adjustGifHeightForPaddingClampCorrection(padAfter - padBeforeSync);
      }
      distanceInput.dataset.heightSyncPad = String(padAfter);

      enforceGifHeightMinimum();
      syncOverrides();
    };
    distanceInput.addEventListener('change', handleDistanceChange);
    distanceInput.addEventListener('input', handleDistanceChange);
  }

  const headingCustomTextInput = document.querySelector('input[name="custom_text"]');
  const headingPositionSelect = document.querySelector('select[name="text_position"]');
  if (headingCustomTextInput) {
    headingCustomTextInput.addEventListener('change', () => initPaddingHeightSyncState());
  }
  if (headingPositionSelect) {
    headingPositionSelect.addEventListener('change', () => initPaddingHeightSyncState());
  }

  // Sync custom text font size to skin_overrides when changed
  const customTextFontSizeInput = document.querySelector('input[name="customTextFontSize"]');
  if (customTextFontSizeInput) {
    const handleFontSizeChange = () => {
      syncOverrides();
    };
    customTextFontSizeInput.addEventListener('change', handleFontSizeChange);
    customTextFontSizeInput.addEventListener('input', handleFontSizeChange);
  }

  // Sync font select to skin_overrides when changed
  const fontSelect = document.querySelector('select[name="customTextFont"]');
  if (fontSelect) {
    fontSelect.addEventListener('change', syncOverrides);
  }

  // Fetch fonts and populate font select
  (async function loadFontsIntoSelect() {
    const fontSelect = document.getElementById('customTextFont');
    if (!fontSelect) return;
    try {
      const res = await fetch('/api/fonts', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!res.ok) return;
      const json = await res.json();
      const fonts = json.fonts || [];
      const toDisplayName = (filename) => filename.replace(/\.(ttf|otf|woff2?|TTF|OTF|WOFF2?)$/i, '');
      const toFamilyName = (filename) => {
        const base = toDisplayName(filename);
        const slug = base.replace(/[^a-zA-Z0-9_-]/g, '_');
        return `CounterFont_${slug}`;
      };

      // Ensure we have a shared <style> tag for font samples
      let styleEl = document.getElementById('custom-font-samples-style');
      if (!styleEl) {
        styleEl = document.createElement('style');
        styleEl.id = 'custom-font-samples-style';
        document.head.appendChild(styleEl);
      }
      let css = styleEl.textContent || '';

      const fontBaseUrl = (window.COUNTER_FONT_BASE_URL || '/assets/fonts').replace(/\/+$/, '');

      fonts.forEach(f => {
        const opt = document.createElement('option');
        opt.value = f;
        const displayName = toDisplayName(f);
        opt.textContent = displayName;
        fontSelect.appendChild(opt);

        const family = toFamilyName(f);
        if (!css.includes(`font-family: '${family}'`)) {
          const ext = (f.split('.').pop() || '').toLowerCase();
          let format = 'truetype';
          if (ext === 'otf') format = 'opentype';
          else if (ext === 'woff') format = 'woff';
          else if (ext === 'woff2') format = 'woff2';
          const encodedName = encodeURIComponent(f).replace(/%2F/gi, '/');
          const srcUrl = `${fontBaseUrl}/${encodedName}`;
          css += `
@font-face {
  font-family: '${family}';
  src: url('${srcUrl}') format('${format}');
  font-weight: normal;
  font-style: normal;
}
`;
        }
      });
      styleEl.textContent = css;
      const saved = fontSelect.dataset.savedValue;
      if (saved && fonts.includes(saved)) {
        fontSelect.value = saved;
      }
      // Enhance font select with Select2 if available (searchable dropdown)
      if (window.$?.fn?.select2) {
        const formatFontOption = (data) => {
          if (!data.id) {
            return data.text || '';
          }
          const value = data.id;
          const family = toFamilyName(value);
          const nameText = data.text || '';

          // Truncate long names with ellipsis for nicer display
          const maxLen = 24;
          let label = nameText;
          if (label.length > maxLen) {
            label = label.slice(0, maxLen - 1) + '…';
          }

          // Apply the font only on the 123ABC sample, not on the name
          return `
            <span class="font-option d-flex justify-content-between align-items-center">
              <span class="font-name">${label}</span>
              <span class="font-sample" style="margin-left: 0.75rem; font-family: '${family}';">123ABC</span>
            </span>
          `;
        };

        const $fontSelect = $(fontSelect).select2({
          width: '100%',
          placeholder: 'Default',
          allowClear: true,
          templateResult: formatFontOption,
          templateSelection: formatFontOption,
          escapeMarkup: (m) => m
        });
        // Ensure preview updates when selection changes via Select2
        $fontSelect.on('change select2:select select2:clear', () => {
          if (typeof syncOverrides === 'function') {
            syncOverrides();
          }
        });
      }
      syncOverrides();
    } catch (e) {
      // Silently ignore - font list stays as Default only
    }
  })();

  // If timer height changes, re-clamp distance. Use 'change' only (not 'input') so the user
  // can type freely (e.g. clear 430 to enter 500) without validation resetting the value mid-edit.
  ['outputHeight'].forEach(name => {
    const el = document.querySelector(`[data-key="${name}"]`) || document.querySelector(`input[name="${name}"]`);
    if (el) {
      const handler = () => {
        clampCustomTextDistance();
        enforceGifHeightMinimum();
      };
      el.addEventListener('change', handler);
      el.dataset.boundGifHeight = '1';
    }
  });

  const customTextInput = document.querySelector('input[name="custom_text"]');
  if (customTextInput) {
    const handleTextChange = () => {
      clampCustomTextDistance();
      enforceGifHeightMinimum();
    };
    customTextInput.addEventListener('change', handleTextChange);
    customTextInput.addEventListener('input', handleTextChange);
  }

  const textPositionInput = document.querySelector('select[name="text_position"]');
  if (textPositionInput) {
    textPositionInput.addEventListener('change', () => {
      clampCustomTextDistance();
      enforceGifHeightMinimum();
    });
  }

  // Initial pass to ensure spacing/height are consistent on load
  clampCustomTextDistance();
  enforceGifHeightMinimum();
});

// Skins loading and dynamic form
async function fetchJson(url) {
  const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
  if (!res.ok) throw new Error('Failed to load ' + url);
  return await res.json();
}

// Bootstrap modal hooks for skin selection

async function loadSkinsIntoModal() {
  const grid = document.getElementById('skin_modal_grid');
  if (!grid) return;
  grid.innerHTML = '<div class="col-12 text-center text-muted small">Loading skins...</div>';
  try {
    const skins = await fetchJson('/api/skins');
    const current = document.getElementById('skin_key').value;
    grid.innerHTML = '';
    skins.forEach(s => {
      const col = document.createElement('div');
      col.className = 'col-6 col-md-4 col-lg-3';
      const card = document.createElement('button');
      card.type = 'button';
      card.className = 'w-100 border rounded p-2 bg-white text-start';
      card.innerHTML = `
        <div class="d-flex align-items-center justify-content-center bg-light border rounded" style="height: 96px;">
          <img src="${s.preview_url}" alt="${s.name}" class="img-fluid" style="max-height: 96px; object-fit: contain;">
        </div>
        <div class="mt-2 small text-truncate">${s.name}</div>
      `;
      if (s.key === current) card.classList.add('border-primary');
      card.addEventListener('click', async () => {
        await selectSkin(s.key);
        const modalEl = document.getElementById('skinSelectModal');
        if (modalEl) {
          const inst = bootstrap.Modal.getInstance(modalEl);
          if (inst) inst.hide();
        }
      });
      col.appendChild(card);
      grid.appendChild(col);
    });
  } catch (e) {
    grid.innerHTML = '<div class="col-12 text-center text-danger small">Failed to load skins</div>';
  }
}

// Horizontal skin selector bar (top of form)
async function loadSkinsIntoScrollBar() {
  const bar = document.getElementById('skin_scroll_bar');
  if (!bar) return;
  bar.innerHTML = '<span class="text-muted small">Loading skins...</span>';

  try {
    const skins = await fetchJson('/api/skins');
    const current = document.getElementById('skin_key').value;
    bar.innerHTML = '';

    // Define helper functions before creating cards
    const getCardWidth = () => {
      const firstCard = bar.querySelector('.skin-card');
      if (!firstCard) return 360; // fallback: 344px card + 16px gap
      // Get actual rendered width including padding and border
      const cardRect = firstCard.getBoundingClientRect();
      const cardWidth = cardRect.width;
      // Get gap from computed style of the container (gap-3 = 1rem = 16px in Bootstrap 5)
      const barStyle = window.getComputedStyle(bar);
      const gap = parseFloat(barStyle.gap) || 16; // fallback to 16px if not found
      return cardWidth + gap;
    };

    skins.forEach(s => {
      const wrapper = document.createElement('div');
      wrapper.className =
        'skin-card mine d-flex flex-column align-items-stretch text-center rounded bg-white flex-shrink-0';
      wrapper.style.boxSizing = 'border-box';
      wrapper.style.width = '344px';
      wrapper.style.height = '102px';
      wrapper.style.cursor = 'pointer';
      wrapper.dataset.skinKey = s.key;

      const preview = document.createElement('div');
      preview.className =
        'skin-card-preview d-flex align-items-center justify-content-center rounded w-100';
      preview.style.position = 'relative';

      const img = document.createElement('img');
      img.src = s.preview_url;
      img.alt = s.name;
      img.className = 'img-fluid';
      img.style.objectFit = 'contain';
      preview.appendChild(img);

      // Create checkmark icon
      const checkmark = document.createElement('i');
      checkmark.className = 'bi bi-check-circle-fill skin-checkmark';
      if (s.key === current) {
        checkmark.classList.add('selected-checkmark');
        wrapper.classList.add('selected-skin');
      }
      preview.appendChild(checkmark);

      // const name = document.createElement('div');
      // name.className = 'small text-truncate w-100';
      // name.textContent = s.name;

      wrapper.appendChild(preview);
      // wrapper.appendChild(name);

      wrapper.addEventListener('click', async () => {
        // Clear previous selection
        bar.querySelectorAll('.skin-card.selected-skin').forEach(el => {
          el.classList.remove('selected-skin');
          const prevCheckmark = el.querySelector('.skin-checkmark');
          if (prevCheckmark) {
            prevCheckmark.classList.remove('selected-checkmark');
          }
        });

        wrapper.classList.add('selected-skin');
        const checkmark = wrapper.querySelector('.skin-checkmark');
        if (checkmark) {
          checkmark.classList.add('selected-checkmark');
        }
        
        // Scroll selected card into view
        setTimeout(() => {
          const cardRect = wrapper.getBoundingClientRect();
          const barRect = bar.getBoundingClientRect();
          const containerWidth = bar.clientWidth;
          
          // Check if card is fully visible
          const cardLeft = cardRect.left - barRect.left;
          const cardRight = cardLeft + cardRect.width;
          
          if (cardLeft < 0) {
            // Card is cut off on the left, scroll to show it
            const targetScroll = bar.scrollLeft + cardLeft - 12;
            bar.scrollTo({ left: Math.max(0, targetScroll), behavior: 'smooth' });
          } else if (cardRight > containerWidth) {
            // Card is cut off on the right, scroll to show it
            const targetScroll = bar.scrollLeft + (cardRight - containerWidth) + 12;
            bar.scrollTo({ left: Math.min(targetScroll, bar.scrollWidth - containerWidth), behavior: 'smooth' });
          }
        }, 100);
        
        await selectSkin(s.key);
      });

      // Hover effect is handled by CSS

      bar.appendChild(wrapper);
    });

    // Wire up left/right arrow buttons to scroll the skin list with snap
    const prevBtn = document.querySelector('.owl-prev');
    const nextBtn = document.querySelector('.owl-next');

    const scrollToCard = (direction) => {
      if (!bar || bar.children.length === 0) return;
      
      const cardWidth = getCardWidth();
      const containerWidth = bar.clientWidth;
      const currentScroll = bar.scrollLeft;
      
      // Calculate how many complete cards fit in the viewport
      const cardsPerView = Math.floor(containerWidth / cardWidth);
      
      let targetScroll;
      if (direction === 'next') {
        // Scroll forward by one card width to show next card(s)
        targetScroll = currentScroll + cardWidth;
      } else {
        // Scroll backward by one card width to show previous card(s)
        targetScroll = currentScroll - cardWidth;
      }
      
      // Snap to card boundary
      targetScroll = Math.round(targetScroll / cardWidth) * cardWidth;
      
      // Ensure we don't scroll beyond boundaries
      const maxScroll = Math.max(0, bar.scrollWidth - containerWidth);
      targetScroll = Math.max(0, Math.min(targetScroll, maxScroll));
      
      bar.scrollTo({ left: targetScroll, behavior: 'smooth' });
    };

    const scrollToSelectedCard = () => {
      const selectedCard = bar.querySelector('.skin-card.selected-skin');
      if (!selectedCard) return;
      
      const cardWidth = getCardWidth();
      const containerWidth = bar.clientWidth;
      const cardRect = selectedCard.getBoundingClientRect();
      const barRect = bar.getBoundingClientRect();
      
      // Check if card is already fully visible
      const cardLeft = cardRect.left - barRect.left + bar.scrollLeft;
      const cardRight = cardLeft + cardRect.width;
      const visibleLeft = bar.scrollLeft;
      const visibleRight = bar.scrollLeft + containerWidth;
      
      // If card is not fully visible, scroll to show it
      if (cardLeft < visibleLeft) {
        // Card is cut off on the left, scroll to show it
        bar.scrollTo({ left: Math.max(0, cardLeft - 12), behavior: 'smooth' });
      } else if (cardRight > visibleRight) {
        // Card is cut off on the right, scroll to show it
        const targetScroll = cardRight - containerWidth + 12;
        bar.scrollTo({ left: Math.min(targetScroll, bar.scrollWidth - containerWidth), behavior: 'smooth' });
      }
    };

    const updateArrowState = () => {
      if (!bar) return;
      const containerWidth = bar.clientWidth;
      const maxScrollLeft = bar.scrollWidth - containerWidth;
      const atStart = bar.scrollLeft <= 1; // small tolerance
      const atEnd = bar.scrollLeft >= maxScrollLeft - 1; // small tolerance
      
      if (prevBtn) {
        if (atStart) {
          prevBtn.classList.add('disabled');
        } else {
          prevBtn.classList.remove('disabled');
        }
      }
      if (nextBtn) {
        if (atEnd) {
          nextBtn.classList.add('disabled');
        } else {
          nextBtn.classList.remove('disabled');
        }
      }
    };

    // Handle snap scrolling after manual scroll (touch/mouse drag)
    let scrollTimeout;
    let isScrolling = false;
    
    bar.addEventListener('scroll', () => {
      clearTimeout(scrollTimeout);
      updateArrowState();
      
      // Only snap if user manually scrolled (not programmatic scroll)
      if (!isScrolling) {
        scrollTimeout = setTimeout(() => {
          // Snap to nearest card boundary
          const cardWidth = getCardWidth();
          const currentScroll = bar.scrollLeft;
          const snappedScroll = Math.round(currentScroll / cardWidth) * cardWidth;
          
          isScrolling = true;
          bar.scrollTo({ left: snappedScroll, behavior: 'smooth' });
          setTimeout(() => { isScrolling = false; }, 300);
        }, 150);
      }
    });

    if (prevBtn) {
      prevBtn.addEventListener('click', () => {
        isScrolling = true;
        scrollToCard('prev');
        setTimeout(() => { isScrolling = false; }, 300);
      });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', () => {
        isScrolling = true;
        scrollToCard('next');
        setTimeout(() => { isScrolling = false; }, 300);
      });
    }

    // Initialize arrow state and scroll to selected card
    setTimeout(() => {
    updateArrowState();
      scrollToSelectedCard();
    }, 200);
    
    // Update on window resize
    window.addEventListener('resize', () => {
      updateArrowState();
      scrollToSelectedCard();
    });
  } catch (e) {
    bar.innerHTML = '<span class="text-danger small">Failed to load skins</span>';
  }
}

let currentSkinMetadata = null;
let currentHiddenOverrides = {};
let lastSkinOverrides = {};
let gifDirty = false;              // true = changes exist that are NOT in the latest GIF
let suppressNextDirtyFlag = false; // used for initial auto-preview so it doesn't mark dirty
let loadedSkins = new Set();       // Track which skins have been loaded (for new timers only)
let appliedFormDefaults = {};     // Track formDefaults that were applied (to ensure they're saved)

function updateResetButtonState() {
  const btn = document.getElementById('reset_design_btn');
  const skinKeyInput = document.getElementById('skin_key');
  if (!btn || !skinKeyInput) return;
  const hasSkin = !!(skinKeyInput.value || '').trim();
  btn.disabled = !hasSkin;
}

// Default sample countdown for preview/form: 1d 1h 10m 50s
const DEFAULT_SAMPLE_SECONDS = 90650;

function toLocalDateTimeInputValue(date) {
  const pad = (n) => String(n).padStart(2, '0');
  const yyyy = date.getFullYear();
  const mm = pad(date.getMonth() + 1);
  const dd = pad(date.getDate());
  const hh = pad(date.getHours());
  const mi = pad(date.getMinutes());
  const ss = pad(date.getSeconds());
  return `${yyyy}-${mm}-${dd}T${hh}:${mi}:${ss}`;
}

function applyDefaultSampleTimeIfEmpty() {
  const typeEl = document.getElementById('timer_type');
  const timerType = typeEl ? typeEl.value : 'fixed_date';

  const targetInput = document.querySelector('input[name="target_datetime"]');
  const durationInput = document.querySelector('input[name="duration_seconds"]');

  if (timerType === 'fixed_date') {
    if (targetInput) {
      const isEmpty = !targetInput.value;
      const isInitial = targetInput.dataset.initialValue && targetInput.value === targetInput.dataset.initialValue;
      const userTouched = targetInput.dataset.userTouched === '1';
      if ((isEmpty || isInitial) && !userTouched) {
      const sampleDate = new Date(Date.now() + DEFAULT_SAMPLE_SECONDS * 1000);
      targetInput.value = toLocalDateTimeInputValue(sampleDate);
      }
    }
    return;
  }

  if (timerType === 'reload_based') {
    if (durationInput && !durationInput.value) {
      durationInput.value = String(DEFAULT_SAMPLE_SECONDS);
    }
  }
}

function setupCollapsibles(root) {
  const scope = root || document;
  scope.querySelectorAll('.collapsible-header').forEach(header => {
    if (header.dataset.collapsibleBound === '1') return;
    const targetSelector = header.dataset.target;
    const body = targetSelector ? document.querySelector(targetSelector) : header.nextElementSibling;
    if (!body) return;
    const icon = header.querySelector('.collapse-icon');

    // Set initial icon state based on whether body is hidden
    if (icon) {
      const isHidden = body.classList.contains('d-none');
      icon.classList.remove('bi-chevron-down', 'bi-chevron-right');
      icon.classList.add(isHidden ? 'bi-chevron-right' : 'bi-chevron-down');
    }

    header.style.cursor = 'pointer';
    header.addEventListener('click', () => {
      const isHidden = body.classList.toggle('d-none');
      if (icon) {
        // Toggle between chevron-right (collapsed) and chevron-down (expanded)
        icon.classList.remove('bi-chevron-down', 'bi-chevron-right');
        icon.classList.add(isHidden ? 'bi-chevron-right' : 'bi-chevron-down');
      }
    });
    header.dataset.collapsibleBound = '1';
  });
}

/** Unique random #rrggbb (lowercase) for color inputs. */
function randomDistinctHexColors(count) {
  const used = new Set();
  const out = [];
  while (out.length < count) {
    const r = Math.floor(Math.random() * 256);
    const g = Math.floor(Math.random() * 256);
    const b = Math.floor(Math.random() * 256);
    const hex = `#${r.toString(16).padStart(2, '0')}${g.toString(16).padStart(2, '0')}${b.toString(16).padStart(2, '0')}`;
    if (!used.has(hex)) {
      used.add(hex);
      out.push(hex);
    }
  }
  return out;
}

/** Assigns a unique random color to each Design (Appearance) group color picker. */
function colorizeDesignGroupColors(groupBodyEl) {
  if (!groupBodyEl) {
    return;
  }
  const inputs = groupBodyEl.querySelectorAll('input.color-picker-native[data-key]');
  const list = Array.from(inputs);
  if (list.length === 0) {
    return;
  }
  const colors = randomDistinctHexColors(list.length);
  list.forEach((input, i) => {
    input.value = colors[i];
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
  });
  if (typeof syncOverrides === 'function') {
    syncOverrides();
  }
}

function renderFields(metadata, savedOverrides = {}) {
  currentSkinMetadata = metadata;
  currentHiddenOverrides = {};
  const fieldsWrap = document.getElementById('skin_fields');
  const defaults = metadata.defaults || {};

  // For create flows only, enforce a visible border default when skin is missing or zero
  const formEl = document.getElementById('timer_form');
  const isEdit = formEl && formEl.dataset.isEdit === '1';
  if (!isEdit) {
    // Do not force a visible border for new timers; respect the skin's default.
    if (defaults.borderWidth === undefined || defaults.borderWidth === null) {
      defaults.borderWidth = 0;
    }
    const bg = defaults.background || '#000000';
    const bgNorm = (bg || '').toString().trim().toLowerCase();
    const borderNorm = (defaults.borderColor || '').toString().trim().toLowerCase();
    if (!defaults.borderColor || defaults.borderColor === '' || borderNorm === bgNorm) {
      defaults.borderColor = invertHexColor(bg);
    }
  } else {
    // Edit: respect saved values; if missing, still ensure a sane fallback
    if (defaults.borderWidth === undefined || defaults.borderWidth === null) {
      defaults.borderWidth = 0;
    }
    if (!defaults.borderColor || defaults.borderColor === '') {
      defaults.borderColor = '#FFFFFF';
    }
  }
  const fields = Array.isArray(metadata.fields) ? metadata.fields : [];
  fieldsWrap.innerHTML = '';
  
  // Fields to hide but use defaults for
  // Note: accentColor is NOT hidden - it's needed for controlling tile/box/ring colors
  // Custom text fields live in the dedicated Custom Text settings card, not from skin JSON
  // Note: If a field is explicitly listed in the fields array with a group, it should be shown.
  // Only hide fields that are truly internal/not user-editable (fontPath, custom text fields)
  // Fields like showDays, layout, animation, width, height can now be shown if specified in skin JSON
  const alwaysHiddenFields = new Set(['fontPath', 'font', 'customTextColor', 'customTextDistance', 'customTextFont', 'custom_text', 'text_position', 'finalBackground', 'finalTextColor', 'width', 'height', 'showSeconds']);
  alwaysHiddenFields.forEach(key => {
    if (savedOverrides[key] !== undefined) {
      currentHiddenOverrides[key] = savedOverrides[key];
    }
  });
  
  // Filter: Only hide fields that are in alwaysHiddenFields
  // All other fields (including showDays, layout, animation, width, height if they're in the fields array) will be shown
  const visibleFields = fields.filter(field => !alwaysHiddenFields.has(field.key));
  
  // Note: Template card visibility will be updated after fields are rendered
  document.getElementById('skin_overrides_section').classList.toggle('d-none', visibleFields.length === 0);

  // Multilevel group system: Parse group specification from field
  // Supports: "GroupName", ["Group", "Subgroup"], "Group.Subgroup", ["Group", "Subgroup", "Subsubgroup"]
  function parseGroupPath(groupSpec) {
    if (!groupSpec) return null;
    
    if (Array.isArray(groupSpec)) {
      return groupSpec.filter(g => g && g.trim()).map(g => g.trim());
    }
    
    if (typeof groupSpec === 'string') {
      // Support dot notation: "Group.Subgroup"
      if (groupSpec.includes('.')) {
        return groupSpec.split('.').map(g => g.trim()).filter(g => g);
      }
      // Single group name
      return [groupSpec.trim()];
    }
    
    return null;
  }

  // Build hierarchical group structure
  function buildGroupTree(fields, defaults) {
    const groupTree = {};
    
    // Fallback keys for fields without explicit groups
    const gifAppearanceKeys = new Set(['background','borderwidth','bordercolor']);
    const gifDimensionKeys = new Set(['outputwidth','outputheight']);
    const counterDigitsKeys = new Set(['textcolor','fontsize','digitspacing','countdirection','refreshrate','pulsespeed']);
    const counterSeparatorKeys = new Set(['separator','separatorcolor','accentcolor','boxcolor','tilecolor','ringcolor']);
    const finalKeys = new Set(['finalmessage','finalbackground','finaltextcolor','finalscreentext','finalscreencolor']);
    const extraKeys = new Set(['shadowcolor','glowcolor','blurstrength','outlinecolor','outlinewidth']);
    
    function getFallbackGroup(fieldKey) {
      const key = fieldKey.toLowerCase();
      if (finalKeys.has(key)) return ['Final Screen'];
      if (extraKeys.has(key)) return ['Extra Options'];
      if (gifAppearanceKeys.has(key)) return ['Appearance'];
      if (counterDigitsKeys.has(key)) return ['Counter', 'Digits'];
      if (gifDimensionKeys.has(key)) return ['Dimensions'];
      if (counterSeparatorKeys.has(key)) return ['Counter', 'Appearance'];
      return ['Appearance'];
    }
    
    function ensureGroupPath(path) {
      let current = groupTree;
      for (let i = 0; i < path.length; i++) {
        const name = path[i];
        if (!current[name]) {
          current[name] = {
            name: name,
            fields: [],
            children: {},
            groupOrder: null  // Track group order
          };
        }
        if (i < path.length - 1) {
          if (!current[name].children) {
            current[name].children = {};
          }
          current = current[name].children;
        }
      }
      return current[path[path.length - 1]];
    }
    
    // Process fields with order support
    // Add original index for fields without explicit order
    fields.forEach((field, index) => {
      // If field doesn't have an order property, use index (plus 1000 to allow room for explicit low orders)
      if (field.order === undefined || field.order === null) {
        field._originalIndex = index + 1000;
      }
      
      let groupPath = parseGroupPath(field.group);
      
      // If no explicit group, use fallback logic
      if (!groupPath || groupPath.length === 0) {
        groupPath = getFallbackGroup(field.key);
      }
      
      const group = ensureGroupPath(groupPath);
      group.fields.push(field);
      
      // Track group order: groupOrder on a field controls the order of its immediate group/subgroup
      // For "Counter.Digits", groupOrder controls "Digits" subgroup order within "Counter"
      // For "Appearance", groupOrder controls "Appearance" group order among top-level groups
      // Use the minimum groupOrder value from all fields in a group
      if (field.groupOrder !== undefined && field.groupOrder !== null) {
        const currentOrder = group.groupOrder;
        if (currentOrder === null || field.groupOrder < currentOrder) {
          group.groupOrder = field.groupOrder;
        }
      }
    });
    
    // Sort fields within each group by order property
    function sortFieldsInTree(node) {
      // Sort fields by order (lower numbers first), then by original index
      node.fields.sort((a, b) => {
        const orderA = a.order !== undefined && a.order !== null ? a.order : a._originalIndex;
        const orderB = b.order !== undefined && b.order !== null ? b.order : b._originalIndex;
        return orderA - orderB;
      });
      
      // Recursively sort children
      Object.keys(node.children || {}).forEach(key => {
        sortFieldsInTree(node.children[key]);
      });
    }
    
    // Calculate group order for parent groups based on their children
    function calculateGroupOrders(node, level = 0) {
      // Calculate order for this group: use its own groupOrder or minimum from children
      if (node.groupOrder === null && node.children && Object.keys(node.children).length > 0) {
        const childOrders = Object.values(node.children)
          .map(child => child.groupOrder)
          .filter(order => order !== null);
        if (childOrders.length > 0) {
          node.groupOrder = Math.min(...childOrders);
        }
      }
      
      // Recursively process children
      Object.keys(node.children || {}).forEach(key => {
        calculateGroupOrders(node.children[key], level + 1);
      });
    }
    
    // Sort all groups recursively
    Object.keys(groupTree).forEach(key => {
      calculateGroupOrders(groupTree[key]);
      sortFieldsInTree(groupTree[key]);
    });
    
    return groupTree;
  }
  
  // Convert tree to flat list for rendering (preserving hierarchy)
  function flattenGroupTree(tree, parentPath = []) {
    const result = [];
    // Get all groups and sort by groupOrder, then alphabetically
    const groupNames = Object.keys(tree);
    
    // Sort groups: first by groupOrder (null/undefined last), then alphabetically
    const sortedGroupNames = groupNames.sort((a, b) => {
      const groupA = tree[a];
      const groupB = tree[b];
      const orderA = groupA.groupOrder !== null && groupA.groupOrder !== undefined ? groupA.groupOrder : 9999;
      const orderB = groupB.groupOrder !== null && groupB.groupOrder !== undefined ? groupB.groupOrder : 9999;
      
      if (orderA !== orderB) {
        return orderA - orderB;
      }
      // If same order, sort alphabetically
      return a.localeCompare(b);
    });
    
    sortedGroupNames.forEach(groupName => {
      const group = tree[groupName];
      const fullPath = [...parentPath, groupName];
      const pathKey = fullPath.join('.');
      
      // Process children recursively (they will be sorted by their own groupOrder)
      const children = group.children && Object.keys(group.children).length > 0
        ? flattenGroupTree(group.children, fullPath)
        : [];
      
      // Create group object (fields are already sorted by buildGroupTree)
      const groupObj = {
        path: fullPath,
        pathKey: pathKey,
        name: groupName,
        title: groupName,
        fields: group.fields || [], // Already sorted by order
        children: children  // Already sorted by groupOrder
      };
      
      result.push(groupObj);
    });
    
    return result;
  }

  // Define field size keys for layout
  const smallKeys = new Set(['fontSize','separator','layout','animation','showDays','showHours','showMinutes','showSeconds','width','height','outputWidth','outputHeight','borderWidth','borderColor']);
  const marginKeys = new Set(['customTextDistance','textMarginTop','textMarginBottom','marginTop','marginBottom','labelMarginTop','labelMarginBottom']);
  const visibilityToggleKeys = new Set(['showDays']);

  /** Max width/height inputs = 2× skin default (overrides JSON max). */
  function getDimensionCapMax(field) {
    const key = field.key;
    const d = defaults;
    let base;
    if (key === 'outputWidth') base = firstNumber(d.outputWidth, d.width);
    else if (key === 'outputHeight') base = firstNumber(d.outputHeight, d.height);
    else if (key === 'width') base = firstNumber(d.width);
    else if (key === 'height') base = firstNumber(d.height);
    else return null;
    if (!Number.isFinite(base) || base <= 0) return null;
    return Math.floor(base * 2);
  }

  const createFieldInput = (field, value, options = {}) => {
    const key = field.key;
    let label = field.label || key;
    if (key === 'outputHeight') {
      label = 'Height';
    } else if (key === 'outputWidth') {
      label = 'Width';
    } else if (key === 'finalMessage') {
      label = 'Text';
    }
    const type = field.type || 'text';
    const dimCapMax = getDimensionCapMax(field);
    const effMax = dimCapMax == null ? field.max : dimCapMax;
    const effMin = field.min;
    if (type === 'number' && effMax != null) {
      label += ` (max: ${effMax})`;
    }
    const useSwitch = options.useSwitch === true;
    const container = document.createElement('div');
    const isSmall = type === 'color' || type === 'boolean' || smallKeys.has(key);
    const isMarginField = marginKeys.has(key);
    if (isMarginField) {
      container.className = 'col-12 col-sm-4 col-md-3';
    } else {
      container.className = isSmall ? 'col-12 col-sm-6 col-md-4' : 'col-12';
    }
    if (!useSwitch) {
      container.innerHTML = `<label class="form-label small mb-1">${label}</label>`;
    }
    let input;
    if (type === 'color') {
      // Create modern color picker wrapper
      const colorWrapper = document.createElement('div');
      colorWrapper.className = 'modern-color-picker';
      
      // Color swatch
      const swatch = document.createElement('div');
      swatch.className = 'color-picker-swatch';
      const colorValue = /^#/.test(value) ? value : (defaults[key] || '#000000');
      swatch.style.setProperty('--swatch-color', colorValue);
      
      // Hidden native color input
      input = document.createElement('input');
      input.type = 'color';
      input.value = colorValue;
      input.className = 'color-picker-native';
      input.setAttribute('data-key', key);
      
      // Display wrapper
      const displayWrapper = document.createElement('div');
      displayWrapper.className = 'color-picker-input-wrapper';
      
      const display = document.createElement('div');
      display.className = 'color-picker-display';
      
      const hexSpan = document.createElement('span');
      hexSpan.className = 'color-picker-hex';
      hexSpan.textContent = colorValue.toUpperCase();
      
      const icon = document.createElement('i');
      icon.className = 'bi bi-palette color-picker-icon';
      
      display.appendChild(hexSpan);
      display.appendChild(icon);
      displayWrapper.appendChild(display);
      displayWrapper.appendChild(input);
      
      // Update display when color changes
      const updateDisplay = () => {
        const newColor = input.value;
        swatch.style.setProperty('--swatch-color', newColor);
        hexSpan.textContent = newColor.toUpperCase();
      };
      
      input.addEventListener('input', updateDisplay);
      input.addEventListener('change', updateDisplay);
      
      // Make display clickable
      display.addEventListener('click', () => input.click());
      swatch.addEventListener('click', () => input.click());
      
      colorWrapper.appendChild(swatch);
      colorWrapper.appendChild(displayWrapper);
      
      // Replace container content
      container.innerHTML = `<label class="form-label small mb-1">${label}</label>`;
      container.appendChild(colorWrapper);
      
      // Store reference for sync and add event listener
      input.dataset.key = key;
      input.addEventListener('change', syncOverrides);
      return container;
    } else if (type === 'number') {
      input = document.createElement('input');
      input.type = 'number';
      if (effMin != null) input.min = effMin;
      if (effMax != null) input.max = effMax;
      if (field.step != null) input.step = field.step;
      input.value = (value ?? '');
      (function clampInitialNumber() {
        if (input.value === '' || input.value == null) return;
        const n = Number(input.value);
        if (isNaN(n)) return;
        let c = n;
        if (effMin != null && c < effMin) c = effMin;
        if (effMax != null && c > effMax) c = effMax;
        if (c !== n) input.value = String(c);
      })();
      input.className = 'form-control' + (isMarginField ? ' form-control-sm' : '');
      input.addEventListener('change', () => {
        const n = Number(input.value);
        if (!isNaN(n) && (effMin != null || effMax != null)) {
          let clamped = n;
          if (effMin != null && clamped < effMin) clamped = effMin;
          if (effMax != null && clamped > effMax) clamped = effMax;
          if (clamped !== n) {
            input.value = clamped;
          }
        }
      });
    } else if (type === 'boolean') {
      input = document.createElement('input');
      input.type = 'checkbox';
      input.checked = !!value;
      input.className = 'form-check-input';
      if (useSwitch) {
        const id = `skin_${key}`;
        input.id = id;
        const switchWrap = document.createElement('div');
        switchWrap.className = 'form-check form-switch';
        const lbl = document.createElement('label');
        lbl.className = 'form-check-label';
        lbl.htmlFor = id;
        lbl.textContent = label;
        switchWrap.appendChild(input);
        switchWrap.appendChild(lbl);
        container.appendChild(switchWrap);
        input.dataset.key = key;
        input.addEventListener('change', syncOverrides);
        return container;
      }
    } else if (type === 'select') {
      input = document.createElement('select');
      input.className = 'form-select';
      (field.options || []).forEach(opt => {
        const o = document.createElement('option');
        o.value = opt; o.textContent = opt; if (opt === value) o.selected = true; input.appendChild(o);
      });
    } else {
      input = document.createElement('input');
      input.type = 'text';
      if (field.maxLength != null) input.maxLength = field.maxLength;
      input.value = value ?? '';
      input.className = 'form-control';
    }
    input.dataset.key = key;
    input.addEventListener('change', syncOverrides);
    container.appendChild(input);
    return container;
  };

  const resolveFieldValue = (field) => {
    const key = field.key;
    if (key === 'outputHeight') {
      if (savedOverrides[key] !== undefined) return savedOverrides[key];
      if (savedOverrides.height !== undefined) return savedOverrides.height;
      if (defaults[key] !== undefined) return defaults[key];
      if (defaults.height !== undefined) return defaults.height;
    } else if (key === 'outputWidth') {
      const formEl = document.getElementById('timer_form');
      const serverOutputWidth = firstNumber(
        formEl?.dataset.timerOutputWidth,
        formEl?.dataset.timerWidth
      );
      if (savedOverrides[key] !== undefined) return savedOverrides[key];
      if (savedOverrides.width !== undefined) return savedOverrides.width;
      if (!isNaN(serverOutputWidth)) return serverOutputWidth;
      if (defaults[key] !== undefined) return defaults[key];
      if (defaults.width !== undefined) return defaults.width;
    }
    return (savedOverrides[key] !== undefined) ? savedOverrides[key] : defaults[key];
  };

  // Build hierarchical group tree from fields
  const groupTree = buildGroupTree(visibleFields, defaults);
  const grouped = flattenGroupTree(groupTree);

  // Determine whether the "Time's Up Message" toggle should start checked.
  // Use the same `savedOverrides` object passed into renderFields (edit load, skin bar, etc.),
  // not a re-parse of #skin_overrides_input — the hidden field can lag or fail JSON.parse after
  // HTML escaping, which incorrectly left the toggle off even when the timer had it enabled.
  // Priority:
  // 1) Explicit value from savedOverrides.show_final_message
  // 2) Skin metadata.defaults.show_final_message
  // 3) Otherwise OFF by default
  const showFinalMessage = (() => {
    if (savedOverrides.show_final_message !== undefined) {
      return !!savedOverrides.show_final_message;
    }
    const metaDefaults = metadata.defaults || {};
    if (metaDefaults.show_final_message !== undefined) {
      return !!metaDefaults.show_final_message;
    }
    return false;
  })();
  let finalCardRef = null;

  function relocateVisibilityTogglesFromGroup(group) {
    const visibilityToggles = group.fields.filter(field => visibilityToggleKeys.has(field.key));
    const remainingFields = group.fields.filter(field => !visibilityToggleKeys.has(field.key));
    const togglesCol = document.getElementById('visibility_toggles_col');
    const togglesInner = document.getElementById('visibility_toggles_inner') || togglesCol;
    const fontCol = document.getElementById('font_field_col');

    if (togglesInner) {
      togglesInner.innerHTML = '';
      if (visibilityToggles.length > 0) {
        visibilityToggles.forEach(field => {
          const value = resolveFieldValue(field);
          const displayField = { ...field, label: (field.label || '').replace(/^Show\s+/i, '') };
          const fieldContainer = createFieldInput(displayField, value, { useSwitch: true });
          const classes = fieldContainer.className.split(' ').filter(c => !c.startsWith('col-'));
          fieldContainer.className = classes.join(' ').trim();
          togglesInner.appendChild(fieldContainer);
        });
      }
    }

    if (togglesCol && fontCol) {
      if (visibilityToggles.length > 0) {
        togglesCol.className = 'col-12 col-lg-6';
        fontCol.className = 'col-12 col-lg-6';
      } else {
        togglesCol.className = 'd-none';
        fontCol.className = 'col-12';
      }
    }

    group.fields = remainingFields;
  }

  function renderAppearanceGroupBody(body, fields) {
    if (fields.length === 0) return;
    const firstRow = document.createElement('div');
    firstRow.className = 'row g-3';
    fields.slice(0, 3).forEach(field => {
      const value = resolveFieldValue(field);
      const fieldContainer = createFieldInput(field, value);
      const classes = fieldContainer.className.split(' ').filter(c => !c.startsWith('col-'));
      fieldContainer.className = 'col-12 col-sm-4 ' + classes.join(' ').trim();
      firstRow.appendChild(fieldContainer);
    });
    body.appendChild(firstRow);

    const tail = fields.slice(3);
    for (let i = 0; i < tail.length; i += 2) {
      const row = document.createElement('div');
      row.className = 'row g-3';
      tail.slice(i, i + 2).forEach(field => {
        const value = resolveFieldValue(field);
        const fieldContainer = createFieldInput(field, value);
        const classes = fieldContainer.className.split(' ').filter(c => !c.startsWith('col-'));
        fieldContainer.className = 'col-12 col-sm-6 ' + classes.join(' ').trim();
        row.appendChild(fieldContainer);
      });
      body.appendChild(row);
    }
  }

  function renderDimensionsGroupBody(body, fields) {
    for (let i = 0; i < fields.length; i += 2) {
      const row = document.createElement('div');
      row.className = 'row g-3';
      fields.slice(i, i + 2).forEach(field => {
        const value = resolveFieldValue(field);
        const fieldContainer = createFieldInput(field, value);
        const classes = fieldContainer.className.split(' ').filter(c => !c.startsWith('col-'));
        fieldContainer.className = 'col-12 col-sm-6 ' + classes.join(' ').trim();
        row.appendChild(fieldContainer);
      });
      body.appendChild(row);
    }
  }

  function renderDefaultGroupBody(body, fields, isVisibilityGroup) {
    const row = document.createElement('div');
    row.className = 'row g-3';
    fields.forEach(field => {
      const value = resolveFieldValue(field);
      const fieldContainer = createFieldInput(field, value, { useSwitch: isVisibilityGroup });
      if (isVisibilityGroup) {
        const classes = fieldContainer.className.split(' ').filter(c => !c.startsWith('col-'));
        fieldContainer.className = 'col-12 ' + classes.join(' ').trim();
      }
      row.appendChild(fieldContainer);
    });
    body.appendChild(row);
  }

  function renderGroupFieldsForCategory(body, group, isVisibilityGroup) {
    if (group.fields.length === 0) return;
    const isAppearanceGroup = group.name === 'Appearance' || group.path.some(p => p.toLowerCase() === 'appearance');
    const isDimensionsGroup = group.name === 'Dimensions' || group.path.some(p => p.toLowerCase() === 'dimensions');
    if (isAppearanceGroup) {
      renderAppearanceGroupBody(body, group.fields);
    } else if (isDimensionsGroup) {
      renderDimensionsGroupBody(body, group.fields);
    } else {
      renderDefaultGroupBody(body, group.fields, isVisibilityGroup);
    }
  }

  function wireFinalScreenGroupUi(parentContainer, col, card, level, isFinalGroup) {
    if (!isFinalGroup || level !== 0) return;
    const toggleCol = document.createElement('div');
    toggleCol.className = 'col-12 mb-0';
    toggleCol.innerHTML = `
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="show_final_message" id="show_final_message" value="1" ${showFinalMessage ? 'checked' : ''} data-key="show_final_message">
          <label class="form-check-label" for="show_final_message">
            <strong>Time's Up Message</strong>
          </label>
        </div>
      `;
    parentContainer.appendChild(toggleCol);
    const toggleInput = toggleCol.querySelector('#show_final_message');
    if (toggleInput) {
      toggleInput.addEventListener('change', () => {
        if (finalCardRef) {
          finalCardRef.classList.toggle('d-none', !toggleInput.checked);
          finalCardRef.querySelector('.card')?.classList.toggle('d-none', !toggleInput.checked);
        }
        syncOverrides();
      });
    }
    col.classList.toggle('d-none', !showFinalMessage);
    card.classList.toggle('d-none', !showFinalMessage);
    finalCardRef = col;
  }

  // Recursive function to render groups and subgroups
  function renderGroup(group, parentContainer, level = 0) {
    const isFinalGroup = group.path.some(p => p.toLowerCase().includes('final'));
    const isVisibilityGroup = group.name === 'Visibility' || group.path.some(p => p.toLowerCase() === 'visibility');
    const isLayoutGroup = group.name === 'Layout' || group.path.some(p => p.toLowerCase() === 'layout');
    /** Keep groups in DOM for overrides/sync; hide in UI (days/seconds live in top bar; layout not editable here). */
    const hideSkinGroupCard = isVisibilityGroup || isLayoutGroup;

    if (isVisibilityGroup && Array.isArray(group.fields) && group.fields.length > 0) {
      relocateVisibilityTogglesFromGroup(group);
    }

    const hasChildren = group.children && group.children.length > 0;
    if (!group.fields.length && !hasChildren && !isFinalGroup) return;

    const col = document.createElement('div');
    col.className = 'col-12';
    if (hideSkinGroupCard) {
      col.classList.add('d-none');
    }

    const card = document.createElement('div');
    card.className = 'card border shadow-sm mb-3';
    const bodyId = `skin_group_${group.pathKey.replace(/\./g, '_').replace(/[^a-zA-Z0-9_]/g, '_')}`;

    const header = document.createElement('div');
    header.className = 'card-header bg-light py-2 collapsible-header';
    header.dataset.target = `#${bodyId}`;
    let displayTitle = group.title;
    if (group.name === 'Appearance' || group.title === 'Appearance') displayTitle = 'Design';
    else if (group.name === 'Final Screen' || group.title === 'Final Screen' || group.path?.some(p => String(p).toLowerCase().includes('final'))) displayTitle = 'End Message';
    const isDesignGroup =
      group.name === 'Appearance' ||
      group.title === 'Appearance' ||
      group.path.some(p => String(p).toLowerCase() === 'appearance');

    const body = document.createElement('div');
    body.className = 'card-body collapsible-body';
    body.id = bodyId;

    if (group.children && group.children.length > 0) {
      group.children.forEach(childGroup => {
        renderGroup(childGroup, body, level + 1);
      });
    }

    renderGroupFieldsForCategory(body, group, isVisibilityGroup);

    if (isDesignGroup) {
      header.classList.add('d-flex', 'align-items-center', 'justify-content-between', 'gap-2', 'flex-wrap');
      const titleWrap = document.createElement('div');
      titleWrap.className = 'd-flex align-items-center gap-2';
      titleWrap.innerHTML = `<span class="text-uppercase text-muted fw-semibold small">${displayTitle}</span><i class="bi bi-chevron-down collapse-icon"></i>`;
      const colorizerBtn = document.createElement('button');
      colorizerBtn.type = 'button';
      colorizerBtn.className = 'skin-colorizer-btn';
      colorizerBtn.setAttribute('title', 'Assign random unique colors to all fields in this section');
      colorizerBtn.innerHTML =
        '<i class="bi bi-palette-fill" aria-hidden="true"></i><span>Colorizer</span>';
      colorizerBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        colorizeDesignGroupColors(body);
      });
      header.appendChild(titleWrap);
      header.appendChild(colorizerBtn);
    } else {
      header.innerHTML = `<span class="text-uppercase text-muted fw-semibold small">${displayTitle}</span><i class="bi bi-chevron-down collapse-icon float-end"></i>`;
    }

    card.appendChild(header);
    card.appendChild(body);
    col.appendChild(card);
    wireFinalScreenGroupUi(parentContainer, col, card, level, isFinalGroup);
    parentContainer.appendChild(col);
  }

  // Render all groups
  grouped.forEach(group => {
    renderGroup(group, fieldsWrap, 0);
  });

  // Update Template card visibility based on whether there are any rendered fields
  const templateCard = document.getElementById('template_card');
  if (templateCard) {
    // Check if there are any visible field elements in the skin_fields container
    const hasVisibleFields = fieldsWrap.children.length > 0;
    templateCard.classList.toggle('d-none', !hasVisibleFields);
    
    // Also update skin_overrides_section visibility
    const skinOverridesSection = document.getElementById('skin_overrides_section');
    if (skinOverridesSection) {
      skinOverridesSection.classList.toggle('d-none', !hasVisibleFields);
    }
  }

  const gifHField = findGifHeightField();
  if (gifHField && !gifHField.dataset.boundGifHeight) {
    const handler = () => {
      clampCustomTextDistance();
      enforceGifHeightMinimum();
    };
    gifHField.addEventListener('change', handler);
    gifHField.dataset.boundGifHeight = '1';
  }

  initPaddingHeightSyncState();

  // Sync initial state to hidden input
  syncOverrides();
  // Enable collapsible behavior for newly rendered groups
  setupCollapsibles(fieldsWrap);
  clampCustomTextDistance();
  enforceGifHeightMinimum();
}

/** @param {Record<string, unknown>} data */
function appendCustomTextFontToOverrides(data) {
  const customTextFontInput = document.querySelector('select[name="customTextFont"]');
  if (customTextFontInput?.value) {
    data['customTextFont'] = customTextFontInput.value;
  }
}

function syncOverrides() {
  const fieldsWrap = document.getElementById('skin_fields');
  // Collect all skin-related inputs across the form (not just inside the Template card),
  // so that relocated controls like the visibility toggles are included.
  const inputs = document.querySelectorAll('[data-key]');
  const data = { ...(currentHiddenOverrides || {}) };
  const defaults = currentSkinMetadata?.defaults || {};
  
  // Fields that are hidden but should always use defaults
  // Note: accentColor is NOT hidden - it's needed for controlling tile/box/ring colors
  const hiddenFields = new Set(['fontPath', 'font', 'showDays', 'showHours', 'showMinutes', 'showSeconds', 'layout', 'animation']);
  
  // For hidden fields:
  // - Font fields: don't store (use skin default)
  // - show* fields: always set to true (always checked)
  hiddenFields.forEach(key => {
    if (key.startsWith('show')) {
      // Always set show* fields to true (always checked)
      data[key] = true;
    }
    // For font fields, don't store anything - will use skin default
  });
  
  inputs.forEach(el => {
    const k = el.dataset.key;
    let val;
    if (el.type === 'checkbox') {
      val = !!el.checked;
    } else if (el.type === 'number') {
      val = el.value === '' ? null : Number(el.value);
    } else {
      val = el.value;
    }
    
    // Store if different from default OR if it's an applied formDefault
    // This ensures formDefaults are always saved, even if they match original defaults
    if (val !== defaults[k] || appliedFormDefaults[k] !== undefined) {
      data[k] = val;
    }

    // Always persist borderColor to ensure the chosen/derived color is saved even if it matches defaults
    if (k === 'borderColor') {
      data[k] = val;
    }
  });

  // Ensure final screen colors mirror primary timer colors
  const resolvedBackground = data.background ?? defaults.background;
  const resolvedTextColor = data.textColor ?? defaults.textColor ?? '#000000';
  if (resolvedBackground) {
    data.finalBackground = resolvedBackground;
  }
  if (resolvedTextColor) {
    data.finalTextColor = resolvedTextColor;
  }
  
  // Include static custom text fields from the Custom Text settings card (stored in skin_overrides)
  // Always save these values so they persist across skin changes
  const customTextColorInput = document.querySelector('input[name="customTextColor"]');
  if (customTextColorInput) {
    const colorVal = customTextColorInput.value;
    if (colorVal) {
      data['customTextColor'] = colorVal;
    }
  }
  
  const customTextDistanceInput = document.querySelector('input[name="customTextDistance"]');
  if (customTextDistanceInput && customTextDistanceInput.value !== '') {
    const dist = Number(customTextDistanceInput.value);
    if (!isNaN(dist)) {
      data['customTextDistance'] = dist;
    }
  }

  const customTextFontSizeInput = document.querySelector('input[name="customTextFontSize"]');
  if (customTextFontSizeInput && customTextFontSizeInput.value !== '') {
    const size = Number(customTextFontSizeInput.value);
    if (!isNaN(size)) {
      data['customTextFontSize'] = size;
    }
  }
  
  appendCustomTextFontToOverrides(data);

  // Include show_final_message toggle
  const showFinalMessageInput = document.querySelector('input[name="show_final_message"]');
  if (showFinalMessageInput) {
    data['show_final_message'] = showFinalMessageInput.checked;
  }
  
  document.getElementById('skin_overrides_input').value = JSON.stringify(data);
  // Remember latest overrides so switching skins applies current user settings
  lastSkinOverrides = data;
  // Generate preview when overrides change
  generatePreviewFrame();
}

let previewTimeout = null;
let createPreviewInFlight = false; // prevent overlapping preview requests

async function generatePreviewFrame() {
  const form = document.getElementById('timer_form');
  if (!form) return;

  // Clear any pending preview generation
  if (previewTimeout) {
    clearTimeout(previewTimeout);
  }

  // Debounce: wait 300ms after last change before generating preview
  previewTimeout = setTimeout(async () => {
    const previewWrap = document.getElementById('timer_preview');
    const loader = document.getElementById('preview_loader');
    const frameImg = document.getElementById('preview_frame');
    const gifImg = document.getElementById('preview_gif');
    const placeholder = document.getElementById('preview_placeholder');

    const skinKey = (document.getElementById('skin_key')?.value || '').trim();
    if (!skinKey) {
      loader.classList.add('d-none');
      placeholder.classList.remove('d-none');
      return;
    }

    // Hide all, show loader
    frameImg.classList.add('d-none');
    gifImg.classList.add('d-none');
    placeholder.classList.add('d-none');
    loader.classList.remove('d-none');

    try {
      const formData = new FormData(form);
      formData.set('skin', skinKey);
      formData.set('skin_overrides', document.getElementById('skin_overrides_input')?.value || '{}');

      // Build URL with optional timer ID
      let timerId = form.dataset.timerId;
      let previewUrl;

      if (timerId) {
        // Existing timer: always hit the per-timer endpoint
        previewUrl = `/timers/${timerId}/preview-frame`;
      } else {
        // Create page without a saved counter yet
        if (createPreviewInFlight) {
          loader.classList.add('d-none');
          // placeholder.classList.remove('d-none');
          return;
        }
        createPreviewInFlight = true;
        previewUrl = '/timers/preview-frame';
      }

      // Explicitly ensure POST method
      formData.set('_method', 'POST');
      const response = await fetch(previewUrl, {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || formData.get('_token')
        },
        body: formData
      });

      const rawText = await response.text();

      if (!response.ok) {
        let detail = `${response.status} ${response.statusText}`;
        const ct = response.headers.get('Content-Type') || '';
        if (ct.includes('application/json')) {
          try {
            const errBody = JSON.parse(rawText);
            if (errBody.errors) {
              detail = Object.entries(errBody.errors)
                .map(([k, v]) => `${k}: ${Array.isArray(v) ? v.join(' ') : v}`)
                .join('; ');
            } else if (errBody.message) {
              detail = errBody.message;
            }
          } catch {
            /* use status-based detail */
          }
        } else {
          const t = rawText.trim();
          if (t) detail = t.slice(0, 300);
        }
        throw new Error(`Failed to generate preview (${detail})`);
      }

      // Response body is plain text containing the URL to the saved PNG.
      // On local, PHP warnings may be printed before the URL, so take the last non-empty line.
      const text = rawText;
      const lines = text.split('\n').map(l => l.trim()).filter(Boolean);
      const url = lines[lines.length - 1];

      // Hide loader, show frame
      loader.classList.add('d-none');
      frameImg.src = url;
      frameImg.classList.remove('d-none');

      // Mark GIF as out-of-date, unless this was the initial suppressed preview
      if (!suppressNextDirtyFlag) {
        gifDirty = true;
      }
      suppressNextDirtyFlag = false;
    } catch (e) {
      console.error('Preview generation error:', e);
      loader.classList.add('d-none');
      // placeholder.classList.remove('d-none');
    } finally {
      // Allow another create-preview attempt if needed
      createPreviewInFlight = false;
    }
  }, 300);
}

function updateSkinScrollBarSelection(key) {
  const bar = document.getElementById('skin_scroll_bar');
  if (!bar) return;
  bar.querySelectorAll('.skin-card').forEach(card => {
    const checkmark = card.querySelector('.skin-checkmark');
    if (card.dataset.skinKey === key) {
      card.classList.add('selected-skin');
      checkmark?.classList.add('selected-checkmark');
    } else {
      card.classList.remove('selected-skin');
      checkmark?.classList.remove('selected-checkmark');
    }
  });
}

/** @returns {Promise<Record<string, unknown> | null>} */
async function fetchSkinFormDefaultOverrides(key, opts) {
  const logErrors = opts?.logErrors !== false;
  try {
    const formDefaultsResponse = await fetchJson(`/api/skins/${encodeURIComponent(key)}/form-defaults`);
    const fd = formDefaultsResponse?.formDefaults;
    if (fd && Object.keys(fd).length > 0) {
      return { ...fd };
    }
  } catch (e) {
    if (logErrors) {
      console.warn('Failed to fetch form defaults:', e);
    }
  }
  return null;
}

function syncEditModeTimerWidthFromSkinDefaults(form, meta) {
  if (!form || !meta?.defaults) return;
  const defW = meta.defaults.outputWidth ?? meta.defaults.width;
  if (defW !== undefined && defW !== null && defW !== '') {
    form.dataset.timerOutputWidth = String(defW);
    form.dataset.timerWidth = String(defW);
  }
}

function schedulePostSkinSelectSync(isSwitchingToDifferentSkin, isEdit) {
  if (isSwitchingToDifferentSkin) {
    setTimeout(() => {
      if (!isEdit) {
        applyDefaultSampleTimeIfEmpty();
      }
      syncOverrides();
    }, 100);
  } else {
    if (!isEdit) {
      applyDefaultSampleTimeIfEmpty();
    }
    generatePreviewFrame();
  }
}

async function selectSkin(key) {
  const previousKey = document.getElementById('skin_key').value || '';
  const isSwitchingToDifferentSkin = previousKey && previousKey !== key;

  document.getElementById('skin_key').value = key;
  document.getElementById('selected_skin_label').textContent = key;
  updateSkinScrollBarSelection(key);

  try {
    const form = document.getElementById('timer_form');
    const isEdit = form?.dataset.isEdit === '1';
    const isFirstLoad = !isEdit && !loadedSkins.has(key);

    const meta = await fetchJson(`/skins/${encodeURIComponent(key)}`);

    let baseOverrides = (isSwitchingToDifferentSkin ? {} : (lastSkinOverrides || {}));

    if (isFirstLoad) {
      const fdOverrides = await fetchSkinFormDefaultOverrides(key);
      if (fdOverrides) {
        appliedFormDefaults = { ...fdOverrides };
        meta.defaults = { ...meta.defaults, ...fdOverrides };
        baseOverrides = { ...fdOverrides };
        loadedSkins.add(key);
      }
    } else {
      appliedFormDefaults = {};
    }

    if (isEdit && isSwitchingToDifferentSkin) {
      syncEditModeTimerWidthFromSkinDefaults(form, meta);
    }

    renderFields(meta, baseOverrides);

    if (isFirstLoad && Object.keys(baseOverrides).length > 0) {
      lastSkinOverrides = { ...baseOverrides };
      setTimeout(() => {
        syncOverrides();
      }, 100);
    }

    const prevEl = document.getElementById('selected_skin_preview');
    if (meta?.preview_url) {
      prevEl.src = meta.preview_url;
      prevEl.classList.remove('d-none');
    }

    schedulePostSkinSelectSync(isSwitchingToDifferentSkin, isEdit);
    updateResetButtonState();
  } catch (e) {
    console.error(e);
  }
}

function resetStaticCustomTextInputsToDefault() {
  const colorInput = document.querySelector('input[name="customTextColor"]');
  if (colorInput) {
    const defaultColor = '#000000';
    colorInput.value = defaultColor;
    const wrapper = colorInput.closest('.modern-color-picker');
    if (wrapper) {
      const swatch = wrapper.querySelector('.color-picker-swatch');
      const hexSpan = wrapper.querySelector('.color-picker-hex');
      if (swatch) swatch.style.setProperty('--swatch-color', defaultColor);
      if (hexSpan) hexSpan.textContent = defaultColor.toUpperCase();
    }
  }
  const distanceInput = document.querySelector('input[name="customTextDistance"]');
  if (distanceInput) distanceInput.value = '0';
  const fontSizeInput = document.querySelector('input[name="customTextFontSize"]');
  if (fontSizeInput) fontSizeInput.value = '';
  const fontSelect = document.querySelector('select[name="customTextFont"]');
  if (fontSelect) fontSelect.value = '';
}

function resetHeadingTimerTypeAndTargetsToDefault() {
  const headingInput = document.querySelector('input[name="custom_text"]');
  const positionSelect = document.querySelector('select[name="text_position"]');
  const timerTypeSelect = document.getElementById('timer_type');
  const targetInput = document.querySelector('input[name="target_datetime"]');
  const durationInput = document.querySelector('input[name="duration_seconds"]');

  if (headingInput) headingInput.value = '';
  if (positionSelect) positionSelect.value = 'none';

  const timerType = timerTypeSelect ? timerTypeSelect.value : 'fixed_date';
  if (timerType === 'fixed_date' && targetInput) {
    const sampleDate = new Date(Date.now() + DEFAULT_SAMPLE_SECONDS * 1000);
    const sampleVal = toLocalDateTimeInputValue(sampleDate);
    targetInput.value = sampleVal;
    targetInput.dataset.initialValue = sampleVal;
    targetInput.dataset.userTouched = '0';
  } else if (timerType === 'reload_based' && durationInput) {
    durationInput.value = '1';
  }
}

async function resetDesignToDefaults() {
  const skinKeyInput = document.getElementById('skin_key');
  if (!skinKeyInput) return;
  const key = (skinKeyInput.value || '').trim();
  if (!key) return;

  try {
    const meta = await fetchJson(`/skins/${encodeURIComponent(key)}`);
    const baseOverrides = (await fetchSkinFormDefaultOverrides(key, { logErrors: false })) || {};

    if (Object.keys(baseOverrides).length > 0) {
      meta.defaults = { ...meta.defaults, ...baseOverrides };
    }

    appliedFormDefaults = { ...baseOverrides };
    lastSkinOverrides = { ...baseOverrides };
    renderFields(meta, baseOverrides);

    resetStaticCustomTextInputsToDefault();
    resetHeadingTimerTypeAndTargetsToDefault();

    if (typeof syncOverrides === 'function') {
      syncOverrides();
    }
    if (typeof generatePreviewFrame === 'function') {
      generatePreviewFrame();
    }
  } catch (e) {
    console.error('Failed to reset design to defaults', e);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  // Initial collapsible setup for static headers (general/customize cards)
  setupCollapsibles(document);

  // Wire up form fields to trigger preview generation
  const form = document.getElementById('timer_form');
  if (form) {
    const previewTriggerFields = [
      // Title does not affect the rendered timer image; avoid regenerating preview while typing.
      'text_position', 'custom_text', 'status', 'timer_type',
      'target_datetime', 'duration_seconds', 'animation_frame_limit'
    ];
    
    previewTriggerFields.forEach(fieldName => {
      const field = form.querySelector(`[name="${fieldName}"]`);
      if (field) {
        field.addEventListener('change', generatePreviewFrame);
        field.addEventListener('input', generatePreviewFrame);
      }
    });
  }

  (async () => {
    try {
      const current = document.getElementById('skin_key').value;
      // Load saved overrides from hidden input (for edit mode)
      const savedOverrides = (() => {
        try {
          return JSON.parse(document.getElementById('skin_overrides_input').value || '{}');
        } catch(e) {
          return {};
        }
      })();
      // Seed outputWidth from existing width/outputWidth (DB or overrides) so Timer Width shows the stored value
      (function seedOutputWidth() {
        const formEl = document.getElementById('timer_form');
        const seed = firstNumber(
          savedOverrides.outputWidth,
          savedOverrides.width,
          formEl?.dataset.timerOutputWidth,
          formEl?.dataset.timerWidth
        );
        if (!isNaN(seed)) {
          savedOverrides.outputWidth = seed;
          if (savedOverrides.width === undefined) {
            savedOverrides.width = seed;
          }
        }
      })();
      
      if (current) {
        const form = document.getElementById('timer_form');
        const isEdit = form?.dataset.isEdit === '1';

        if (isEdit) {
        // Edit mode: load existing skin with saved overrides
        const meta = await fetchJson(`/skins/${encodeURIComponent(current)}`);
        renderFields(meta, savedOverrides);
        // Seed last overrides with saved values so switching skins keeps the user's base settings
        lastSkinOverrides = savedOverrides;
        
        // Load custom text fields from savedOverrides into the static form fields
        if (savedOverrides.customTextColor) {
          const colorInput = document.querySelector('input[name="customTextColor"]');
          if (colorInput) {
            colorInput.value = savedOverrides.customTextColor;
            // Update the display
            const wrapper = colorInput.closest('.modern-color-picker');
            if (wrapper) {
              const swatch = wrapper.querySelector('.color-picker-swatch');
              const hexSpan = wrapper.querySelector('.color-picker-hex');
              if (swatch) swatch.style.setProperty('--swatch-color', savedOverrides.customTextColor);
              if (hexSpan) hexSpan.textContent = savedOverrides.customTextColor.toUpperCase();
            }
          }
        }
        if (savedOverrides.customTextDistance !== undefined) {
          const distanceInput = document.querySelector('input[name="customTextDistance"]');
          if (distanceInput) distanceInput.value = savedOverrides.customTextDistance;
        }
        if (savedOverrides.customTextFontSize !== undefined) {
          const sizeInput = document.querySelector('input[name="customTextFontSize"]');
          if (sizeInput) sizeInput.value = savedOverrides.customTextFontSize;
        }
        if (savedOverrides.customTextFont) {
          const fontSelect = document.querySelector('select[name="customTextFont"]');
          if (fontSelect) fontSelect.value = savedOverrides.customTextFont;
        }
        
        document.getElementById('selected_skin_label').textContent = current;
        if (meta && meta.preview_url) {
          const prevEl = document.getElementById('selected_skin_preview');
          prevEl.src = meta.preview_url;
          prevEl.classList.remove('d-none');
          }
        } else {
          // New timer: load skin and apply formDefaults if available
          await selectSkin(current);
        }
      }
      // Populate top horizontal skin bar
      if (typeof loadSkinsIntoScrollBar === 'function') {
        await loadSkinsIntoScrollBar();
      }
      
      // Generate initial preview
      suppressNextDirtyFlag = true;
      generatePreviewFrame();
      updateResetButtonState();
    } catch (e) {
      // swallow to avoid noisy console during navigation
    }
  })();

  const resetBtn = document.getElementById('reset_design_btn');
  if (resetBtn) {
    resetBtn.addEventListener('click', (e) => {
      e.preventDefault();
      resetDesignToDefaults();
    });
  }

});

function getGifGenerationMode() {
  const form = document.getElementById('timer_form');
  const fromForm = form?.dataset.gifGenerationMode;
  if (fromForm) {
    return fromForm;
  }
  const bodyMode = document.body?.dataset.gifGenerationMode;
  return bodyMode || 'queue';
}

function isGifQueueMode() {
  return getGifGenerationMode() === 'queue';
}

function setFormPreviewButtonPending(pending) {
  const btn = document.getElementById('form_preview_btn');
  if (!btn) return;
  const url = btn.dataset.previewUrl;
  if (pending) {
    btn.classList.add('disabled');
    btn.setAttribute('href', '#');
    btn.setAttribute('aria-disabled', 'true');
    btn.setAttribute('tabindex', '-1');
    btn.setAttribute('title', 'Processing');
  } else {
    btn.classList.remove('disabled');
    if (url) btn.setAttribute('href', url);
    btn.removeAttribute('aria-disabled');
    btn.removeAttribute('tabindex');
    btn.setAttribute('title', 'Preview counter');
  }
}

/** @type {ReturnType<typeof setTimeout> | null} */
let gifPollTimerId = null;

function clearGifPollTimer() {
  if (gifPollTimerId !== null) {
    clearTimeout(gifPollTimerId);
    gifPollTimerId = null;
  }
}

/** Pause after each status response before the next request (ms). */
const GIF_STATUS_POLL_GAP_MS = 2800;

/**
 * Clear client-side validation state from a prior AJAX 422 (inline messages + is-invalid).
 * @param {HTMLFormElement} form
 */
function clearAjaxValidationErrors(form) {
  form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
  form.querySelectorAll('.invalid-feedback[data-ajax-inline]').forEach((el) => el.remove());
  const titleFb = document.getElementById('title_field_feedback');
  if (titleFb) {
    titleFb.textContent = '';
    titleFb.classList.add('d-none');
  }
}

/**
 * Show Laravel validation.errors next to fields (422). No Swal.
 * @param {HTMLFormElement} form
 * @param {Record<string, string[]|string>} errors
 */
function applyLaravelValidationErrors(form, errors) {
  if (!errors || typeof errors !== 'object') return;
  clearAjaxValidationErrors(form);
  /** @type {HTMLElement[]} */
  const focusTargets = [];
  Object.entries(errors).forEach(([name, msgs]) => {
    const input = form.querySelector(`[name="${CSS.escape(name)}"]`);
    if (!input) return;
    const text = Array.isArray(msgs) ? msgs.filter(Boolean).join(' ') : String(msgs || '');
    if (!text) return;
    input.classList.add('is-invalid');
    focusTargets.push(input);
    if (name === 'title') {
      const fb = document.getElementById('title_field_feedback');
      if (fb) {
        fb.textContent = text;
        fb.classList.remove('d-none');
      }
      return;
    }
    let fb = input.nextElementSibling;
    if (!fb || !fb.classList.contains('invalid-feedback')) {
      fb = document.createElement('div');
      fb.className = 'invalid-feedback d-block';
      fb.setAttribute('data-ajax-inline', '1');
      input.insertAdjacentElement('afterend', fb);
    } else {
      fb.setAttribute('data-ajax-inline', '1');
    }
    fb.textContent = text;
    fb.classList.remove('d-none');
  });
  const first = focusTargets[0];
  if (first && typeof first.focus === 'function') first.focus();
}

// AJAX save + preview polling
async function ajaxSaveAndPoll() {
  const form = document.getElementById('timer_form');
  const saveBtn = document.getElementById('save_btn');
  if (!form || !saveBtn) return;

  if (!form.dataset.ajaxValidationClearBound) {
    form.dataset.ajaxValidationClearBound = '1';
    form.addEventListener('input', (e) => {
      const t = e.target;
      if (t && t.name === 'title') {
        t.classList.remove('is-invalid');
        const fb = document.getElementById('title_field_feedback');
        if (fb) {
          fb.textContent = '';
          fb.classList.add('d-none');
        }
      } else if (t && t.classList && t.classList.contains('is-invalid')) {
        const fb = t.nextElementSibling;
        if (fb && fb.classList.contains('invalid-feedback') && fb.getAttribute('data-ajax-inline')) {
          t.classList.remove('is-invalid');
          fb.remove();
        }
      }
    });
  }

  saveBtn.addEventListener('click', async () => {
    clearAjaxValidationErrors(form);
    saveBtn.disabled = true;
    const formData = new FormData(form);
    formData.append('generate_gif', '1');
    try {
      const res = await fetch(form.action, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: formData
      });
      if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        if (res.status === 422 && err.errors && typeof err.errors === 'object') {
          applyLaravelValidationErrors(form, err.errors);
          saveBtn.disabled = false;
          return;
        }
        Swal.fire({
          icon: 'error',
          title: 'Save Failed',
          text: err.message || 'Failed to save. Please check your inputs.'
        });
        saveBtn.disabled = false;
        return;
      }
      const json = await res.json();
      const timerId = json.id || form.dataset.timerId;
      if (!timerId) {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Timer ID not found'
        });
        saveBtn.disabled = false;
        return;
      }
      window.location.href = '/dashboard';
    } catch (e) {
      console.error(e);
      Swal.fire({
        icon: 'error',
        title: 'Network Error',
        text: 'An error occurred while saving. Please try again.'
      });
      saveBtn.disabled = false;
    }
  });
}

/**
 * Poll until GIF is ready (queue mode). Keeps PNG preview visible until then.
 * @param {string|number} timerId
 * @param {{ silent?: boolean }} [opts]
 */
function startPreviewPolling(timerId, opts) {
  const silent = !!opts?.silent;

  clearGifPollTimer();

  if (Swal.isVisible()) {
    Swal.update({
      title: 'Generating GIF...',
      text: 'Please wait while your counter GIF is being created'
    });
  }

  const loader = document.getElementById('preview_loader');
  const frameImg = document.getElementById('preview_frame');
  const gifImg = document.getElementById('preview_gif');
  const placeholder = document.getElementById('preview_placeholder');

  const finishOk = (json) => {
    clearGifPollTimer();
    const form = document.getElementById('timer_form');
    if (form) {
      if (json.status) form.dataset.timerStatus = json.status;
      form.dataset.hasGif = '1';
      form.dataset.gifPending = '0';
      if (json.gif_url) form.dataset.gifUrl = json.gif_url;
    }

    setFormPreviewButtonPending(false);

    if (!silent) {
      if (Swal.isVisible()) {
        Swal.fire({
          icon: 'success',
          title: 'GIF ready',
          text: 'Your counter GIF has been generated.',
          timer: 2000,
          showConfirmButton: false
        });
      } else if (typeof window.showToast === 'function') {
        window.showToast('GIF is ready', 'success');
      }
    } else if (typeof window.showToast === 'function') {
      window.showToast('GIF is ready', 'success');
    }

    const saveBtn = document.getElementById('save_btn');
    if (saveBtn) saveBtn.disabled = false;

    if (loader) loader.classList.add('d-none');
    if (placeholder) placeholder.classList.add('d-none');
    if (gifImg) {
      gifImg.src = json.gif_url_with_cache_bust || json.gif_url;
      gifImg.classList.remove('d-none');
    }
    if (frameImg) frameImg.classList.add('d-none');

    gifDirty = false;
  };

  const scheduleNextPoll = () => {
    clearGifPollTimer();
    gifPollTimerId = setTimeout(runPoll, GIF_STATUS_POLL_GAP_MS);
  };

  const runPoll = () => {
    gifPollTimerId = null;
    fetch(`/timers/${encodeURIComponent(timerId)}/status`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }
    })
      .then((res) => (res.ok ? res.json() : Promise.reject(new Error(`GIF status HTTP ${res.status}`))))
      .then((json) => {
        if (json?.gif_ready) {
          finishOk(json);
          return;
        }
        scheduleNextPoll();
      })
      .catch(() => {
        scheduleNextPoll();
      });
  };

  gifPollTimerId = setTimeout(runPoll, 600);
}

ajaxSaveAndPoll();

// Auto-poll on edit page load when GIF is still processing (queue mode)
(function autoPollOnLoad(){
  const form = document.getElementById('timer_form');
  if (!form) return;
  if (!isGifQueueMode()) return;
  if (form.dataset.isEdit === '1' && form.dataset.gifPending === '1' && form.dataset.timerId) {
    startPreviewPolling(form.dataset.timerId, { silent: true });
  }
})();

document.addEventListener('click', (e) => {
  const a = e.target.closest('#form_preview_btn.disabled, a.dashboard-preview-btn.disabled');
  if (a) e.preventDefault();
}, true);

document.addEventListener('DOMContentLoaded', function(){
  // Toggle pill behavior for select replacements (status, timer_type)
  (function bindToggleGroups() {
    const bindToggleGroup = (group) => {
      const selectName = group.dataset.toggleSelect;
      if (!selectName) return;
      const select = document.querySelector(`select[name="${selectName}"]`);
      if (!select) return;
      const buttons = Array.from(group.querySelectorAll('.toggle-pill'));
      const track = group.querySelector('.toggle-pill-track');
      const setActive = (value) => {
        buttons.forEach(btn => btn.classList.toggle('active', btn.dataset.value === value));
        if (track) {
          const activeBtn = buttons.find(btn => btn.dataset.value === value) || buttons[0];
          if (activeBtn) {
            track.style.width = `${activeBtn.offsetWidth}px`;
            track.style.transform = `translateX(${activeBtn.offsetLeft}px)`;
          }
        }
      };
      const initial = select.value || (buttons[0]?.dataset.value ?? '');
      select.value = initial;
      setActive(initial);
      buttons.forEach(btn => {
        btn.addEventListener('click', () => {
          const val = btn.dataset.value;
          select.value = val;
          setActive(val);
          // If this is the timer_type select, call toggleInputs with user action flag
          if (selectName === 'timer_type' && typeof toggleInputs === 'function') {
            toggleInputs(true);
          }
          select.dispatchEvent(new Event('change', { bubbles: true }));
          select.dispatchEvent(new Event('input', { bubbles: true }));
        });
      });
      select.addEventListener('change', () => setActive(select.value));
    };
    document.querySelectorAll('.toggle-pill-group').forEach(bindToggleGroup);
    const timerTypeSelect = document.getElementById('timer_type');
    if (timerTypeSelect) {
      // Ensure timer type-dependent inputs start in correct visibility
      timerTypeSelect.dispatchEvent(new Event('change', { bubbles: true }));
    }
  })();

  if (!window.copyToClipboard) {
    window.copyToClipboard = function(text){
      if (navigator.clipboard && navigator.clipboard.writeText) {
        return navigator.clipboard.writeText(text);
      }
      return new Promise(function(resolve, reject){
        try {
          var textarea = document.createElement('textarea');
          textarea.value = text;
          textarea.setAttribute('readonly', '');
          textarea.style.position = 'absolute';
          textarea.style.left = '-9999px';
          document.body.appendChild(textarea);
          textarea.select();
          var successful = document.execCommand('copy');
          document.body.removeChild(textarea);
          successful ? resolve() : reject(new Error('Copy command unsuccessful'));
        } catch (err) {
          reject(err);
        }
      });
    };
  }
});