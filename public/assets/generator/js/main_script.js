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
  const minVal = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
  input.min = minVal;
  if (input.value && input.value < minVal) {
    input.value = minVal;
  }
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
    overrides.outputHeight,
    overrides.height,
    defaults.outputHeight,
    defaults.height
  );

  const timerHeight = firstNumber(
    findFieldByKey('height')?.value,
    overrides.height,
    defaults.height
  );

  const fontSizeRaw = firstNumber(
    findFieldByKey('fontSize')?.value,
    overrides.fontSize,
    defaults.fontSize
  );
  const fontSize = isNaN(fontSizeRaw) ? 36 : fontSizeRaw;
  const textSize = Math.max(12, Math.round(fontSize * 0.5));

  const textVal = (document.querySelector('input[name="custom_text"]')?.value || '').trim();
  const textPos = document.querySelector('select[name="text_position"]')?.value || 'none';
  const wantsText = textVal !== '' && textPos !== 'none';

  const distanceRaw = Number(document.querySelector('input[name="customTextDistance"]')?.value);
  const distance = Math.max(0, isNaN(distanceRaw) ? 0 : distanceRaw);

  const combinedHeight = timerHeight + (wantsText ? (textSize + distance) : 0) + (edgePad * 2);
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

function clampCustomTextDistance() {
  const distanceInput = document.querySelector('input[name="customTextDistance"]');
  if (!distanceInput) return;
  const sizing = resolveSizingSnapshot();
  const gifHeight = sizing.gifHeight;
  const timerHeight = sizing.timerHeight;

  if (isNaN(gifHeight) || isNaN(timerHeight)) {
    if (!window.__clampWarnedOnce) {
      window.__clampWarnedOnce = true;
      const msg = 'Set GIF height to control text distance.';
      if (window.showToast) {
        window.showToast(msg, 'warning');
      } else {
        console.warn(msg);
      }
    }
    return;
  }

  // Available vertical space for distance, based on GIF (canvas) height
  const maxDistance = sizing.maxDistance;
  const desired = Number(distanceInput.value);
  if (!isNaN(desired) && desired > maxDistance) {
    distanceInput.value = maxDistance;
    const msg = `Increase GIF height to add more spacing. Max distance right now is ${maxDistance}px.`;
    if (window.showToast) {
      window.showToast(msg, 'warning');
    } else {
      console.warn(msg);
    }
  }
}

