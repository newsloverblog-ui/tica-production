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
