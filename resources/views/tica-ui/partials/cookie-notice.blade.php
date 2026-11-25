<section class="ck-banner" id="ck-banner" aria-labelledby="ckb-title" hidden>
  <div class="wrap ckb-layout">
    <div class="ckb-main">
      <h2 id="ckb-title">Cookie choices</h2>
      <p>Optional cookies help personalise events, measure site use and support marketing. <a href="/policies/cookies">Cookie policy</a></p>
    </div>
    <div class="ckb-acts">
      <button class="btn btn-line" type="button" data-ck="reject">Essential only</button>
      <button class="btn btn-line" type="button" data-ck="accept">Accept all</button>
      <button class="btn btn-line" type="button" data-ck="manage" aria-label="Cookie settings">Settings</button>
    </div>
  </div>
</section>
<script>
window.TICA_consent = function () {
  var choice;
  try {
    choice = JSON.parse(localStorage.getItem('tica_consent_v1'));
  } catch (error) {
    choice = window.__ck || null;
  }
  return choice && choice.at && Date.now() - Date.parse(choice.at) > 365 * 864e5 ? null : choice;
};
document.getElementById('ck-banner').hidden = Boolean(window.TICA_consent());
</script>
