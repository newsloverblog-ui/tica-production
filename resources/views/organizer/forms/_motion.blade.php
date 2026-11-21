<script>
  // Registration form pages: calm motion, and on the editor every field change
  // saves in the background and redraws the list right away.
  (function () {
    var page = document.querySelector('.rf-page');
    if (!page) return;
    var key = 'rf-seen:' + location.pathname, scrollKey = 'rf-scroll:' + location.pathname;
    var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    try {
      // Cards ease in on the first visit only.
      if (!sessionStorage.getItem(key)) { page.classList.add('rf-intro'); sessionStorage.setItem(key, '1'); }
      // After a full-page save, return to where the organiser was.
      var saved = JSON.parse(sessionStorage.getItem(scrollKey) || 'null');
      sessionStorage.removeItem(scrollKey);
      if (saved && Date.now() - saved.t < 15000 && !location.hash) window.scrollTo(0, saved.y);
    } catch (e) {}

    var token = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
    var toastEl = document.querySelector('[data-rf-toast]'), toastTimer;
    function toast(msg, bad) {
      if (!toastEl || !msg) return;
      toastEl.textContent = msg; toastEl.classList.toggle('is-bad', !!bad); toastEl.classList.add('is-on');
      clearTimeout(toastTimer); toastTimer = setTimeout(function () { toastEl.classList.remove('is-on'); }, 2600);
    }
    function errorText(data) {
      if (data && data.errors) { for (var k in data.errors) { return [].concat(data.errors[k])[0]; } }
      return (data && data.message) || 'Something went wrong. Please try again.';
    }
    function post(url, body) {
      return Promise.resolve().then(function () { return fetch(url, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token } })
        .then(function (r) {
          return r.json().catch(function () { return {}; }).then(function (d) {
            if (r.status === 419) d = { message: 'Your session timed out. Refresh the page and try again.' };
            if (!r.ok) throw d; return d;
          });
        }, function () { throw { message: 'Could not reach TICA. Check your connection and try again.' }; });
      });
    }
    function busy(btn, on) { if (!btn) return; btn.disabled = on; if (on) btn.setAttribute('aria-busy', 'true'); else btn.removeAttribute('aria-busy'); }

    // Two-click confirm for destructive buttons that still post normally.
    page.addEventListener('submit', function (e) {
      var form = e.target, ask = form.getAttribute('data-rf-confirm');
      if (ask && !form.dataset.sure) {
        e.preventDefault();
        var b = form.querySelector('[type=submit]'); form.dataset.sure = '1'; b.dataset.label = b.textContent; b.textContent = ask; b.classList.add('is-sure');
        setTimeout(function () { if (b.isConnected) { delete form.dataset.sure; b.textContent = b.dataset.label; b.classList.remove('is-sure'); } }, 4000);
      }
    }, true);

    var list = document.querySelector('[data-rf-list]');

    // ---------- Field editor (library forms only) ----------
    if (list) {
      var dlg = document.getElementById('rf-edit'), edit = dlg.querySelector('[data-rf-edit-form]'), current = null;
      var choice = ['select', 'radio', 'multi_select'], textish = ['text', 'textarea', 'email', 'phone', 'company', 'job_title'];
      var summary = document.querySelector('[data-rf-summary]'), count = document.querySelector('[data-rf-count]');

      function rows() { return [].slice.call(list.querySelectorAll('[data-rf-item]')); }
      function q(row) { try { return JSON.parse(row.getAttribute('data-q')); } catch (e) { return null; } }
      function render(data) {
        rows().forEach(function (r) { r.remove(); });
        var empty = list.querySelector('[data-rf-empty]'); if (empty) empty.remove();
        list.insertAdjacentHTML('beforeend', data.html);
        if (count) count.textContent = data.count;
        if (summary) summary.textContent = data.count + (data.count === 1 ? ' field' : ' fields');
        var row = data.focus && list.querySelector('[data-id="' + data.focus + '"]');
        if (row) { row.classList.add('is-new'); setTimeout(function () { row.classList.remove('is-new'); }, 1300); }
        return row;
      }
      function sync() {
        var t = edit.type.value, cond = edit.condition_question_key.value;
        edit.querySelectorAll('[data-rf-show]').forEach(function (el) {
          var k = el.getAttribute('data-rf-show');
          el.hidden = k === 'choice' ? choice.indexOf(t) === -1 : k === 'text' ? textish.indexOf(t) === -1 : !cond;
        });
      }
      function openEditor(row, fresh) {
        var d = q(row); if (!d) return; current = d;
        dlg.querySelector('#rf-edit-h').textContent = fresh ? 'Name your new field' : 'Edit field';
        edit.action = d.update;
        edit.label.value = d.label; edit.type.value = d.type; edit.question_key.value = d.key;
        edit.is_required.checked = d.required; edit.options.value = d.options || ''; edit.help_text.value = d.help || '';
        edit.scope.value = d.scope; edit.placeholder.value = d.placeholder || ''; edit.min_length.value = d.min || ''; edit.max_length.value = d.max || '';
        var opts = '<option value="">Always show</option>';
        rows().forEach(function (r) { var o = q(r); if (o && o.id !== d.id) opts += '<option value="' + o.key.replace(/"/g, '&quot;') + '">' + o.label.replace(/</g, '&lt;') + '</option>'; });
        edit.condition_question_key.innerHTML = opts; edit.condition_question_key.value = d.condKey || '';
        edit.condition_operator.value = d.condOp || 'equals'; edit.condition_value.value = d.condValue || '';
        edit.querySelector('details').open = !!(d.condKey || d.scope === 'attendee');
        dlg.querySelector('[data-rf-msg]').textContent = '';
        sync(); dlg.showModal();
        setTimeout(function () { edit.label.focus(); if (fresh) edit.label.select(); }, 30);
      }
      edit.addEventListener('change', function (e) { if (e.target.name === 'type' || e.target.name === 'condition_question_key') sync(); });
      dlg.addEventListener('click', function (e) { if (e.target === dlg || e.target.closest('[data-rf-close]')) dlg.close(); });
      edit.addEventListener('submit', function (e) {
        e.preventDefault();
        var msg = dlg.querySelector('[data-rf-msg]'), btn = edit.querySelector('[type=submit]');
        if (!edit.label.value.trim()) { msg.textContent = 'Write the question.'; edit.label.focus(); return; }
        if (choice.indexOf(edit.type.value) > -1 && !edit.options.value.trim()) { msg.textContent = 'Add at least one choice, one per line.'; edit.options.focus(); return; }
        var fd = new FormData(edit); if (!edit.is_required.checked) fd.set('is_required', '0');
        busy(btn, true); msg.textContent = '';
        post(edit.action, fd).then(function (d) { dlg.close(); render(d); toast(d.message); }, function (er) { msg.textContent = errorText(er); })
          .then(function () { busy(btn, false); });
      });

      // Click a row or Edit to edit; Remove asks once more, then removes.
      list.addEventListener('click', function (e) {
        var row = e.target.closest('[data-rf-item]'); if (!row) return;
        if (e.target.closest('[data-rf-edit]')) return openEditor(row);
        var rm = e.target.closest('[data-rf-remove]');
        if (rm) {
          if (!rm.dataset.sure) { rm.dataset.sure = '1'; rm.textContent = 'Remove?'; setTimeout(function () { if (rm.isConnected) { delete rm.dataset.sure; rm.textContent = 'Remove'; } }, 4000); return; }
          busy(rm, true); row.classList.add('is-leaving');
          post(q(row).delete, new FormData()).then(function (d) { render(d); toast(d.message); }, function (er) { row.classList.remove('is-leaving'); busy(rm, false); toast(errorText(er), true); });
        }
      });

      // Add a field: click a chip, or drag it into the list.
      function addField(form, position) {
        var btn = form.querySelector('[data-rf-chip]'), fd = new FormData(form);
        if (position) fd.set('position', position);
        btn.classList.add('is-adding');
        post(form.action, fd).then(function (d) { var row = render(d); toast(d.message); if (row) { row.scrollIntoView({ block: 'nearest', behavior: reduce ? 'auto' : 'smooth' }); openEditor(row, true); } },
          function (er) { toast(errorText(er), true); }).then(function () { btn.classList.remove('is-adding'); });
      }
      document.querySelectorAll('.rf-chip-form').forEach(function (f) {
        var c = f.querySelector('[data-rf-chip]');
        c.addEventListener('click', function (e) { e.preventDefault(); if (!c.classList.contains('is-adding')) addField(f); });
        c.addEventListener('keydown', function (e) { if (e.key === ' ') { e.preventDefault(); c.click(); } });
        f.addEventListener('submit', function (e) { e.preventDefault(); addField(f); });
      });

      // Save the order shown on screen; put it back if the server refuses.
      function saveOrder(before) {
        var fd = new FormData(); rows().forEach(function (r) { fd.append('ordered_questions[]', r.dataset.id); });
        post(list.getAttribute('data-reorder'), fd).then(function (d) { render(d); toast(d.message); }, function (er) {
          before.forEach(function (r) { list.appendChild(r); }); toast(errorText(er), true);
        });
      }

      // Drag and drop. The marker shows where the field will land.
      var dragging = null, chip = null, marker = document.createElement('li');
      marker.className = 'rf-marker'; marker.setAttribute('aria-hidden', 'true');
      function place(y) {
        var after = null;
        rows().forEach(function (el) { if (el === dragging) return; var r = el.getBoundingClientRect(); if (y > r.top + r.height / 2) after = el; });
        var fixed = list.querySelectorAll('.rf-fixed'), anchor = after || fixed[fixed.length - 1];
        if (anchor.nextSibling !== marker) list.insertBefore(marker, anchor.nextSibling);
      }
      function finishDrag() {
        if (dragging) dragging.classList.remove('is-dragging');
        dragging = null; chip = null; marker.remove(); list.classList.remove('is-target');
      }
      document.addEventListener('dragstart', function (e) {
        var row = e.target.closest && e.target.closest('[data-rf-item]');
        var c = e.target.closest && e.target.closest('[data-rf-chip]');
        if (row) { dragging = row; row.classList.add('is-dragging'); e.dataTransfer.effectAllowed = 'move'; }
        else if (c) { chip = c.closest('form'); e.dataTransfer.effectAllowed = 'copy'; list.classList.add('is-target'); }
        else return;
        e.dataTransfer.setData('text/plain', '');
      });
      list.addEventListener('dragover', function (e) { if (!dragging && !chip) return; e.preventDefault(); e.dataTransfer.dropEffect = dragging ? 'move' : 'copy'; place(e.clientY); });
      list.addEventListener('drop', function (e) {
        if (!dragging && !chip) return;
        e.preventDefault();
        if (!marker.parentNode) place(e.clientY);
        var index = 0;
        for (var n = list.firstElementChild; n && n !== marker; n = n.nextElementSibling) { if (n.matches('[data-rf-item]') && n !== dragging) index++; }
        if (chip) { var f = chip; finishDrag(); addField(f, index + 1); return; }
        var before = rows(), row = dragging;
        list.insertBefore(row, marker); finishDrag();
        if (rows().some(function (r, i) { return r !== before[i]; })) saveOrder(before);
      });
      document.addEventListener('dragend', finishDrag);

      // Keyboard: arrow keys on the handle move a field.
      list.addEventListener('keydown', function (e) {
        var h = e.target.closest('.rf-handle'); if (!h || (e.key !== 'ArrowUp' && e.key !== 'ArrowDown')) return;
        e.preventDefault();
        var row = h.closest('[data-rf-item]'), before = rows(), i = before.indexOf(row), j = e.key === 'ArrowUp' ? i - 1 : i + 1;
        if (j < 0 || j >= before.length) return;
        if (e.key === 'ArrowUp') list.insertBefore(row, before[j]); else list.insertBefore(row, before[j].nextSibling);
        h.focus(); saveOrder(before);
      });

      if (window.RF_OPEN) { var r0 = list.querySelector('[data-id="' + window.RF_OPEN + '"]'); if (r0) openEditor(r0); }
    }

    // Form settings save in place.
    document.querySelectorAll('[data-rf-live]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = form.querySelector('[type=submit]'); busy(btn, true);
        post(form.action, new FormData(form)).then(function (d) {
          toast(d.message); var h = document.querySelector('[data-rf-name]'); if (h && d.name) h.textContent = d.name;
        }, function (er) { toast(errorText(er), true); }).then(function () { busy(btn, false); });
      });
    });

    // Preview: the form exactly as attendees fill it in, from the fields on screen now.
    var pv = document.getElementById('rf-preview'), pvOpen = document.querySelector('[data-rf-preview-open]');
    if (pv && pvOpen && pv.showModal) {
      var pvPage = pv.querySelector('[data-rf-pv-page]'), pvFrame = pv.querySelector('[data-rf-pv-frame]'), uid = 0;
      function el(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; }
      function req(label, on) { var l = el('span', null, label); if (on) { var r = el('span', 'pv-req', ' *'); r.setAttribute('aria-hidden', 'true'); l.appendChild(r); } return l; }
      function settingsValue(name) { var f = document.querySelector('[data-rf-live] [name="' + name + '"]'); return f ? f.value.trim() : ''; }
      function input(type, attrs) { var i = el('input'); i.type = type; for (var k in attrs || {}) i.setAttribute(k, attrs[k]); return i; }
      function choices(qq) { return String(qq.options || '').split('\n').map(function (o) { return o.trim(); }).filter(Boolean); }
      function field(qq, prefix) {
        var id = prefix + '-' + (++uid), wrap = el('div', 'pv-field'), help = qq.help ? el('small', null, qq.help) : null;
        wrap.dataset.key = qq.key || '';
        if (qq.condKey) { wrap.dataset.condKey = qq.condKey; wrap.dataset.condOp = qq.condOp || 'equals'; wrap.dataset.condValue = qq.condValue || ''; }
        if (qq.type === 'terms') {
          var t = el('div', 'pv-terms'); t.appendChild(el('h4', null, qq.label)); if (qq.help) t.appendChild(el('p', null, qq.help)); wrap.appendChild(t); return wrap;
        }
        if (qq.type === 'consent' || qq.type === 'checkbox') {
          var lab = el('label', 'pv-check'), box = input('checkbox', { id: id, value: '1' });
          lab.appendChild(box);
          if (qq.type === 'consent') lab.appendChild(req(qq.label, qq.required));
          else { var top = el('label', 'pv-label'); top.setAttribute('for', id); top.appendChild(req(qq.label, qq.required)); wrap.appendChild(top); lab.appendChild(el('span', null, 'Yes')); }
          wrap.appendChild(lab); if (help) wrap.appendChild(help); return wrap;
        }
        if (qq.type === 'radio' || qq.type === 'multi_select') {
          var fs = el('fieldset', 'pv-group'), lg = el('legend'); lg.appendChild(req(qq.label, qq.required)); fs.appendChild(lg);
          choices(qq).forEach(function (o, i) {
            var l = el('label', 'pv-check'), c = input(qq.type === 'radio' ? 'radio' : 'checkbox', { name: id, value: o, id: id + '-' + i });
            l.appendChild(c); l.appendChild(el('span', null, o)); fs.appendChild(l);
          });
          wrap.appendChild(fs); if (help) wrap.appendChild(help); return wrap;
        }
        var label = el('label', 'pv-label'); label.setAttribute('for', id); label.appendChild(req(qq.label, qq.required)); wrap.appendChild(label);
        var ctl;
        if (qq.type === 'textarea') { ctl = el('textarea'); ctl.rows = 4; }
        else if (qq.type === 'select') { ctl = el('select'); ctl.appendChild(el('option', null, 'Choose an option')).value = ''; choices(qq).forEach(function (o) { ctl.appendChild(el('option', null, o)).value = o; }); }
        else if (qq.type === 'file') { ctl = input('file', { accept: '.pdf,.jpg,.jpeg,.png' }); }
        else { ctl = input(qq.type === 'phone' ? 'tel' : (['email', 'number', 'date'].indexOf(qq.type) > -1 ? qq.type : 'text')); }
        ctl.id = id; if (qq.placeholder && ctl.tagName !== 'SELECT') ctl.placeholder = qq.placeholder;
        if (qq.max) ctl.setAttribute('maxlength', qq.max);
        wrap.appendChild(ctl);
        if (qq.type === 'file') wrap.appendChild(el('small', null, 'PDF, JPG or PNG. Files are stored privately.'));
        if (help) wrap.appendChild(help);
        return wrap;
      }
      function answer(scope, key) {
        var f = scope.querySelector('.pv-field[data-key="' + key + '"]'); if (!f) return '';
        var on = [].filter.call(f.querySelectorAll('input[type=radio],input[type=checkbox]'), function (i) { return i.checked; });
        if (f.querySelector('input[type=radio],input[type=checkbox]')) {
          if (f.querySelectorAll('input[type=checkbox]').length === 1 && !f.querySelector('fieldset')) return on.length ? '1' : '';
          return on.map(function (i) { return i.value; }).join(', ');
        }
        var c = f.querySelector('input,select,textarea'); return c ? c.value.trim() : '';
      }
      function applyConditions() {
        pvPage.querySelectorAll('[data-pv-scope]').forEach(function (scope) {
          scope.querySelectorAll('.pv-field[data-cond-key]').forEach(function (f) {
            var a = scope.querySelector('.pv-field[data-key="' + f.dataset.condKey + '"]') ? answer(scope, f.dataset.condKey) : answer(pvPage, f.dataset.condKey);
            var v = f.dataset.condValue, op = f.dataset.condOp, show;
            var hit = a.split(', ').some(function (x) { return x.toLowerCase() === v.toLowerCase(); }) || (v === '1' && a === '1') || (/^yes$/i.test(v) && a === '1');
            show = op === 'not_empty' ? a !== '' : (op === 'not_equals' ? !hit : hit);
            f.hidden = !show;
          });
        });
      }
      function section(n, title, copy) {
        var fs = el('section', 'pv-section'), h = el('h3'); h.appendChild(el('span', 'pv-num', String(n))); h.appendChild(document.createTextNode(title)); fs.appendChild(h);
        if (copy) fs.appendChild(el('p', 'pv-copy', copy)); return fs;
      }
      function nameEmail(prefix, emailRequired) {
        var g = el('div', 'pv-two');
        g.appendChild(field({ label: 'Full name', type: 'text', required: true }, prefix));
        g.appendChild(field({ label: 'Email address', type: 'email', required: emailRequired }, prefix));
        return g;
      }
      function build() {
        uid = 0; pvPage.textContent = '';
        var rowsNow = [].map.call(document.querySelectorAll('[data-rf-list] [data-rf-item]'), function (r) { try { return JSON.parse(r.dataset.q); } catch (e) { return null; } }).filter(Boolean);
        var head = el('header', 'pv-head'); head.appendChild(el('p', 'pv-kicker', 'Event registration'));
        head.appendChild(el('h2', null, (document.querySelector('[data-rf-name]') || {}).textContent || 'Registration'));
        var intro = settingsValue('intro'); if (intro) head.appendChild(el('p', 'pv-intro', intro));
        pvPage.appendChild(head);
        var form = el('form', 'pv-form'); form.setAttribute('novalidate', ''); form.addEventListener('submit', function (e) { e.preventDefault(); });
        var s1 = section(1, 'Primary contact'), b1 = el('div'); b1.setAttribute('data-pv-block', ''); b1.setAttribute('data-pv-scope', '');
        b1.appendChild(nameEmail('pc', true));
        rowsNow.filter(function (r) { return r.scope !== 'attendee'; }).forEach(function (r) { b1.appendChild(field(r, 'pc')); });
        s1.appendChild(b1); form.appendChild(s1);
        var s2 = section(2, 'Attendees', 'Add every person attending, including the primary contact when applicable.');
        var card = el('article', 'pv-attendee'); card.setAttribute('data-pv-block', ''); card.setAttribute('data-pv-scope', '');
        card.appendChild(el('h4', null, 'Attendee 1')); card.appendChild(nameEmail('at', false));
        rowsNow.filter(function (r) { return r.scope === 'attendee'; }).forEach(function (r) { card.appendChild(field(r, 'at')); });
        s2.appendChild(card); s2.appendChild(el('button', 'pv-add', '+ Add another attendee')).type = 'button'; form.appendChild(s2);
        var consent = el('div', 'pv-consent'), cl = el('label', 'pv-check'); cl.appendChild(input('checkbox'));
        cl.appendChild(req('I have read the privacy notice below and consent to Tica and the event organizer processing this registration for event administration.', false));
        consent.appendChild(cl);
        var privacy = settingsValue('privacy_notice');
        if (privacy) { var d = el('details'); d.appendChild(el('summary', null, 'Read privacy notice')); d.appendChild(el('p', null, privacy)); consent.appendChild(d); }
        form.appendChild(consent);
        var go = el('button', 'pv-submit', 'Submit registration'); go.type = 'submit'; form.appendChild(go);
        form.appendChild(el('p', 'pv-fine', 'Preview only. Submitting here sends nothing.'));
        if (!rowsNow.length) form.insertBefore(el('p', 'pv-empty', 'Only name and email are asked so far. Add fields to see them here.'), s2);
        pvPage.appendChild(form);
        form.addEventListener('input', applyConditions); form.addEventListener('change', applyConditions);
        applyConditions();
      }
      pvOpen.addEventListener('click', function () { build(); pv.showModal(); pvPage.scrollTop = 0; });
      pv.querySelector('[data-rf-pv-close]').addEventListener('click', function () { pv.close(); });
      pv.addEventListener('click', function (e) { if (e.target === pv) pv.close(); });
      pv.querySelectorAll('[data-rf-pv-size]').forEach(function (b) {
        b.addEventListener('click', function () {
          pv.querySelectorAll('[data-rf-pv-size]').forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); });
          pvFrame.dataset.size = b.dataset.rfPvSize;
        });
      });
    }

    // Every other form posts normally: remember the scroll spot and show it is saving.
    page.addEventListener('submit', function (e) {
      if (e.defaultPrevented) return;
      try { sessionStorage.setItem(scrollKey, JSON.stringify({ y: window.scrollY, t: Date.now() })); } catch (err) {}
      var btn = e.submitter || e.target.querySelector('[type=submit]');
      if (btn) setTimeout(function () { busy(btn, true); }, 0);
    });
    window.addEventListener('pageshow', function (e) {
      if (e.persisted) page.querySelectorAll('[aria-busy=true]').forEach(function (b) { busy(b, false); });
    });
  })();
</script>
