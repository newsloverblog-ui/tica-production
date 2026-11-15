@verbatim
<section class="page" data-page="dashboard">
  <div class="db od">
    <div class="db-main od-main" id="db-top">
      <p class="db-flag" id="db-flag" hidden></p>

      <!-- OVERVIEW: key numbers, then sales and the next event, then recent orders. -->
      <div class="od-p ov-wrap" data-p="overview"><div class="ov">
        <header class="ov-head">
          <div class="ov-head__copy">
            <p class="ov-eyebrow" id="od-today"></p>
            <h1 id="db-hi">Welcome back</h1>
            <p class="ov-sub">Here's how your events are doing. <span class="ov-refreshed" id="od-refreshed"></span></p>
          </div>
          <div class="ov-head__actions">
            <div class="ov-seg" role="radiogroup" aria-label="Performance period" id="od-range-seg"></div>
            <select class="t-select ov-range-select" id="od-range" aria-label="Performance period"></select>
            <a class="t-btn t-btn--primary" id="od-overview-create" href="/create"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Create an event</a>
          </div>
        </header>

        <section class="ov-setup" id="od-setup" aria-labelledby="od-setup-h" hidden></section>

        <!-- One performance rail for the chosen period, with change against the period before. -->
        <div class="ov-summary" id="db-kpis" aria-live="polite">
          <span class="t-skel"></span><span class="t-skel"></span><span class="t-skel"></span><span class="t-skel"></span>
        </div>

        <div class="ov-row ov-row--primary">
          <!-- Sales over time -->
          <section class="ov-card ov-sales" aria-labelledby="ov-chart-h">
            <div class="ov-card__head">
              <div><h2 class="ov-card__title" id="ov-chart-h">Sales performance</h2><p class="ov-card__sub" id="od-chart-sub"></p></div>
              <div class="ov-toggle" role="radiogroup" aria-label="Show">
                <button type="button" role="radio" aria-checked="true" data-metric="revenue">Revenue</button>
                <button type="button" role="radio" aria-checked="false" data-metric="tickets">Tickets</button>
              </div>
            </div>
            <div class="ov-chart" id="db-chart"></div>
            <details class="ov-table-view"><summary>Show as a table</summary><div id="od-chart-table"></div></details>
          </section>
          <!-- Next event -->
          <section class="ov-card ov-next" id="od-next" aria-label="Next event"></section>
        </div>
        <div class="ov-row ov-row--secondary">
          <!-- Events -->
          <section class="ov-card ov-events" aria-labelledby="od-top-h">
            <div class="ov-card__head"><div><h2 class="ov-card__title" id="od-top-h">Events</h2><p class="ov-card__sub">Published events and their current ticket pace.</p></div><button class="ov-link" type="button" data-panel="events">All events</button></div>
            <div id="od-top"></div>
          </section>
          <!-- Money -->
          <section class="ov-card ov-money" id="od-money" aria-labelledby="od-money-h"></section>
        </div>
        <!-- Recent orders -->
          <section class="ov-card ov-orders" aria-labelledby="ov-ord-h">
            <div class="ov-card__head"><div><h2 class="ov-card__title" id="ov-ord-h">Recent orders</h2><p class="ov-card__sub" id="od-order-count"></p></div><button class="ov-link" type="button" data-panel="orders">All orders</button></div>
            <div id="db-ord"></div>
          </section>
        <!-- Channels and promotion -->
        <section class="ov-card ov-channels" id="od-channels" aria-labelledby="od-channels-h"></section>
      </div></div>

      <!-- EVENTS -->
      <div class="od-p" data-p="events" hidden>
        <header class="t-page-head"><div><h1>Events</h1><p>Edit details, control sales and manage tickets for each event.</p></div><a class="t-btn t-btn--primary od-events-create" href="/create">Create an event</a></header>
        <div class="t-tabs" role="tablist" aria-label="Show events"><button class="t-tab" type="button" role="tab" aria-selected="true" data-ef="up">Upcoming<span class="t-count" data-efc="up">0</span></button><button class="t-tab" type="button" role="tab" aria-selected="false" data-ef="draft">Drafts<span class="t-count" data-efc="draft">0</span></button><button class="t-tab" type="button" role="tab" aria-selected="false" data-ef="past">Past<span class="t-count" data-efc="past">0</span></button></div>
        <div class="t-toolbar"><label class="t-search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg><span class="t-sr">Search events</span><input class="t-input" id="ev-q" type="search" placeholder="Search by name or city"></label></div>
        <div id="od-evlist"></div>
      </div>

      <!-- TICKET MANAGEMENT -->
      <div class="od-p tk" data-p="tickets" hidden>
        <header class="t-page-head"><div><h1>Ticket management</h1><p>Monitor inventory, ticket numbers, reserved seats and attendee assignments.</p></div>
          <div class="or-actions"><button class="t-btn t-btn--secondary" type="button" id="tk-export">Export tickets</button><button class="t-btn t-btn--primary" type="button" data-dlg="od-comp">Issue complimentary ticket</button></div></header>
        <div class="tk-stats" id="tk-stats"></div>
        <section class="t-card tk-categories" aria-labelledby="tk-cat-h">
          <div class="t-card__head"><div><h2 class="t-card__title" id="tk-cat-h">Inventory by ticket type</h2><p class="t-help">Unlimited categories show an em dash for inventory and availability.</p></div></div>
          <div class="t-toolbar"><label class="t-search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg><span class="t-sr">Search ticket types</span><input class="t-input" id="tk-cat-q" type="search" placeholder="Event or ticket type"></label><select class="t-select" id="tk-cat-event" aria-label="Event"><option value="">All events</option></select></div>
          <div id="tk-cat-table"></div>
        </section>
        <section class="t-card tk-allocations" aria-labelledby="tk-unit-h">
          <div class="t-card__head"><div><h2 class="t-card__title" id="tk-unit-h">Assigned tickets and seats</h2><p class="t-help">Search by ticket number, attendee, email, section, row or seat.</p></div></div>
          <div class="t-toolbar"><label class="t-search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg><span class="t-sr">Search assigned tickets</span><input class="t-input" id="tk-q" type="search" placeholder="REG-001, Ada, VIP, Row A or Seat 12"></label><select class="t-select" id="tk-event" aria-label="Event"><option value="">All events</option></select><select class="t-select" id="tk-category" aria-label="Ticket type"><option value="">All ticket types</option></select><select class="t-select" id="tk-status" aria-label="Ticket status"><option value="">Any status</option><option value="paid">Paid</option><option value="checked_in">Checked in</option><option value="refunded">Refunded or cancelled</option><option value="guest">Complimentary</option></select></div>
          <div id="tk-units" aria-live="polite"></div><div class="t-pager"><span id="tk-msg" role="status"></span><button class="t-btn t-btn--secondary t-btn--sm" id="tk-more" type="button" hidden>Load more</button></div>
        </section>
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
        <header class="t-page-head"><div><h1>Promote</h1><p>Share your event, make discount codes and see which channels sell.</p></div></header>
        <section class="t-card pr-share" aria-labelledby="pr-share-h">
          <div class="t-card__head"><div><h2 class="t-card__title" id="pr-share-h">Share your event</h2><p class="t-help">Post this link anywhere. Use a tracking link below to see which channel sells.</p></div></div>
          <div class="pr-share__row"><label class="t-field pr-share__ev"><span class="t-label">Event</span><select class="t-select" id="pr-ev"></select></label>
            <label class="t-field pr-share__url"><span class="t-label">Event link</span><span class="pr-copy"><input class="t-input" id="pr-url" type="text" readonly><button class="t-btn t-btn--primary" type="button" id="pr-copy">Copy link</button></span></label></div>
          <div class="pr-social" id="pr-social"></div>
        </section>
        <div class="od-pr2">
          <section class="t-card"><div class="t-card__head"><h2 class="t-card__title">Discount codes</h2></div>
            <form class="od-form" id="od-code-f" novalidate>
              <div class="od-row3"><div class="inp"><label for="od-cn">Code</label><input id="od-cn" type="text" placeholder="EARLYBIRD" autocapitalize="characters"></div><div class="inp"><label for="od-cv">Discount</label><select id="od-cv"><option value="10">10% off</option><option value="20">20% off</option><option value="50">50% off</option><option value="1000">₦1,000 off</option></select></div><div class="inp"><label for="od-cl">Uses</label><input id="od-cl" type="number" min="1" value="50"></div></div>
              <div class="od-row3"><div class="inp"><label for="od-code-event">Event</label><select id="od-code-event"><option value="">All events</option></select></div><div class="inp"><label for="od-code-min">Minimum purchase</label><input id="od-code-min" type="number" min="0" step="0.01" value="0"></div><div class="inp"><label for="od-code-end">Expires</label><input id="od-code-end" type="date"></div></div>
              <button class="t-btn t-btn--secondary" type="submit">Create code</button><p class="hint" id="od-code-m" role="status"></p>
            </form>
            <ul class="od-codes" id="od-codes"></ul>
          </section>
          <section class="t-card"><div class="t-card__head"><h2 class="t-card__title">Tracking links</h2></div>
            <p class="t-help">Give each channel or promoter its own link, and see which one sells.</p>
            <ul class="od-links" id="od-links"></ul>
            <form class="od-form" id="od-link-f" novalidate><div class="od-row3"><div class="inp"><label for="od-ln">Link name</label><input id="od-ln" type="text" placeholder="e.g. Chioma's Instagram"></div><div class="inp"><label for="od-link-channel">Channel</label><input id="od-link-channel" type="text" placeholder="Instagram"></div><div class="inp"><label for="od-link-event">Event</label><select id="od-link-event"><option value="">All events</option></select></div></div><div class="inp"><label for="od-link-influencer">Influencer <small>(optional)</small></label><input id="od-link-influencer" type="text" placeholder="Chioma"></div><button class="t-btn t-btn--secondary" type="submit">Create tracking link</button></form>
          </section>
        </div>
        <details class="pr-more"><summary>Conversion tracking <span class="t-help">Meta, TikTok and Google pixels</span></summary>
        <section class="t-card" aria-labelledby="od-pixel-h"><div class="t-card__head"><div><h2 class="t-card__title" id="od-pixel-h">Conversion tracking</h2><p class="t-help">Submit only the provider's ID. TICA loads an approved integration after consent; custom JavaScript is never accepted.</p></div></div>
          <form class="od-form od-row3" id="od-pixel-f" novalidate><div class="inp"><label for="od-pixel-provider">Provider</label><select id="od-pixel-provider"><option value="meta">Meta Pixel</option><option value="tiktok">TikTok Pixel</option><option value="google">Google tag</option></select></div><div class="inp"><label for="od-pixel-id">Pixel / tag ID</label><input id="od-pixel-id" maxlength="100" required></div><div class="inp"><label for="od-pixel-label">Label</label><input id="od-pixel-label" maxlength="100" placeholder="Main website" required></div><button class="t-btn t-btn--secondary" type="submit">Submit for review</button><p class="hint" id="od-pixel-m" role="status"></p></form>
          <ul class="od-links" id="od-pixels"></ul>
        </section>
        </details>
      </div>

      <!-- TEAM -->
      <div class="od-p" data-p="team" hidden>
        <div class="db-head"><div><h1>Team</h1><p>Let people help run your events, with only the access they need.</p></div></div>
        <div class="od-team-layout">
          <section class="db-card od-team-card" aria-labelledby="od-team-members-h">
            <div class="db-ch od-team-heading"><div><h2 id="od-team-members-h">People with access</h2><p class="od-fine">Role changes take effect on the person's next action.</p></div><span class="od-team-count" id="od-team-count" aria-live="polite"></span></div>
            <ul class="od-team" id="od-team"></ul>
            <div class="od-invite-wrap">
              <div><h3>Invite someone</h3><p class="od-fine">Choose the smallest role that gives them the access they need.</p></div>
              <form class="od-form od-invite" id="od-inv" novalidate><div class="inp"><label for="od-ie">Email address</label><input id="od-ie" type="email" placeholder="name@example.com" autocomplete="email" required></div><div class="inp"><label for="od-ir">Role</label><select id="od-ir"><option>Gate staff</option><option>Manager</option><option>Finance</option></select></div><button class="btn btn-hot" type="submit">Send invite</button></form>
              <p class="hint" id="od-inv-m" role="status"></p>
            </div>
          </section>
          <aside class="db-card od-role-guide" aria-labelledby="od-role-guide-h">
            <div class="db-ch"><h2 id="od-role-guide-h">Role permissions</h2></div>
            <div class="od-roles" id="od-roles"></div>
          </aside>
        </div>
      </div>

      <!-- SETTINGS -->
      <div class="od-p" data-p="settings" hidden>
        <div class="db-head"><div><h1>Settings</h1></div></div>
        <div class="od-settings-layout">
          <section class="db-card od-settings-profile"><div class="db-ch"><h2>Profile and account</h2></div>
            <form class="od-form" id="od-prof" novalidate>
              <div class="od-profile-photo"><span class="od-photo-frame"><span class="od-photo-ini" id="od-photo-ini" aria-hidden="true"></span><img id="od-photo-preview" alt="Your profile photo" hidden></span><div><span class="od-photo-t">Profile photo</span><div class="od-photo-act"><label class="btn btn-line od-upl" for="od-photo"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/></svg>Choose a photo</label><input id="od-photo" class="od-file" name="photo" type="file" accept="image/jpeg,image/png,image/webp,image/gif"><span class="od-photo-name" id="od-photo-name"></span></div><small>JPG, PNG, WebP or GIF, up to 3 MB. Square photos look best.</small></div></div>
              <fieldset class="od-settings-group"><legend>Public profile</legend>
                <div class="inp"><label for="od-pn">Organiser name</label><input id="od-pn" type="text" autocomplete="organization" required></div>
                <div class="inp"><label for="od-pa">About</label><textarea id="od-pa" rows="3"></textarea></div>
              </fieldset>
              <fieldset class="od-settings-group"><legend>Sign-in details</legend>
                <div class="od-row2"><div class="inp"><label for="od-pe">Email</label><input id="od-pe" type="email" autocomplete="email" required><small>Changing this email signs you out and requires verification.</small></div><div class="inp"><label for="od-pu">Username</label><input id="od-pu" type="text" autocomplete="username" required></div></div>
                <div class="inp" id="od-pcp-row" hidden><label for="od-pcp">Current password</label><input id="od-pcp" type="password" autocomplete="current-password"></div>
              </fieldset>
              <fieldset class="od-settings-group"><legend>Contact and location</legend>
                <div class="od-row2"><div class="inp"><label for="od-pw">Phone / WhatsApp</label><input id="od-pw" type="tel" autocomplete="tel"></div><div class="inp"><label for="od-paddr">Address</label><input id="od-paddr" type="text" autocomplete="street-address"></div></div>
                <div class="od-row2"><div class="inp"><label for="od-pcity">City</label><input id="od-pcity" type="text" autocomplete="address-level2"></div><div class="inp"><label for="od-pstate">State</label><input id="od-pstate" type="text" autocomplete="address-level1"></div></div>
                <div class="od-row2"><div class="inp"><label for="od-pcountry">Country</label><input id="od-pcountry" type="text" autocomplete="country-name"></div><div class="inp"><label for="od-pzip">Postcode</label><input id="od-pzip" type="text" autocomplete="postal-code"></div></div>
              </fieldset>
              <fieldset class="od-settings-group"><legend>Social profiles</legend>
                <div class="od-row2"><div class="inp"><label for="od-pi">Instagram</label><input id="od-pi" type="text" placeholder="@yourhandle"></div><div class="inp"><label for="od-pt">TikTok</label><input id="od-pt" type="text" placeholder="@yourhandle"></div></div>
                <div class="inp"><label for="od-pweb">Website</label><input id="od-pweb" type="url" placeholder="https://example.com"></div>
                <div class="od-row2"><div class="inp"><label for="od-pfb">Facebook URL</label><input id="od-pfb" type="url" placeholder="https://facebook.com/..."></div><div class="inp"><label for="od-px">X / Twitter URL</label><input id="od-px" type="url" placeholder="https://x.com/..."></div></div>
                <div class="od-row2"><div class="inp"><label for="od-pli">LinkedIn URL</label><input id="od-pli" type="url" placeholder="https://linkedin.com/company/..."></div><div class="inp"><label for="od-pyt">YouTube URL</label><input id="od-pyt" type="url" placeholder="https://youtube.com/@..."></div></div>
              </fieldset>
              <div class="od-settings-actions"><button class="btn btn-hot" type="submit">Save changes</button><p class="hint" id="od-prof-m" role="status"></p></div>
            </form>
          </section>
          <div class="od-settings-stack">
          <section class="db-card"><div class="db-ch"><h2>Defaults for new events</h2></div>
            <ul class="od-sw">
              <li><span><b>Buyers pay the booking fee</b><small>Turn off to include the <span data-fee-pct>6%</span> in your ticket prices instead.</small></span><input class="ck-sw" type="checkbox" checked aria-label="Buyers pay the booking fee" data-pref="buyers_pay_fee"></li>
              <li><span><b>Show tickets left on event pages</b></span><input class="ck-sw" type="checkbox" checked aria-label="Show tickets left" data-pref="show_remaining"></li>
              <li><span><b>Waitlist when sold out</b><small>Collect emails and tell people if tickets come back.</small></span><input class="ck-sw" type="checkbox" checked aria-label="Waitlist when sold out" data-pref="waitlist"></li>
              <li><span><b>Let buyers transfer tickets</b><small>Buyers can pass a ticket to someone else before the event.</small></span><input class="ck-sw" type="checkbox" checked aria-label="Let buyers transfer tickets" data-pref="transfers"></li>
            </ul>
          </section>
          <section class="db-card"><div class="db-ch"><h2>Notifications</h2></div>
            <ul class="od-sw">
              <li><span><b>Email me for every sale</b></span><input class="ck-sw" type="checkbox" aria-label="Email me for every sale" data-pref="notify_sale"></li>
              <li><span><b>Daily and weekly sales summaries</b></span><input class="ck-sw" type="checkbox" checked aria-label="Daily and weekly sales summaries" data-pref="daily_summary"></li>
              <li><span><b>Text me about payouts</b><small>An SMS to your phone number when a payout is sent or doesn't go through.</small></span><input class="ck-sw" type="checkbox" aria-label="Text me about payouts" data-pref="payout_sms"></li>
              <li><span><b>Payout status emails</b></span><span class="od-sec-state on">Always on</span></li>
            </ul>
          </section>
          <section class="db-card od-security-card" id="password"><div class="db-ch"><h2>Security</h2><span class="od-sec-state" id="od-mfa-state">Off</span></div>
            <form class="od-form" id="od-pass" novalidate>
              <div class="inp"><label for="od-pass-current">Current password</label><input id="od-pass-current" type="password" autocomplete="current-password" required></div>
              <div class="od-row2"><div class="inp"><label for="od-pass-new">New password</label><input id="od-pass-new" type="password" autocomplete="new-password" minlength="8" required><small>At least 8 characters with upper and lowercase letters and a number.</small></div><div class="inp"><label for="od-pass-confirm">Confirm new password</label><input id="od-pass-confirm" type="password" autocomplete="new-password" minlength="8" required></div></div>
              <button class="t-btn t-btn--secondary" type="submit">Update password</button><p class="hint" id="od-pass-m" role="status"></p>
            </form>
            <div class="db-ch od-sub"><h2>Two-step verification</h2></div>
            <a class="btn btn-line" id="od-security-link" href="/account-security/organizer">Manage two-step verification</a>
          </section>
          </div>
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
    <div class="inp" id="od-gs-wrap" hidden><label for="od-gs">Reserved seat</label><select id="od-gs"><option value="">Choose an available seat</option></select><small>The seat is held and assigned when the complimentary ticket is issued.</small></div>
    <button class="btn btn-hot" type="submit">Send free ticket</button><p class="hint" id="od-comp-m" role="status"></p>
  </form>
</dialog>

<!-- ACCOUNT -->
@endverbatim
