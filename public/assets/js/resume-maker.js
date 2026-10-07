(() => {
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];
  const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };

  /* ---------------------------------------------------------------
   * Inline validation: messages appear while typing (after a short
   * pause) so the expected format is known before submitting.
   * Each rule returns [state, message, strict]; state = error | warn | ok | ''.
   * A strict warning is a hint while typing but an error once the user
   * leaves the field or submits.
   * Keep in sync with Resume::validateResume().
   * ------------------------------------------------------------- */
  const PHONE = /^(\+?63|0)9\d{2}[\s-]?\d{3}[\s-]?\d{4}$|^\(?0\d{1,2}\)?[\s-]?\d{3,4}[\s-]?\d{4}$/;
  const rules = {
    name(v) {
      if (/\d/.test(v)) return ['error', 'Names cannot contain numbers.'];
      if (!/^[\p{L}\p{M}][\p{L}\p{M} .,'-]*$/u.test(v)) return ['error', 'Use letters only, e.g. Juan Dela Cruz.'];
      if (v.length < 2) return ['warn', 'Keep typing your full name…', true];
      if (!/\s/.test(v)) return ['warn', 'Include your first and last name, e.g. Juan Dela Cruz.'];
      return ['ok', ''];
    },
    email(v) {
      if (/\s/.test(v)) return ['error', 'Email cannot contain spaces.'];
      if (!v.includes('@')) return ['warn', 'Format: name@example.com', true];
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) return ['warn', 'Almost there — format: name@example.com', true];
      if (/@(gmial|gmal|gamil|gnail)\./i.test(v)) return ['warn', 'Did you mean @gmail.com?'];
      return ['ok', ''];
    },
    phone(v) {
      if (/[^\d\s()+-]/.test(v)) return ['error', 'Use numbers only, e.g. 0926-000-0000.'];
      const digits = v.replace(/\D/g, '');
      if (/^(09|639)/.test(digits) && digits.length < (digits.startsWith('63') ? 12 : 11)) return ['warn', `Format: 0926-000-0000 (${digits.length}/${digits.startsWith('63') ? 12 : 11} digits)`, true];
      if (!PHONE.test(v.trim())) return ['error', 'Use a PH number, e.g. 0926-000-0000 or +63 926 000 0000.'];
      return ['ok', ''];
    },
    dob(v) {
      const d = new Date(v + 'T00:00:00');
      if (!/^\d{4}-\d{2}-\d{2}$/.test(v) || isNaN(d)) return ['error', 'Enter a valid date of birth.'];
      const today = new Date(); today.setHours(0, 0, 0, 0);
      if (d > today) return ['error', 'Date of birth cannot be in the future.'];
      if (d.getFullYear() < 1900) return ['error', 'Please check the year of your date of birth.'];
      let age = today.getFullYear() - d.getFullYear();
      if (today < new Date(today.getFullYear(), d.getMonth(), d.getDate())) age--;
      if (age < 10) return ['warn', `Age ${age} — please double-check the year.`];
      return ['ok', `Age: ${age}`];
    },
    height(v) {
      if (/^\d{1,2}\s*('|ft)\s*\d{0,2}\s*("|in)?$/i.test(v) || /^\d{2,3}(\.\d)?\s*cm$/i.test(v) || /^\d(\.\d{1,2})?\s*m$/i.test(v)) return ['ok', ''];
      if (/^\d{2,3}$/.test(v)) return ['warn', 'Add a unit, e.g. 155 cm'];
      return ['warn', 'Format: 5\'1" or 155 cm'];
    },
    weight(v) {
      if (/^\d{2,3}(\.\d)?\s*(kg|kgs|kls|kilos?|lbs?)$/i.test(v)) return ['ok', ''];
      if (/^\d{2,3}(\.\d)?$/.test(v)) return ['warn', 'Add a unit, e.g. 42 kg'];
      return ['warn', 'Format: 42 kg or 95 lbs'];
    },
    years(v) {
      const m = v.match(/^((?:19|20)\d{2})\s*[-–]\s*((?:19|20)\d{2}|present)$/i);
      if (!m && /^(19|20)\d{2}$/.test(v)) return ['warn', 'Add the end year, e.g. 2021 - 2025 or 2025 - Present'];
      if (!m) return ['warn', 'Format: 2021 - 2025 or 2025 - Present'];
      if (/^\d/.test(m[2]) && +m[2] < +m[1]) return ['error', 'End year must be after the start year.'];
      return ['ok', ''];
    },
    objective(v) {
      return v.length > 800 ? ['error', 'Objective is too long (max 800 characters).'] : ['', ''];
    },
  };

  function fieldBox(input) { return input.closest('.field, .repeat-field') || input.parentElement; }

  function setState(input, state, msg) {
    const box = fieldBox(input);
    const out = $('.field-msg', box);
    box.classList.remove('is-error', 'is-warn', 'is-ok');
    if (state) box.classList.add('is-' + state);
    input.setAttribute('aria-invalid', state === 'error' ? 'true' : 'false');
    if (out) out.textContent = msg || out.dataset.hint || '';
  }

  /** Validates one input; `force` also reports required fields that are empty. */
  function check(input, force = false) {
    const v = input.value.trim();
    let state = '', msg = '', strict = false;
    if (!v) {
      if (input.required && (force || input.dataset.touched)) { state = 'error'; msg = (fieldLabel(input) || 'This field') + ' is required.'; }
    } else if (rules[input.dataset.rule]) {
      [state, msg, strict] = rules[input.dataset.rule](v);
      if (strict && state === 'warn' && force) state = 'error';
    }
    if (input.dataset.counter) {
      const left = +input.dataset.counter - input.value.length;
      if (!msg) msg = `${input.value.length} / ${input.dataset.counter}` + (left < 80 ? ` — ${left} characters left` : '');
    }
    setState(input, state, msg);
    return state !== 'error';
  }

  function fieldLabel(input) {
    if (input.dataset.label) return input.dataset.label;
    const l = input.id && $(`label[for="${input.id}"]`);
    return l ? l.textContent.replace('*', '').trim() : input.placeholder;
  }

  function validateAll(form) {
    let first = null;
    $$('input[data-rule], input[required], select[required], textarea[data-rule]', form).forEach(i => {
      i.dataset.touched = '1';
      if (!check(i, true) && !first) first = i;
    });
    return first;
  }

  function bindValidation(form) {
    const later = debounce(check, 350);
    form.addEventListener('input', e => {
      const i = e.target;
      if (!i.matches('[data-rule], [required]')) return;
      if (i.dataset.counter) return check(i);
      // Clear stale errors at once, report the format after a short pause.
      if (fieldBox(i).classList.contains('is-error')) setState(i, '', '');
      later(i);
    });
    form.addEventListener('focusout', e => {
      const i = e.target;
      if (!i.matches('[data-rule], [required]')) return;
      i.dataset.touched = '1';
      if (i.dataset.rule === 'phone') i.value = formatPhone(i.value);
      check(i, true);
    });
    $$('[data-rule], [required]', form).forEach(i => { if (i.value.trim() || i.dataset.counter) check(i); });
  }

  /** 09260000000 → 0926-000-0000, +639260000000 → +63 926 000 0000 */
  function formatPhone(v) {
    const d = v.replace(/\D/g, '');
    if (/^09\d{9}$/.test(d)) return `${d.slice(0, 4)}-${d.slice(4, 7)}-${d.slice(7)}`;
    if (/^639\d{9}$/.test(d)) return `+63 ${d.slice(2, 5)} ${d.slice(5, 8)} ${d.slice(8)}`;
    return v.trim();
  }

  $$('form.js-validate').forEach(form => {
    bindValidation(form);
    if (form.id === 'resumeForm') return;
    form.addEventListener('submit', e => {
      const bad = validateAll(form);
      if (bad) { e.preventDefault(); bad.focus(); }
    });
  });

  /* ---------------------------------------------------------------
   * Resume builder
   * ------------------------------------------------------------- */
  const f = $('#resumeForm');
  if (!f) return;
  const d = window.RESUME_DATA || {};
  const URLS = window.RESUME_URLS || {};
  const MAX = 5;

  function toast(msg, type = 'info', ms = 4000) {
    const t = document.createElement('div');
    t.className = 'toast toast-' + type;
    t.textContent = msg;
    $('#toasts').appendChild(t);
    requestAnimationFrame(() => t.classList.add('show'));
    setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 300); }, ms);
  }

  /* Repeaters ---------------------------------------------------- */
  const msg = '<small class="field-msg" aria-live="polite"></small>';
  const sub = (name, value, placeholder, attrs = '', label = '') =>
    `<div class="repeat-field"><input data-name="${name}" value="${esc(value)}" placeholder="${placeholder}" ${label ? `required data-label="${label}"` : ''} ${attrs}>${msg}</div>`;
  const repeaters = {
    skill: { wrap: $('#skillsWrap'), base: 'skills', tpl: x => sub('', x, 'e.g. Can sing, dance, cook; good in reading and writing', 'maxlength="200"') },
    achievement: { wrap: $('#achievementsWrap'), base: 'achievements', tpl: x => sub('', x, 'e.g. Junior High School – Consistent Honor Student', 'maxlength="200"') },
    education: {
      wrap: $('#educationWrap'), base: 'education', card: true, min: 1,
      tpl: (x = {}) => sub('level', x.level, 'Level / Course, e.g. Senior High School', 'maxlength="160"', 'Level / Course') + sub('school', x.school, 'School', '', 'School') +
        sub('address', x.address, 'School address', '', 'School address') + sub('years', x.years, 'e.g. 2025 - Present', 'data-rule="years"', 'School year'),
    },
    reference: {
      wrap: $('#referencesWrap'), base: 'references', card: true, min: 1,
      tpl: (x = {}) => sub('name', x.name, 'Name, e.g. Mr. Juan Santos', '', 'Name') + sub('contact', x.contact, 'Contact, e.g. 0992-000-0000', 'type="tel" inputmode="tel" data-rule="phone"', 'Contact number') +
        sub('position', x.position, 'Position', '', 'Position') + sub('organization', x.organization, 'School / Company / Organization', '', 'School / Company / Organization'),
    },
  };

  /** Renumbers names so removed rows never leave gaps or duplicate indexes. */
  function renumber(r) {
    [...r.wrap.children].forEach((row, i) => $$('input', row).forEach(inp => {
      inp.name = inp.dataset.name ? `${r.base}[${i}][${inp.dataset.name}]` : `${r.base}[]`;
    }));
    if (r.min) [...r.wrap.children].forEach(row => { $('.remove', row).hidden = r.wrap.children.length <= r.min; });
    const btn = $(`[data-add="${Object.keys(repeaters).find(k => repeaters[k] === r)}"]`);
    btn.disabled = r.wrap.children.length >= MAX;
    btn.textContent = btn.textContent.replace(/\s*\(\d\/\d\)$/, '') + ` (${r.wrap.children.length}/${MAX})`;
  }

  function addRow(type, x, focus = false) {
    const r = repeaters[type];
    if (r.wrap.children.length >= MAX) return toast(`You can add up to ${MAX} items only.`, 'warn');
    r.wrap.insertAdjacentHTML('beforeend', `<div class="repeat${r.card ? ' cardrow' : ''}">${r.tpl(x)}<button type="button" class="remove" aria-label="Remove">×</button></div>`);
    renumber(r);
    const row = r.wrap.lastElementChild;
    $$('input[data-rule]', row).forEach(i => { if (i.value.trim()) check(i); });
    if (focus) $('input', row).focus();
  }

  const list = (v, fallback) => (Array.isArray(v) && v.length ? v : fallback);
  list(d.skills, ['']).forEach(x => addRow('skill', x));
  list(d.education, [{}]).forEach(x => addRow('education', x));
  list(d.achievements, ['']).forEach(x => addRow('achievement', x));
  list(d.references, [{}]).forEach(x => addRow('reference', x));

  document.addEventListener('click', e => {
    const add = e.target.closest('[data-add]');
    if (add) return addRow(add.dataset.add, undefined, true);
    const rm = e.target.closest('.remove');
    if (rm) {
      const wrap = rm.closest('.repeat').parentElement;
      rm.closest('.repeat').remove();
      renumber(Object.values(repeaters).find(r => r.wrap === wrap));
      schedulePreview();
    }
  });

  /* Languages ---------------------------------------------------- */
  const lang = $('#languages'), langEntry = $('#languageEntry'), langList = $('#languageTagList');
  let languageValues = (lang.value || '').split(',').map(v => v.trim()).filter(Boolean);
  function renderLanguages() {
    langList.innerHTML = languageValues.map((v, i) => `<span class="tag">${esc(v)}<button type="button" class="tag-remove" data-lang-index="${i}" aria-label="Remove ${esc(v)}">×</button></span>`).join('');
    lang.value = languageValues.join(', ');
    lang.dispatchEvent(new Event('input', { bubbles: true }));
  }
  function pushLanguage(v) { if (v && !languageValues.some(x => x.toLowerCase() === v.toLowerCase())) languageValues.push(v); }
  function addLanguage() { pushLanguage(langEntry.value.trim().replace(/,+$/, '').trim()); langEntry.value = ''; renderLanguages(); }
  langEntry.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); addLanguage(); }
    else if (e.key === 'Backspace' && !langEntry.value && languageValues.length) { languageValues.pop(); renderLanguages(); }
  });
  langEntry.addEventListener('blur', () => { addLanguage(); lang.dataset.touched = '1'; check(lang, true); });
  langEntry.addEventListener('paste', () => setTimeout(() => {
    if (!langEntry.value.includes(',')) return;
    langEntry.value.split(',').map(x => x.trim()).filter(Boolean).forEach(pushLanguage);
    langEntry.value = ''; renderLanguages();
  }, 0));
  langList.addEventListener('click', e => {
    const b = e.target.closest('[data-lang-index]');
    if (!b) return;
    languageValues.splice(Number(b.dataset.langIndex), 1); renderLanguages();
  });
  $('.field-msg', fieldBox(lang)).dataset.hint = 'Press Enter or comma to add each language.';
  renderLanguages();

  /* Photo: crop to 2x2 in the browser, preview at once, upload with progress */
  const drop = $('#photoDrop'), pInput = $('#photoInput'), pPreview = $('#photoPreview'), pMsg = $('#photoMsg');
  const pProgress = $('#photoProgress'), pText = $('#photoProgressText'), pToken = $('#photoToken'), pRemove = $('#photoRemove');
  const PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp'], PHOTO_MAX = 8 * 1024 * 1024, SIDE = 600;
  let uploading = null, lastObjectUrl = null;

  function photoState(state, text) {
    const box = $('#photoField');
    box.classList.remove('is-error', 'is-ok', 'is-warn');
    if (state) box.classList.add('is-' + state);
    pMsg.textContent = text || '';
  }

  /** Center-crops (slightly above center for portraits) to a 600×600 JPEG. */
  async function cropSquare(file) {
    const bmp = await (window.createImageBitmap ? createImageBitmap(file, { imageOrientation: 'from-image' }) : new Promise((res, rej) => {
      const img = new Image(); img.onload = () => res(img); img.onerror = rej; img.src = URL.createObjectURL(file);
    }));
    const w = bmp.width, h = bmp.height, side = Math.min(w, h);
    if (side < 150) photoState('warn', `Photo is small (${w}×${h}px) and may look blurry when printed.`);
    const c = document.createElement('canvas');
    c.width = c.height = SIDE;
    const ctx = c.getContext('2d');
    ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, SIDE, SIDE);
    ctx.drawImage(bmp, (w - side) / 2, (h - side) * (h > w ? 0.3 : 0.5), side, side, 0, 0, SIDE, SIDE);
    return new Promise(res => c.toBlob(res, 'image/jpeg', 0.9));
  }

  function upload(blob, name) {
    return new Promise((resolve, reject) => {
      const fd = new FormData();
      fd.append('photo', blob, name);
      const xhr = new XMLHttpRequest();
      xhr.open('POST', URLS.photo);
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.upload.onprogress = e => { if (e.lengthComputable) { const p = Math.round(e.loaded / e.total * 100); pText.textContent = p + '%'; drop.style.setProperty('--p', p); } };
      xhr.onload = () => {
        let j = {};
        try { j = JSON.parse(xhr.responseText); } catch (_) { /* handled below */ }
        if (xhr.status === 200 && j.ok) resolve(j.photo); else reject(new Error((j.errors && j.errors.photo) || 'Upload failed. Please try again.'));
      };
      xhr.onerror = () => reject(new Error('Upload failed — check your internet connection.'));
      xhr.send(fd);
    });
  }

  async function handlePhoto(file) {
    if (!file) return;
    if (!PHOTO_TYPES.includes(file.type)) return photoState('error', 'Photo must be a JPG, PNG, or WEBP image.');
    if (file.size > PHOTO_MAX) return photoState('error', `Photo is too large (${(file.size / 1048576).toFixed(1)} MB). Maximum is 8 MB.`);
    photoState('', 'Preparing photo…');
    let blob;
    try { blob = await cropSquare(file); } catch (_) { blob = file; }
    if (lastObjectUrl) URL.revokeObjectURL(lastObjectUrl);
    pPreview.src = lastObjectUrl = URL.createObjectURL(blob);
    drop.classList.add('has-photo', 'is-uploading');
    pProgress.hidden = false; pText.textContent = '0%'; drop.style.setProperty('--p', 0);
    photoState('', 'Uploading…');
    uploading = upload(blob, 'photo.jpg').then(token => {
      pToken.value = token; pRemove.value = '';
      photoState('ok', '✓ Photo uploaded and resized to 2×2.');
      $('#photoClear').hidden = false; $('#photoPick').textContent = 'Change photo';
      schedulePreview(0);
    }).catch(err => {
      photoState('error', err.message);
      pToken.value = '';
    }).finally(() => {
      drop.classList.remove('is-uploading'); pProgress.hidden = true; uploading = null; pInput.value = '';
    });
  }

  $('#photoPick').addEventListener('click', () => pInput.click());
  drop.addEventListener('click', () => pInput.click());
  drop.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); pInput.click(); } });
  pInput.addEventListener('change', () => handlePhoto(pInput.files[0]));
  ['dragenter', 'dragover'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('is-dragging'); }));
  ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('is-dragging'); }));
  drop.addEventListener('drop', e => handlePhoto(e.dataTransfer.files[0]));
  $('#photoClear').addEventListener('click', () => {
    pPreview.removeAttribute('src'); drop.classList.remove('has-photo');
    pToken.value = ''; pRemove.value = '1';
    $('#photoClear').hidden = true; $('#photoPick').textContent = 'Choose photo';
    photoState('', 'Photo removed.');
    schedulePreview(0);
  });

  /* Live preview ------------------------------------------------- */
  const frame = $('#previewFrame'), status = $('#previewStatus');
  let previewReq = 0;
  async function refreshPreview() {
    const id = ++previewReq;
    status.textContent = 'Updating…';
    try {
      const res = await fetch(URLS.render, { method: 'POST', body: new FormData(f), headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!res.ok) throw new Error();
      const html = await res.text();
      if (id !== previewReq) return;
      const y = frame.contentWindow ? frame.contentWindow.scrollY : 0;
      frame.srcdoc = html;
      frame.onload = () => { try { frame.contentWindow.scrollTo(0, y); } catch (_) { /* ignore */ } };
      status.textContent = 'Up to date.';
    } catch (_) {
      if (id === previewReq) status.textContent = 'Preview unavailable — check your connection.';
    }
  }
  const debouncedPreview = debounce(refreshPreview, 600);
  function schedulePreview(ms) { if (ms === 0) refreshPreview(); else debouncedPreview(); }
  f.addEventListener('input', () => schedulePreview());
  f.addEventListener('change', () => schedulePreview());
  refreshPreview();

  /* Save / download --------------------------------------------- */
  function showServerErrors(errors) {
    const left = [];
    Object.entries(errors).forEach(([name, text]) => {
      const input = name === 'photo' ? null : f.querySelector(`[name="${CSS.escape(name)}"]`);
      if (name === 'photo') photoState('error', text);
      else if (input) setState(input, 'error', text);
      else left.push(text);
    });
    $('#errors').innerHTML = left.length ? '<div class="alert alert-error">' + left.map(esc).join('<br>') + '</div>' : '';
  }

  async function save(download) {
    const buttons = $$('#saveBtn, #downloadBtn');
    $('#errors').innerHTML = '';
    let bad = validateAll(f);
    if (!pPreview.getAttribute('src')) {
      photoState('error', 'A 2×2 photo is required.');
      bad = bad || drop;
    } else if ($('#photoField').classList.contains('is-error') && !uploading) {
      bad = bad || drop;
    }
    if (bad) {
      const n = $$('.is-error', f).length;
      toast(`Please fix ${n} highlighted field${n > 1 ? 's' : ''} first.`, 'error');
      bad.scrollIntoView({ behavior: 'smooth', block: 'center' });
      bad.focus({ preventScroll: true });
      return;
    }
    buttons.forEach(b => { b.disabled = true; });
    const main = download ? $('#downloadBtn') : $('#saveBtn');
    const label = main.textContent;
    main.classList.add('is-loading');
    try {
      if (uploading) { main.textContent = 'Waiting for photo…'; await uploading; }
      main.textContent = 'Saving…';
      const res = await fetch(URLS.save, { method: 'POST', body: new FormData(f), headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      let j = {};
      try { j = await res.json(); } catch (_) { /* handled below */ }
      if (!res.ok || !j.ok) {
        showServerErrors(j.errors || { form: 'Unable to save. Please try again.' });
        toast('Some details need fixing. See the highlighted fields.', 'error');
        const first = $('.is-error input, .is-error textarea', f);
        if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
      }
      f.uuid.value = j.uuid;
      if (download) {
        toast('Saved! Your DOCX download is starting…', 'success');
        location.href = j.download;
      } else {
        toast('Saved! Retrieve it anytime with your name, birthday, and email.', 'success');
      }
    } catch (_) {
      toast('Unable to connect to the server. Please try again.', 'error');
    } finally {
      buttons.forEach(b => { b.disabled = false; });
      main.classList.remove('is-loading');
      main.textContent = label;
    }
  }
  f.addEventListener('submit', e => { e.preventDefault(); save(true); });
  $('#saveBtn').addEventListener('click', () => save(false));
})();