function enforceGifHeightMinimum() {
  const heightField = findFieldByKey('outputHeight');
  if (!heightField) return;
  const sizing = resolveSizingSnapshot();

  if (isNaN(sizing.gifHeight) || isNaN(sizing.timerHeight)) {
    return;
  }

  const required = Math.max(0, Math.ceil(sizing.combinedHeight || 0));
  if (!isNaN(required) && required > 0 && sizing.gifHeight < required) {
    heightField.value = required;
    const msg = `GIF height must be at least ${required}px to fit the timer and text.`;
    if (window.showToast) {
      window.showToast(msg, 'warning');
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
  
  // Sync distance field to skin_overrides when changed
  const distanceInput = document.querySelector('input[name="customTextDistance"]');
  if (distanceInput) {
    const handleDistanceChange = () => {
      clampCustomTextDistance();
      enforceGifHeightMinimum();
      syncOverrides();
    };
    distanceInput.addEventListener('change', handleDistanceChange);
    distanceInput.addEventListener('input', handleDistanceChange);
  }

  // If timer height changes, re-clamp distance
  ['outputHeight'].forEach(name => {
    const el = document.querySelector(`[data-key="${name}"]`) || document.querySelector(`input[name="${name}"]`);
    if (el) {
      const handler = () => {
        clampCustomTextDistance();
        enforceGifHeightMinimum();
      };
      el.addEventListener('change', handler);
      el.addEventListener('input', handler);
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
      if (!firstCard) return 296; // fallback: 280px card + 16px gap
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
      wrapper.className = 'skin-card mine d-flex flex-column align-items-center text-center rounded bg-white flex-shrink-0';
      wrapper.style.width = '280px';
      wrapper.style.cursor = 'pointer';
      wrapper.dataset.skinKey = s.key;

      const preview = document.createElement('div');
      preview.className = 'd-flex align-items-center justify-content-center rounded w-100';
      preview.style.height = '96px';
      preview.style.position = 'relative';

      const img = document.createElement('img');
      img.src = s.preview_url;
      img.alt = s.name;
      img.className = 'img-fluid';
      img.style.maxHeight = '92px';
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

function renderFields(metadata, savedOverrides = {}) {
  currentSkinMetadata = metadata;
  currentHiddenOverrides = {};
  const fieldsWrap = document.getElementById('skin_fields');
  const defaults = metadata.defaults || {};

  // For create flows only, enforce a visible border default when skin is missing or zero
  const formEl = document.getElementById('timer_form');
  const isEdit = formEl && formEl.dataset.isEdit === '1';
  if (!isEdit) {
    if (defaults.borderWidth === undefined || defaults.borderWidth === null || Number(defaults.borderWidth) === 0) {
      defaults.borderWidth = 1;
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
      defaults.borderWidth = 1;
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
  const alwaysHiddenFields = new Set(['fontPath', 'font', 'customTextColor', 'customTextDistance', 'custom_text', 'text_position', 'finalBackground', 'finalTextColor']);
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

  const createFieldInput = (field, value) => {
    const key = field.key;
    let label = field.label || key;
    if (key === 'outputHeight') {
      label = 'Counter Height';
    } else if (key === 'outputWidth') {
      label = 'Counter Width';
    }
    const type = field.type || 'text';
    const container = document.createElement('div');
    const isSmall = type === 'color' || type === 'boolean' || smallKeys.has(key);
    const isMarginField = marginKeys.has(key);
    if (isMarginField) {
      container.className = 'col-12 col-sm-4 col-md-3';
    } else {
      container.className = isSmall ? 'col-12 col-sm-6 col-md-4' : 'col-12';
    }
    container.innerHTML = `<label class="form-label small mb-1">${label}</label>`;
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
      if (field.min != null) input.min = field.min;
      if (field.max != null) input.max = field.max;
      if (field.step != null) input.step = field.step;
      input.value = (value ?? '');
      input.className = 'form-control' + (isMarginField ? ' form-control-sm' : '');
    } else if (type === 'boolean') {
      input = document.createElement('input');
      input.type = 'checkbox';
      input.checked = !!value;
      input.className = 'form-check-input';
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

  // Read saved overrides to determine initial toggle state for final screen
  const savedOverridesForFinal = (() => {
    try {
      return JSON.parse(document.getElementById('skin_overrides_input').value || '{}');
    } catch (e) {
      return {};
    }
  })();
  const showFinalMessage = !!savedOverridesForFinal.show_final_message;
  let finalCardRef = null;

  // Recursive function to render groups and subgroups
  function renderGroup(group, parentContainer, level = 0) {
    // Skip empty groups (except for final which has special handling)
    // But always render groups that have children (subgroups), even if they have no direct fields
    const isFinalGroup = group.path.some(p => p.toLowerCase().includes('final'));
    const isVisibilityGroup = group.name === 'Visibility' || group.path.some(p => p.toLowerCase() === 'visibility');
    const hasChildren = group.children && group.children.length > 0;
    if (!group.fields.length && !hasChildren && !isFinalGroup) return;
    
    const col = document.createElement('div');
    col.className = 'col-12';
    // Hide Visibility group by default - fields will still be processed and applied but not shown to user
    if (isVisibilityGroup) {
      col.classList.add('d-none');
    }
    
    const card = document.createElement('div');
    card.className = 'card border shadow-sm mb-3';
    const bodyId = `skin_group_${group.pathKey.replace(/\./g, '_').replace(/[^a-zA-Z0-9_]/g, '_')}`;
    
    const header = document.createElement('div');
    header.className = 'card-header bg-light py-2 collapsible-header';
    header.dataset.target = `#${bodyId}`;
    header.innerHTML = `<span class="text-uppercase text-muted fw-semibold small">${group.title}</span><i class="bi bi-chevron-down collapse-icon float-end"></i>`;
    
    const body = document.createElement('div');
    body.className = 'card-body collapsible-body';
    body.id = bodyId;
    
    // If this group has children (subgroups), render them first
    if (group.children && group.children.length > 0) {
      group.children.forEach(childGroup => {
        renderGroup(childGroup, body, level + 1);
      });
    }
    
    // Add fields for this group (even if hidden, fields are still created so their values are applied)
    if (group.fields.length > 0) {
      const row = document.createElement('div');
      row.className = 'row g-3';
      group.fields.forEach(field => {
        const value = resolveFieldValue(field);
        row.appendChild(createFieldInput(field, value));
      });
      body.appendChild(row);
    }
    
    card.appendChild(header);
    card.appendChild(body);
    col.appendChild(card);

    // Special handling for final screen group
    if (isFinalGroup && level === 0) {
      const toggleCol = document.createElement('div');
      toggleCol.className = 'col-12 mb-0';
      toggleCol.innerHTML = `
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="show_final_message" id="show_final_message" value="1" ${showFinalMessage ? 'checked' : ''} data-key="show_final_message">
          <label class="form-check-label" for="show_final_message">
            <strong>Show Time's Up Screen</strong>
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

  const outputHeightField = findFieldByKey('outputHeight');
  if (outputHeightField && !outputHeightField.dataset.boundGifHeight) {
    const handler = () => {
      clampCustomTextDistance();
      enforceGifHeightMinimum();
    };
    outputHeightField.addEventListener('change', handler);
    outputHeightField.addEventListener('input', handler);
    outputHeightField.dataset.boundGifHeight = '1';
  }

  // Sync initial state to hidden input
  syncOverrides();
  // Enable collapsible behavior for newly rendered groups
  setupCollapsibles(fieldsWrap);
  clampCustomTextDistance();
  enforceGifHeightMinimum();
}

function syncOverrides() {
  const fieldsWrap = document.getElementById('skin_fields');
  const inputs = fieldsWrap.querySelectorAll('[data-key]');
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

    // Hide all, show loader
    frameImg.classList.add('d-none');
    gifImg.classList.add('d-none');
    placeholder.classList.add('d-none');
    loader.classList.remove('d-none');

    try {
      const formData = new FormData(form);
      formData.append('skin', document.getElementById('skin_key').value || '');
      formData.append('skin_overrides', document.getElementById('skin_overrides_input').value || '{}');

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
          placeholder.classList.remove('d-none');
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

      if (!response.ok) {
        throw new Error('Failed to generate preview');
      }

      // Response body is plain text containing the URL to the saved PNG.
      // On local, PHP warnings may be printed before the URL, so take the last non-empty line.
      const text = await response.text();
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
      placeholder.classList.remove('d-none');
    } finally {
      // Allow another create-preview attempt if needed
      createPreviewInFlight = false;
    }
  }, 300);
}

async function selectSkin(key) {
  document.getElementById('skin_key').value = key;
  document.getElementById('selected_skin_label').textContent = key;
  
  // Update visual selection in scroll bar
  const bar = document.getElementById('skin_scroll_bar');
  if (bar) {
    bar.querySelectorAll('.skin-card').forEach(card => {
      const checkmark = card.querySelector('.skin-checkmark');
      if (card.dataset.skinKey === key) {
        card.classList.add('selected-skin');
        if (checkmark) {
          checkmark.classList.add('selected-checkmark');
        }
      } else {
        card.classList.remove('selected-skin');
        if (checkmark) {
          checkmark.classList.remove('selected-checkmark');
        }
      }
    });
  }
  
  try {
    const form = document.getElementById('timer_form');
    const isEdit = form && form.dataset.isEdit === '1';
    const isFirstLoad = !isEdit && !loadedSkins.has(key);
    
    const meta = await fetchJson(`/skins/${encodeURIComponent(key)}`);
    
    let baseOverrides = lastSkinOverrides || {};
    
    // For new timers, fetch and apply formDefaults on first skin load
    if (isFirstLoad) {
      try {
        const formDefaultsResponse = await fetchJson(`/api/skins/${encodeURIComponent(key)}/form-defaults`);
        if (formDefaultsResponse && formDefaultsResponse.formDefaults && Object.keys(formDefaultsResponse.formDefaults).length > 0) {
          // Store applied formDefaults so they're always saved (even if they match original defaults)
          appliedFormDefaults = { ...formDefaultsResponse.formDefaults };
          // Merge formDefaults into metadata.defaults so they appear in form fields
          // This ensures the form fields show the formDefaults values
          meta.defaults = { ...meta.defaults, ...formDefaultsResponse.formDefaults };
          // Also set as baseOverrides so they're saved
          baseOverrides = { ...formDefaultsResponse.formDefaults };
          // Mark this skin as loaded
          loadedSkins.add(key);
        }
      } catch (e) {
        console.warn('Failed to fetch form defaults:', e);
      }
    } else {
      // Clear appliedFormDefaults when switching to a different skin
      appliedFormDefaults = {};
    }
    
    // When changing skin, apply the latest user overrides (carry settings across skins)
    renderFields(meta, baseOverrides);
    
    // If we applied formDefaults, sync them to the hidden input immediately
    if (isFirstLoad && Object.keys(baseOverrides).length > 0) {
      // Update lastSkinOverrides so they get saved
      lastSkinOverrides = { ...baseOverrides };
      // Small delay to ensure form fields are rendered before syncing
      setTimeout(() => {
        syncOverrides();
      }, 100);
    }
    
    const prevEl = document.getElementById('selected_skin_preview');
    if (meta && meta.preview_url) {
      prevEl.src = meta.preview_url;
      prevEl.classList.remove('d-none');
    }
    // Generate preview frame after skin change
    generatePreviewFrame();
  } catch (e) {
    console.error(e);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  // Initial collapsible setup for static headers (general/customize cards)
  setupCollapsibles(document);

  // Wire up form fields to trigger preview generation
  const form = document.getElementById('timer_form');
  if (form) {
    const previewTriggerFields = [
      'title', 'text_position', 'custom_text', 'status', 'timer_type',
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
        const isEdit = form && form.dataset.isEdit === '1';
        
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
    } catch (e) {
      // swallow to avoid noisy console during navigation
    }
  })();

});

// AJAX save + preview polling
async function ajaxSaveAndPoll() {
  const form = document.getElementById('timer_form');
  const saveBtn = document.getElementById('save_btn');
  if (!form || !saveBtn) return;

  saveBtn.addEventListener('click', async () => {
    const isEdit = form.dataset.isEdit === '1';
    
    // Show confirmation dialog
    const confirmResult = await Swal.fire({
      title: 'Generate GIF?',
      text: isEdit
        ? 'This will save your counter changes and generate a new GIF.'
        : 'This will create a new counter and generate its GIF.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Yes, save it',
      cancelButtonText: 'Cancel'
    });
    
    if (!confirmResult.isConfirmed) {
      return;
    }
    
    // Show loading indicator
    const pageRole = isEdit;
    Swal.fire({
      title: isEdit ? 'Updating counter...' : 'Creating counter...',
      text: 'Please wait',
      allowOutsideClick: false,
      allowEscapeKey: false,
      showConfirmButton: false,
      didOpen: () => {
        Swal.showLoading();
      }
    });
    
    saveBtn.disabled = true;
    const formData = new FormData(form);
    // Instruct the backend to generate the GIF as part of save
    formData.append('generate_gif', '1');
    // Ensure _method is present for PUT
    const methodOverride = form.querySelector('input[name="_method"]');
    const isPut = methodOverride && methodOverride.value.toUpperCase() === 'PUT';
    try {
      const res = await fetch(form.action, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: formData
      });
      if (!res.ok) {
        const err = await res.json().catch(() => ({}));
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
      form.dataset.timerId = timerId;

      // Once we have a timer ID, ensure form uses the update route (PUT) instead of create (POST)
      if (form.dataset.isEdit !== '1') {
        form.dataset.isEdit = '1';
        form.action = `/timers/${timerId}`;
        let methodOverrideInput = form.querySelector('input[name="_method"]');
        if (!methodOverrideInput) {
          methodOverrideInput = document.createElement('input');
          methodOverrideInput.type = 'hidden';
          methodOverrideInput.name = '_method';
          form.appendChild(methodOverrideInput);
        }
        methodOverrideInput.value = 'PUT';
      }

      // Now that timer exists, show the Generate GIF button
      saveBtn.classList.remove('d-none');
      
      const previewWrap = document.getElementById('timer_preview');
      const loader = document.getElementById('preview_loader');
      const frameImg = document.getElementById('preview_frame');
      const gifImg = document.getElementById('preview_gif');
      const placeholder = document.getElementById('preview_placeholder');

      // If we have a GIF URL, show it immediately in the preview
      const gifUrl = json.gif_url_with_cache_bust || json.gif_url;
      if (gifUrl && gifImg && loader && frameImg && placeholder) {
        loader.classList.add('d-none');
        placeholder.classList.add('d-none');
        if (form.dataset.isEdit === '1') {
          // Keep showing static preview on edit; refresh PNG frame instead of GIF
          gifImg.classList.add('d-none');
          frameImg.classList.remove('d-none');
          if (typeof generatePreviewFrame === 'function') {
            generatePreviewFrame();
          }
        } else {
          frameImg.classList.add('d-none');
          gifImg.src = gifUrl;
          gifImg.classList.remove('d-none');
        }
      }

      // Update embed section visibility and value based on share availability and status
      const embedSection = document.getElementById('embed_section');
      const embedInput = document.getElementById('embed_input');
      const embedNotice = document.getElementById('embed_notice');
      const statusSelect = document.querySelector('select[name="status"]');
      const isActive = statusSelect ? statusSelect.value === 'active' : false;
      // form is already declared at the top of ajaxSaveAndPoll function (line 1496)
      
      // Store GIF URL in form data attribute for later use
      if (form && json.gif_url) {
        form.dataset.gifUrl = json.gif_url;
        form.dataset.hasGif = '1';
      }
      
      if (embedSection && embedInput) {
        // Show embed section only if timer is active AND has a GIF URL
        if (json.share_available && gifUrl && isActive) {
          const match = (gifUrl || '').match(/\/timers\/(\d+)\/([^\/]+)\.gif/);
          if (match) {
            const userId = match[1];
            const filename = match[2];
            const gettimerUrl = `${window.location.origin}/gettimer/${userId}/${filename}.gif`;
            embedInput.value = `<img src="${gettimerUrl}" alt="Countdown Counter">`;
          } else {
            embedInput.value = `<img src="${gifUrl}" alt="Countdown Counter">`;
          }
          embedSection.classList.remove('d-none');
          if (embedNotice) embedNotice.classList.add('d-none');
        } else {
          embedSection.classList.add('d-none');
          // Show notice if timer is inactive or has no GIF
          if (embedNotice) {
            if (!isActive) {
              embedNotice.classList.remove('d-none');
            } else if (!gifUrl) {
              embedNotice.classList.remove('d-none');
            } else {
              embedNotice.classList.add('d-none');
            }
          }
        }
      }

      // Mark GIF as up to date
      gifDirty = false;

      // For create mode: redirect after save
      if (!pageRole) {
        Swal.fire({
          icon: 'success',
          title: 'Timer Created',
          text: 'Your counter and GIF have been created.',
          timer: 1500,
          showConfirmButton: false
        }).then(() => {
          window.location.href = `/timers/${form.dataset.timerId}/preview`;
        });
        return;
      }
      
      // For edit mode: show success toast, keep user on page with updated GIF
      Swal.fire({
        icon: 'success',
        title: 'GIF Generated',
        text: 'Your counter has been saved and the GIF has been updated.',
        timer: 1500,
        showConfirmButton: false
      });
      saveBtn.disabled = false;
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

function startPreviewPolling(timerId) {
  const previewWrap = document.getElementById('timer_preview');
  const loader = document.getElementById('preview_loader');
  const frameImg = document.getElementById('preview_frame');
  const gifImg = document.getElementById('preview_gif');
  const placeholder = document.getElementById('preview_placeholder');
  
  // Hide frame, show loader
  if (frameImg) frameImg.classList.add('d-none');
  if (gifImg) gifImg.classList.add('d-none');
  if (placeholder) placeholder.classList.add('d-none');
  // if (loader) loader.classList.remove('d-none');
  
  // Update Swal loading message if Swal is already open
  if (Swal.isVisible()) {
    Swal.update({
      title: 'Generating GIF...',
      text: 'Please wait while your counter GIF is being created'
    });
  }
  
  let attempts = 0;
  const maxAttempts = 60; // up to ~2 minutes if interval is 2s
  const interval = setInterval(async () => {
    attempts++;
    try {
      const res = await fetch(`/timers/${encodeURIComponent(timerId)}/status`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const json = await res.json();
      const form = document.getElementById('timer_form');
      if (form && json.status) {
        form.dataset.timerStatus = json.status;
      }
      const isActive = json.status === 'active';
      const embedSection = document.getElementById('embed_section');
      const embedInput = document.getElementById('embed_input');
      const embedNotice = document.getElementById('embed_notice');
      if (embedSection) embedSection.classList.toggle('d-none', !isActive);
      if (embedNotice) embedNotice.classList.toggle('d-none', isActive);
      if (json.gif_url) {
        clearInterval(interval);
        
        // Close the loading Swal and show success (only if Swal was open)
        if (Swal.isVisible()) {
          Swal.fire({
            icon: 'success',
            title: 'Timer Updated!',
            text: 'Your counter GIF has been generated.',
            timer: 2000,
            showConfirmButton: false
          });
        }
        
        // Re-enable save button
        const saveBtn = document.getElementById('save_btn');
        if (saveBtn) saveBtn.disabled = false;
        
        // Hide loader and frame, show GIF
        if (loader) loader.classList.add('d-none');
        if (frameImg) frameImg.classList.add('d-none');
        if (placeholder) placeholder.classList.add('d-none');
        if (gifImg) {
          const previewSrc = json.gif_url_with_cache_bust || json.gif_url;
          gifImg.src = previewSrc;
          gifImg.classList.remove('d-none');
        }
        
        // Update form hasGif attribute
        if (form && json.gif_url) {
          form.dataset.hasGif = '1';
        }
        
        // update embed section
        const statusSelect = document.querySelector('select[name="status"]');
        const isActive = statusSelect ? statusSelect.value === 'active' : false;
        
        if (embedInput) {
          if (!json.share_available || !isActive) {
            embedInput.value = '';
          }
        }
        if (embedSection && embedInput && embedNotice) {
          // Extract user_id and filename from gif_url to build gettimer URL
          const gifUrl = json.gif_url || '';
          if (json.share_available && isActive && gifUrl) {
            const match = gifUrl.match(/\/timers\/(\d+)\/([^\/]+)\.gif/);
            if (match) {
              const userId = match[1];
              const filename = match[2];
              const gettimerUrl = `${window.location.origin}/gettimer/${userId}/${filename}.gif`;
              embedInput.value = `<img src="${gettimerUrl}" alt="Countdown Counter">`;
            } else {
              const absUrl = new URL(gifUrl, window.location.origin).href;
              embedInput.value = `<img src="${absUrl}" alt="Countdown Counter">`;
            }
            embedSection.classList.remove('d-none');
            embedNotice.classList.add('d-none');
          } else {
            embedSection.classList.add('d-none');
            if (!isActive) {
              embedNotice.textContent = 'Activate this timer to generate a shareable embed code.';
              embedNotice.classList.remove('d-none');
            } else if (!gifUrl) {
              embedNotice.textContent = 'Generate a GIF to get the embed code.';
              embedNotice.classList.remove('d-none');
            } else {
              embedNotice.classList.add('d-none');
            }
          }
        }
        // GIF is now up to date with latest changes
        gifDirty = false;
      } else if (attempts >= maxAttempts) {
        clearInterval(interval);
        if (Swal.isVisible()) {
          Swal.fire({
            icon: 'warning',
            title: 'Timeout',
            text: 'GIF generation is taking longer than expected. Please refresh the page.'
          });
        }
        const saveBtn = document.getElementById('save_btn');
        if (saveBtn) saveBtn.disabled = false;
      }
    } catch (e) {
      // ignore transient errors
    }
  }, 2000);
}

ajaxSaveAndPoll();

// Auto-poll on edit page load when GIF not yet available
(function autoPollOnLoad(){
  const form = document.getElementById('timer_form');
  if (!form) return;
  if (form.dataset.isEdit === '1' && form.dataset.hasGif !== '1' && form.dataset.timerId) {
    startPreviewPolling(form.dataset.timerId);
  }
})();

// Bind copy button to use global toast
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

  // Update embed section visibility when status changes
  const statusSelect = document.querySelector('select[name="status"]');
  if (statusSelect) {
    const updateEmbedVisibility = () => {
      const embedSection = document.getElementById('embed_section');
      const embedNotice = document.getElementById('embed_notice');
      const embedInput = document.getElementById('embed_input');
      const form = document.getElementById('timer_form');
      // Check if timer has GIF by checking if embed input has a value or if form has hasGif attribute
      const hasGif = (form && form.dataset.hasGif === '1') || (embedInput && embedInput.value && embedInput.value.trim() !== '');
      const isActive = statusSelect.value === 'active';
      
      if (embedSection && embedNotice) {
        // Show embed section only if timer is active AND has a GIF
        if (isActive && hasGif) {
          embedSection.classList.remove('d-none');
          embedNotice.classList.add('d-none');
          
          // If embed input is empty but we have a GIF, populate it
          if (embedInput && (!embedInput.value || embedInput.value.trim() === '')) {
            // Try to get GIF URL from form data attribute first
            let gifUrl = form ? form.dataset.gifUrl : '';
            
            // If not in form data, try preview image
            if (!gifUrl) {
              const previewGif = document.getElementById('preview_gif');
              if (previewGif && previewGif.src && !previewGif.classList.contains('d-none')) {
                gifUrl = previewGif.src;
              }
            }
            
            // If still no GIF URL, fetch from server if we have timer ID
            if (!gifUrl && form && form.dataset.timerId) {
              const timerId = form.dataset.timerId;
              fetch(`/timers/${timerId}/status`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
              })
              .then(res => res.json())
              .then(data => {
                if (data.gif_url && embedInput) {
                  // Update form data attribute
                  if (form) {
                    form.dataset.gifUrl = data.gif_url;
                    form.dataset.hasGif = '1';
                  }
                  // Build embed code
                  const match = data.gif_url.match(/\/timers\/(\d+)\/([^\/]+)\.gif/);
                  if (match) {
                    const userId = match[1];
                    const filename = match[2];
                    const gettimerUrl = `${window.location.origin}/gettimer/${userId}/${filename}.gif`;
                    embedInput.value = `<img src="${gettimerUrl}" alt="Countdown Counter">`;
                  } else {
                    const absUrl = new URL(data.gif_url, window.location.origin).href;
                    embedInput.value = `<img src="${absUrl}" alt="Countdown Counter">`;
                  }
                }
              })
              .catch(err => console.error('Failed to fetch timer status:', err));
            } else if (gifUrl) {
              // Build embed code from GIF URL
              const match = gifUrl.match(/\/timers\/(\d+)\/([^\/]+)\.gif/);
              if (match) {
                const userId = match[1];
                const filename = match[2];
                const gettimerUrl = `${window.location.origin}/gettimer/${userId}/${filename}.gif`;
                embedInput.value = `<img src="${gettimerUrl}" alt="Countdown Counter">`;
              } else {
                // Try to extract from full URL
                try {
                  const urlObj = new URL(gifUrl);
                  const pathParts = urlObj.pathname.split('/');
                  if (pathParts.length >= 4 && pathParts[1] === 'timers') {
                    const userId = pathParts[2];
                    const filename = pathParts[3].replace('.gif', '');
                    const gettimerUrl = `${window.location.origin}/gettimer/${userId}/${filename}.gif`;
                    embedInput.value = `<img src="${gettimerUrl}" alt="Countdown Counter">`;
                  } else {
                    embedInput.value = `<img src="${gifUrl}" alt="Countdown Counter">`;
                  }
                } catch (e) {
                  embedInput.value = `<img src="${gifUrl}" alt="Countdown Counter">`;
                }
              }
            }
          }
        } else {
          embedSection.classList.add('d-none');
          // Show notice if inactive or no GIF
          if (!isActive) {
            embedNotice.textContent = 'Activate this timer to generate a shareable embed code.';
            embedNotice.classList.remove('d-none');
          } else if (!hasGif) {
            embedNotice.textContent = 'Generate a GIF to get the embed code.';
            embedNotice.classList.remove('d-none');
          } else {
            embedNotice.classList.add('d-none');
          }
        }
      }
    };
    
    statusSelect.addEventListener('change', updateEmbedVisibility);
    // Also update on initial load
    updateEmbedVisibility();
  }

  var btn = document.getElementById('embed_copy_btn');
  if (!btn) return;
  btn.addEventListener('click', function(){
    var inp = document.getElementById('embed_input');
    if (!inp || !inp.value) return;
    window.copyToClipboard(inp.value).then(function(){
      if (window.showToast) window.showToast('Embed code copied', 'success');
    }).catch(function(){
      if (window.showToast) window.showToast('Copy failed', 'danger');
    });
  });
});