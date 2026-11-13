@verbatim
<section class="page" data-page="dashboard">
  <div class="db od">
    <div class="db-main od-main" id="db-top">
      <p class="db-flag" id="db-flag" hidden></p>

      <!-- OVERVIEW: key numbers, then sales and the next event, then recent orders. -->
      <div class="od-p ov" data-p="overview">
        <header class="t-page-head">
          <div><h1 id="db-hi">Welcome back</h1><p>Here's how your events are doing.</p></div>
          <label class="ov-range"><span class="t-sr">Period</span><select class="t-select" id="od-range"></select></label>
        </header>
        <section class="ov-setup" id="od-setup" aria-labelledby="od-setup-h" hidden></section>
        <div class="ov-stats" id="db-kpis">
          <span class="t-skel" style="height:104px"></span><span class="t-skel" style="height:104px"></span><span class="t-skel" style="height:104px"></span>
        </div>
        <div class="ov-grid">
          <section class="t-card" aria-labelledby="ov-chart-h"><div class="t-card__head"><h2 class="t-card__title" id="ov-chart-h">Tickets sold per day</h2></div><div class="db-chart" id="db-chart"></div></section>
          <section class="t-card ov-next" id="od-next" aria-label="Next event"></section>
        </div>
        <section class="t-card ov-orders" aria-labelledby="ov-ord-h">
          <div class="t-card__head"><h2 class="t-card__title" id="ov-ord-h">Recent orders</h2><button class="t-btn t-btn--ghost t-btn--sm" type="button" data-panel="orders">View all</button></div>
          <div id="db-ord"></div>
        </section>
      </div>

      <!-- EVENTS -->
      <div class="od-p" data-p="events" hidden>
        <header class="t-page-head"><div><h1>Events</h1><p>Edit details, control sales and manage tickets for each event.</p></div><a class="t-btn t-btn--primary od-events-create" href="/create">Create an event</a></header>
        <div class="t-tabs" role="tablist" aria-label="Show events"><button class="t-tab" type="button" role="tab" aria-selected="true" data-ef="up">Upcoming<span class="t-count" data-efc="up">0</span></button><button class="t-tab" type="button" role="tab" aria-selected="false" data-ef="draft">Drafts<span class="t-count" data-efc="draft">0</span></button><button class="t-tab" type="button" role="tab" aria-selected="false" data-ef="past">Past<span class="t-count" data-efc="past">0</span></button></div>
        <div class="t-toolbar"><label class="t-search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg><span class="t-sr">Search events</span><input class="t-input" id="ev-q" type="search" placeholder="Search by name or city"></label></div>
        <div id="od-evlist"></div>
      </div>

      <!-- ORDERS -->
      <div class="od-p" data-p="orders" hidden>
        <header class="t-page-head"><div><h1>Orders and attendees</h1><p>Find anyone, resend tickets, refund orders and message your guests.</p></div>
          <div class="or-actions"><button class="t-btn t-btn--secondary" type="button" id="od-export">Export CSV</button><button class="t-btn t-btn--secondary" type="button" data-dlg="od-comp">Add guest</button><button class="t-btn t-btn--primary" type="button" data-dlg="od-msg">Message attendees</button></div></header>
        <div class="t-toolbar">
          <label class="t-search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg><span class="t-sr">Search attendees</span><input class="t-input" id="od-q" type="search" placeholder="Name, email, phone or order number"></label>
          <select class="t-select" id="od-fe" aria-label="Event"><option value="">All events</option></select>
          <select class="t-select" id="od-fs" aria-label="Status"><option value="">Any status</option></select>
          <label class="or-date"><span class="t-sr">Ordered from</span><input class="t-input" id="od-from" type="date" aria-label="Ordered from"></label>
          <label class="or-date"><span class="t-sr">Ordered to</span><input class="t-input" id="od-to" type="date" aria-label="Ordered to"></label>
          <button class="t-btn t-btn--ghost t-btn--sm" type="button" id="od-clear" hidden>Clear filters</button>
        </div>
        <div id="od-att" aria-live="polite"></div>
        <div class="t-pager"><span id="od-att-msg" role="status"></span><button class="t-btn t-btn--secondary t-btn--sm" id="od-att-more" type="button" hidden>Load more</button></div>
      </div>

      <!-- PAYOUTS -->
      <div class="od-p" data-p="payouts" hidden>
        <header class="t-page-head"><div><h1>Payouts</h1><p>Your ticket money, sent straight to your bank.</p></div><button class="t-btn t-btn--primary" type="button" id="od-wd-btn">Withdraw</button></header>
        <div class="pay-bal" id="od-bal">
          <div class="t-card t-stat"><span class="t-stat__label">Available</span><b class="t-stat__value" id="od-avail">₦0</b><span class="t-stat__note">Ready to withdraw now</span></div>
          <div class="t-card t-stat"><span class="t-stat__label">Pending</span><b class="t-stat__value" id="od-pend">₦0</b><span class="t-stat__note">Requested and on its way to your bank</span></div>
          <div class="t-card t-stat"><span class="t-stat__label">Paid out</span><b class="t-stat__value" id="od-paid">₦0</b><span class="t-stat__note">Since you joined</span></div>
        </div>
        <div class="pay-grid">
          <section class="t-card" id="od-bankcard" aria-labelledby="od-bank-h">
            <div class="t-card__head"><h2 class="t-card__title" id="od-bank-h">Payout account</h2></div>
            <div id="od-bank-view"></div>
          </section>
          <section class="t-card" aria-labelledby="od-sched-h">
            <div class="t-card__head"><h2 class="t-card__title" id="od-sched-h">How you get paid</h2></div>
            <div class="od-radio" role="radiogroup" aria-labelledby="od-sched-h">
              <label><input type="radio" name="od-sched" value="auto" checked><span><b>Automatically</b><small>We send each event's sales to your bank as soon as they're released.</small></span></label>
              <label><input type="radio" name="od-sched" value="manual"><span><b>When I ask</b><small>Money waits in your balance until you press Withdraw.</small></span></label>
            </div>
            <p class="t-help">Transfers are free. Payouts pause for 24 hours after a bank account change, to protect you from fraud.</p>
          </section>
        </div>
        <section class="t-card pay-hist" aria-labelledby="od-hist-h"><div class="t-card__head"><h2 class="t-card__title" id="od-hist-h">Payout history</h2></div><div id="od-hist"></div></section>
      </div>

      <!-- PROMOTE -->
      <div class="od-p" data-p="promote" hidden>
        <div class="db-head"><div><h1>Promote</h1><p>Book TICA placements, create discount codes and track the channels selling your tickets.</p></div></div>
        <section class="db-card od-promo-studio" aria-labelledby="od-campaign-h"><div class="db-ch"><div><h2 id="od-campaign-h">Paid promotion</h2><p class="od-fine">Choose an admin-priced placement. Every campaign is reviewed before it goes live.</p></div><div class="od-promo-balance"><span>Available TICA balance</span><b id="od-promo-balance">—</b></div></div>
          <div class="od-empty-state" id="od-promo-empty" hidden><i aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 10v4a1 1 0 0 0 1 1h3l7 4V5L8 9H5a1 1 0 0 0-1 1zM18 9a4 4 0 0 1 0 6"/></svg></i><h3>Paid promotion is not available yet</h3><p>TICA will show placement options here when campaign plans are available. Your discount codes and tracking links still work.</p></div>
          <form class="od-form" id="od-promo-f" novalidate>
            <div class="od-row3"><div class="inp"><label for="od-promo-plan">Placement</label><select id="od-promo-plan" required></select></div><div class="inp"><label for="od-promo-event">Event</label><select id="od-promo-event" required></select></div><div class="inp"><label for="od-promo-start">Start date <small>(day / month / year)</small></label><input id="od-promo-start" type="date" required></div></div>
            <div class="od-row2"><div class="inp"><label for="od-promo-headline">Headline <small>(optional)</small></label><input id="od-promo-headline" maxlength="160"></div><div class="inp"><label for="od-promo-url">Destination URL <small>(optional)</small></label><input id="od-promo-url" type="url" placeholder="https://..."></div></div>
            <div class="inp"><label for="od-promo-body">Promotion copy <small>(optional)</small></label><textarea id="od-promo-body" maxlength="1000" rows="3"></textarea></div>
            <div class="inp od-upload"><label for="od-promo-creative">Campaign image <small>(optional)</small><span>Drop an event image here or choose a file</span><em>JPG, PNG, WebP or GIF, up to 3 MB</em></label><input id="od-promo-creative" type="file" accept="image/jpeg,image/png,image/webp,image/gif"></div>
            <button class="btn btn-hot" type="submit">Create promotion</button><p class="hint" id="od-promo-m" role="status"></p>
          </form>
          <div class="od-campaigns" id="od-campaigns"></div>
        </section>
        <div class="od-pr2">
          <section class="db-card"><div class="db-ch"><h2>Discount codes</h2></div>
            <form class="od-form" id="od-code-f" novalidate>
              <div class="od-row3"><div class="inp"><label for="od-cn">Code</label><input id="od-cn" type="text" placeholder="EARLYBIRD" autocapitalize="characters"></div><div class="inp"><label for="od-cv">Discount</label><select id="od-cv"><option value="10">10% off</option><option value="20">20% off</option><option value="50">50% off</option><option value="1000">₦1,000 off</option></select></div><div class="inp"><label for="od-cl">Uses</label><input id="od-cl" type="number" min="1" value="50"></div></div>
              <button class="btn btn-line" type="submit">Create code</button><p class="hint" id="od-code-m" role="status"></p>
            </form>
            <ul class="od-codes" id="od-codes"></ul>
          </section>
          <section class="db-card"><div class="db-ch"><h2>Tracking links</h2></div>
            <p class="od-fine">Give each channel or promoter its own link, and see which one sells.</p>
            <ul class="od-links" id="od-links"></ul>
            <form class="od-form od-inline" id="od-link-f" novalidate><div class="inp"><label for="od-ln">New link name</label><input id="od-ln" type="text" placeholder="e.g. Chioma's Instagram"></div><button class="btn btn-line" type="submit">Create link</button></form>
          </section>
        </div>
        <section class="db-card" aria-labelledby="od-pixel-h"><div class="db-ch"><div><h2 id="od-pixel-h">Conversion tracking</h2><p class="od-fine">Submit only the provider's ID. TICA loads an approved integration after consent; custom JavaScript is never accepted.</p></div></div>
          <form class="od-form od-row3" id="od-pixel-f" novalidate><div class="inp"><label for="od-pixel-provider">Provider</label><select id="od-pixel-provider"><option value="meta">Meta Pixel</option><option value="tiktok">TikTok Pixel</option><option value="google">Google tag</option></select></div><div class="inp"><label for="od-pixel-id">Pixel / tag ID</label><input id="od-pixel-id" maxlength="100" required></div><div class="inp"><label for="od-pixel-label">Label</label><input id="od-pixel-label" maxlength="100" placeholder="Main website" required></div><button class="btn btn-line" type="submit">Submit for review</button><p class="hint" id="od-pixel-m" role="status"></p></form>
          <ul class="od-links" id="od-pixels"></ul>
        </section>
      </div>

      <!-- TEAM -->
      <div class="od-p" data-p="team" hidden>
        <header class="t-page-head"><div><h1>Team</h1><p>Let people help run your events, with only the access they need.</p></div><button class="t-btn t-btn--primary" type="button" data-dlg="od-inv-dlg" id="od-inv-open">Invite member</button></header>
        <div id="od-team"></div>
        <section class="tm-roles" aria-labelledby="od-roles-h"><h2 class="t-card__title" id="od-roles-h">What each role can do</h2><div class="od-roles" id="od-roles"></div></section>
      </div>

      <!-- SETTINGS -->
      <div class="od-p" data-p="settings" hidden>
        <header class="t-page-head"><div><h1>Settings</h1><p>Your organiser profile, defaults for new events, notifications and security.</p></div></header>
        <div class="t-tabs" role="tablist" aria-label="Settings sections" id="od-stabs">
          <button class="t-tab is-on" type="button" role="tab" aria-selected="true" aria-controls="st-profile" id="stt-profile" data-stab="profile">Profile</button>
          <button class="t-tab" type="button" role="tab" aria-selected="false" aria-controls="st-defaults" id="stt-defaults" data-stab="defaults" tabindex="-1">Event defaults</button>
          <button class="t-tab" type="button" role="tab" aria-selected="false" aria-controls="st-notifications" id="stt-notifications" data-stab="notifications" tabindex="-1">Notifications</button>
          <button class="t-tab" type="button" role="tab" aria-selected="false" aria-controls="st-security" id="stt-security" data-stab="security" tabindex="-1">Security</button>
        </div>
        <section class="t-card st-pane" role="tabpanel" id="st-profile" aria-labelledby="stt-profile" data-spane="profile">
          <div class="t-card__head"><div><h2 class="t-card__title">Organiser profile</h2><p class="t-help">Shown on your public page and on tickets.</p></div></div>
          <form class="od-form" id="od-prof" novalidate>
              <div class="od-profile-photo"><span class="od-photo-frame"><span class="od-photo-ini" id="od-photo-ini" aria-hidden="true"></span><img id="od-photo-preview" alt="Your profile photo" hidden></span><div><span class="od-photo-t">Profile photo</span><div class="od-photo-act"><label class="btn btn-line od-upl" for="od-photo"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/></svg>Choose a photo</label><input id="od-photo" class="od-file" name="photo" type="file" accept="image/jpeg,image/png,image/webp,image/gif"><span class="od-photo-name" id="od-photo-name"></span></div><small>JPG, PNG, WebP or GIF, up to 3 MB. Square photos look best.</small></div></div>
              <div class="inp"><label for="od-pn">Organiser name</label><input id="od-pn" type="text" autocomplete="organization" required></div>
              <div class="od-row2"><div class="inp"><label for="od-pe">Sign-in email</label><input id="od-pe" type="email" autocomplete="email" required><small>Changing this email signs you out and requires verification.</small></div><div class="inp"><label for="od-pu">Username</label><input id="od-pu" type="text" autocomplete="username" required></div></div>
              <div class="inp" id="od-pcp-row" hidden><label for="od-pcp">Current password</label><input id="od-pcp" type="password" autocomplete="current-password"><small>Needed because you're changing your sign-in email.</small></div>
              <div class="inp"><label for="od-pa">About</label><textarea id="od-pa" rows="3"></textarea></div>
              <div class="od-row2"><div class="inp"><label for="od-pw">Phone / WhatsApp</label><input id="od-pw" type="tel" autocomplete="tel"></div><div class="inp"><label for="od-paddr">Address</label><input id="od-paddr" type="text" autocomplete="street-address"></div></div>
              <div class="od-row2"><div class="inp"><label for="od-pcity">City</label><input id="od-pcity" type="text" autocomplete="address-level2"></div><div class="inp"><label for="od-pstate">State</label><input id="od-pstate" type="text" autocomplete="address-level1"></div></div>
              <div class="od-row2"><div class="inp"><label for="od-pcountry">Country</label><input id="od-pcountry" type="text" autocomplete="country-name"></div><div class="inp"><label for="od-pzip">Postcode</label><input id="od-pzip" type="text" autocomplete="postal-code"></div></div>
              <h3 class="st-sub">Social links</h3>
              <div class="od-row2"><div class="inp"><label for="od-pi">Instagram</label><input id="od-pi" type="text" placeholder="@yourhandle"></div><div class="inp"><label for="od-pt">TikTok</label><input id="od-pt" type="text" placeholder="@yourhandle"></div></div>
              <div class="inp"><label for="od-pweb">Website</label><input id="od-pweb" type="url" placeholder="https://example.com"></div>
              <div class="od-row2"><div class="inp"><label for="od-pfb">Facebook URL</label><input id="od-pfb" type="url" placeholder="https://facebook.com/..."></div><div class="inp"><label for="od-px">X / Twitter URL</label><input id="od-px" type="url" placeholder="https://x.com/..."></div></div>
              <div class="od-row2"><div class="inp"><label for="od-pli">LinkedIn URL</label><input id="od-pli" type="url" placeholder="https://linkedin.com/company/..."></div><div class="inp"><label for="od-pyt">YouTube URL</label><input id="od-pyt" type="url" placeholder="https://youtube.com/@..."></div></div>
              <button class="t-btn t-btn--primary" type="submit">Save profile</button><p class="hint" id="od-prof-m" role="status"></p>
            </form>
          </section>
        <section class="t-card st-pane" role="tabpanel" id="st-defaults" aria-labelledby="stt-defaults" data-spane="defaults" hidden>
          <div class="t-card__head"><div><h2 class="t-card__title">Defaults for new events</h2><p class="t-help">New events start with these. You can still change them per event. Changes save as you go.</p></div></div>
          <ul class="od-sw">
              <li><span><b>Buyers pay the booking fee</b><small>Turn off to include the <span data-fee-pct>6%</span> in your ticket prices instead.</small></span><input class="ck-sw" type="checkbox" checked aria-label="Buyers pay the booking fee" data-pref="buyers_pay_fee"></li>
              <li><span><b>Show tickets left on event pages</b><small>Creates urgency when stock is low.</small></span><input class="ck-sw" type="checkbox" checked aria-label="Show tickets left" data-pref="show_remaining"></li>
              <li><span><b>Waitlist when sold out</b><small>Collect emails and tell people if tickets come back.</small></span><input class="ck-sw" type="checkbox" checked aria-label="Waitlist when sold out" data-pref="waitlist"></li>
              <li><span><b>Let buyers transfer tickets</b><small>Buyers can pass a ticket to someone else before the event.</small></span><input class="ck-sw" type="checkbox" checked aria-label="Let buyers transfer tickets" data-pref="transfers"></li>
            </ul>
            </section>
        <section class="t-card st-pane" role="tabpanel" id="st-notifications" aria-labelledby="stt-notifications" data-spane="notifications" hidden>
          <div class="t-card__head"><div><h2 class="t-card__title">Notifications</h2><p class="t-help">Choose what TICA tells you about. Changes save as you go.</p></div></div>
          <ul class="od-sw">
              <li><span><b>Email me for every sale</b></span><input class="ck-sw" type="checkbox" aria-label="Email me for every sale" data-pref="notify_sale"></li>
              <li><span><b>Daily and weekly sales summaries</b><small>Receive scheduled performance digests for your events.</small></span><input class="ck-sw" type="checkbox" checked aria-label="Daily and weekly sales summaries" data-pref="daily_summary"></li>
              <li><span><b>Text me about payouts</b><small>An SMS to your phone number when a payout is sent or doesn't go through.</small></span><input class="ck-sw" type="checkbox" aria-label="Text me about payouts" data-pref="payout_sms"></li>
              <li><span><b>Payout status emails</b><small>Always on for account security and transfer records.</small></span><span class="od-sec-state on">Always on</span></li>
            </ul>
          </section>
        <div class="st-pane t-stack" role="tabpanel" id="st-security" aria-labelledby="stt-security" data-spane="security" hidden>
          <section class="t-card" id="password"><div class="t-card__head"><div><h2 class="t-card__title">Password</h2><p class="t-help">Use a password you don't use anywhere else.</p></div></div>
            <form class="od-form" id="od-pass" novalidate>
              <div class="inp"><label for="od-pass-current">Current password</label><input id="od-pass-current" type="password" autocomplete="current-password" required></div>
              <div class="od-row2"><div class="inp"><label for="od-pass-new">New password</label><input id="od-pass-new" type="password" autocomplete="new-password" minlength="8" required><small>At least 8 characters with upper and lowercase letters and a number.</small></div><div class="inp"><label for="od-pass-confirm">Confirm new password</label><input id="od-pass-confirm" type="password" autocomplete="new-password" minlength="8" required></div></div>
              <button class="t-btn t-btn--secondary" type="submit">Update password</button><p class="hint" id="od-pass-m" role="status"></p>
            </form>
            </section>
          <section class="t-card"><div class="t-card__head"><div><h2 class="t-card__title">Two-factor authentication</h2><p class="t-help">Sign in with your password and a code from an authenticator app. It's also asked for when you change your bank account.</p></div><span class="t-badge od-sec-state" id="od-mfa-state">Off</span></div>
            <div class="od-2fa" id="od-2fa"></div>
            <p class="t-help">Prefer codes by email instead? <a id="od-security-link" href="/account-security/organizer">Use email codes</a></p>
          </section>
        </div>
      </div>
    </div>
  </div>
