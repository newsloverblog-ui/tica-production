<script>
  // Calm motion and natural page behaviour for the registration form pages.
  (function () {
    var page = document.querySelector('.rf-page');
    if (!page) return;
    var key = 'rf-seen:' + location.pathname, scrollKey = 'rf-scroll:' + location.pathname;
    try {
      // Cards ease in on the first visit only, not after every save.
      if (!sessionStorage.getItem(key)) { page.classList.add('rf-intro'); sessionStorage.setItem(key, '1'); }
      // After a save that comes back to this page, return to where the organiser was.
      var saved = JSON.parse(sessionStorage.getItem(scrollKey) || 'null');
      sessionStorage.removeItem(scrollKey);
      if (saved && Date.now() - saved.t < 15000 && !location.hash) window.scrollTo(0, saved.y);
    } catch (e) {}
    var editing = page.querySelector('.rf-field.is-editing');
    if (editing && location.hash === '#rf-editor') {
      var q = document.querySelector('#rf-editor input[name=label]');
      if (q) setTimeout(function () { q.focus({ preventScroll: true }); q.select(); }, 250);
    }
    page.addEventListener('submit', function (e) {
      if (e.defaultPrevented) return;
      var form = e.target;
      try { sessionStorage.setItem(scrollKey, JSON.stringify({ y: window.scrollY, t: Date.now() })); } catch (err) {}
      var btn = e.submitter || form.querySelector('[type=submit]');
      if (!btn) return;
      if (btn.matches('[data-rf-chip]')) { btn.classList.add('is-adding'); return; }
      // Show that the save is happening and stop double submits.
      setTimeout(function () { btn.disabled = true; btn.setAttribute('aria-busy', 'true'); }, 0);
    });
    // A chip submitted by drag-and-drop does not fire submit, so mark it here.
    document.addEventListener('drop', function () {
      var c = page.querySelector('[data-rf-chip].is-dragged');
      if (c) c.classList.add('is-adding');
      try { sessionStorage.setItem(scrollKey, JSON.stringify({ y: window.scrollY, t: Date.now() })); } catch (err) {}
    }, true);
    document.addEventListener('dragstart', function (e) { var c = e.target.closest && e.target.closest('[data-rf-chip]'); if (c) c.classList.add('is-dragged'); });
    document.addEventListener('dragend', function () { page.querySelectorAll('.is-dragged').forEach(function (c) { c.classList.remove('is-dragged'); }); });
    // Leaving the page with the browser back button keeps buttons usable.
    window.addEventListener('pageshow', function (e) {
      if (!e.persisted) return;
      page.querySelectorAll('[aria-busy=true]').forEach(function (b) { b.disabled = false; b.removeAttribute('aria-busy'); });
      page.querySelectorAll('.is-adding').forEach(function (c) { c.classList.remove('is-adding'); });
    });
  })();
</script>