</section>

<dialog class="od-dlg" id="od-inv-dlg" aria-labelledby="od-inv-h">
  <div class="sh-head"><div><h2 id="od-inv-h">Invite a team member</h2><p>They get an email with a link to join. You can change their role or remove them any time.</p></div><button class="sh-x" type="button" data-close aria-label="Close"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>
  <form class="od-form" id="od-inv" novalidate><div class="inp"><label for="od-ie">Email</label><input id="od-ie" type="email" placeholder="name@example.com" autocomplete="email"></div><div class="inp"><label for="od-ir">Role</label><select id="od-ir"></select><small id="od-ir-help"></small></div><button class="t-btn t-btn--primary t-btn--block" type="submit">Send invite</button><p class="hint" id="od-inv-m" role="alert"></p></form>
</dialog>
<dialog class="od-dlg" id="od-ev-dlg" aria-labelledby="od-ev-h"><div id="od-ev-body"></div></dialog>
<dialog class="od-dlg" id="od-wd" aria-labelledby="od-wd-h"><div id="od-wd-body"></div></dialog>
<dialog class="od-dlg" id="od-2fa-dlg" aria-labelledby="od-2fa-h"><div id="od-2fa-body"></div></dialog>
<dialog class="od-dlg" id="od-msg" aria-labelledby="od-msg-h">
  <div class="sh-head"><div><h2 id="od-msg-h">Message attendees</h2><p>Sent by email and SMS from TICA, with your organiser name.</p></div><button class="sh-x" type="button" data-close aria-label="Close"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>
  <form class="od-form" id="od-msg-f" novalidate>
    <div class="inp"><label for="od-ma">Send to</label><select id="od-ma"><option>Everyone with a ticket to Afterdark Owerri (348)</option><option>Afterdark Owerri, VIP only (52)</option><option>Everyone with a ticket to Kulture Market (132)</option><option>People who haven't checked in yet</option></select></div>
    <div class="inp"><label for="od-mt">Message</label><textarea id="od-mt" rows="4">Hi! Doors open at 9:30 PM. Bring your QR code and a valid ID. See you tonight.</textarea></div>
    <label class="od-chk"><input type="checkbox" checked> Also send as SMS <small>(₦4 per text, taken from your sales)</small></label>
    <button class="btn btn-hot" type="submit">Send message</button><p class="hint" id="od-msg-m" role="status"></p>
  </form>
</dialog>
<dialog class="od-dlg" id="od-comp" aria-labelledby="od-comp-h">
  <div class="sh-head"><div><h2 id="od-comp-h">Add a guest</h2><p>Send a free ticket to a guest, artist or sponsor. It counts toward capacity.</p></div><button class="sh-x" type="button" data-close aria-label="Close"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>
  <form class="od-form" id="od-comp-f" novalidate>
    <div class="od-row2"><div class="inp"><label for="od-gn">Guest name</label><input id="od-gn" type="text"></div><div class="inp"><label for="od-ge">Email</label><input id="od-ge" type="email"></div></div>
    <div class="od-row2"><div class="inp"><label for="od-gv">Event</label><select id="od-gv"><option>Afterdark Owerri</option><option>Kulture Market</option><option>Freelance Money Masterclass</option></select></div><div class="inp"><label for="od-gt">Ticket</label><select id="od-gt"><option>Regular</option><option>VIP</option></select></div></div>
    <button class="btn btn-hot" type="submit">Send free ticket</button><p class="hint" id="od-comp-m" role="status"></p>
  </form>
</dialog>

<!-- ACCOUNT -->
@endverbatim
