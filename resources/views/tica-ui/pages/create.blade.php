@verbatim
<section class="page" data-page="create">
  <div class="ce-shell" id="ce-shell"><div class="ce-side" id="ce-side"></div><div class="ce-shell-main">
  <div class="tint full"><div class="wrap pagehead creategrid">
    <form id="create-form" class="panel ce" novalidate>
      <h1 class="display">Create your event</h1>
      <p class="lead">Fill this in and watch your event card build itself. You can change anything later.</p>
      <ol class="ce-steps" aria-label="Event setup steps"><li><a href="#ce-basics" data-ce="ce-basics">1. Details</a></li><li><a href="#ce-where" data-ce="ce-where">2. Type &amp; location</a></li><li><a href="#ce-tix" data-ce="ce-tix">3. Tickets &amp; fees</a></li><li><a href="#ce-more" data-ce="ce-more">4. Media &amp; settings</a></li><li><a href="#ce-review" data-ce="ce-review">5. Preview</a></li></ol>
      <p class="ce-progress" id="ce-progress" aria-live="polite">Step 1 of 5: Details</p>

      <fieldset id="ce-basics"><legend>The basics</legend>
        <section class="ce-admin" id="ce-admin" hidden aria-labelledby="ce-admin-title">
          <div class="ce-admin-head"><h2 id="ce-admin-title">Admin options</h2></div>
          <div class="ce-admin-grid">
            <div class="ce-admin-owner">
              <label class="ce-admin-choice" for="c-assign-owner">
                <input class="ce-admin-check" type="checkbox" id="c-assign-owner" aria-controls="c-owner-picker" aria-expanded="false">
                <span><b>Assign to an organizer</b><small>They manage tickets, attendees and payouts.</small></span>
              </label>
              <div class="inp ce-owner-picker" id="c-owner-picker" hidden>
                <label for="c-owner">Organizer</label>
                <select id="c-owner" name="organizer_id" aria-label="Organizer"><option value="">Choose an organizer</option></select>
              </div>
            </div>
            <label class="ce-admin-choice" for="c-featured">
              <input class="ce-admin-check" type="checkbox" id="c-featured" name="is_featured">
              <span><b>Feature this event</b><small>Show it in approved featured placements.</small></span>
            </label>
            <div class="inp ce-admin-edit" hidden><label for="c-status">Status</label><select id="c-status"><option value="live">Live, people can buy tickets</option><option value="draft">Hidden, saved as a draft</option></select></div>
          </div>
          <div class="ce-admin-danger ce-admin-edit" hidden><span><b>Delete this event</b><small>Removes the event, its tickets and its page for good.</small></span><button class="btn btn-line ce-del" type="button" id="ce-delete">Delete event</button></div>
        </section>
        <div class="inp"><label for="c-name">Event name</label><input id="c-name" name="name" type="text" maxlength="255" placeholder="Give it a name people will remember"></div>
        <div class="two">
          <div class="inp"><label for="c-cat">Category</label><select id="c-cat" name="category_id"><option>Party and nightlife</option><option>Concert or show</option><option>Campus</option><option>Conference or class</option><option>Faith</option><option>Sport and fitness</option><option>Art and food</option></select></div>
          <div class="inp"><label for="c-age">Who can come</label><select id="c-age" name="age_restriction"><option value="">All ages</option><option>18+ only, with ID</option><option>16+ only</option></select></div>
        </div>
        <div class="inp"><label for="c-desc">Description</label><textarea id="c-desc" name="description" rows="3" placeholder="What should people expect? Line-up, dress code, what's included."></textarea></div>
      </fieldset>

      <fieldset id="ce-where"><legend>Where it happens</legend>
        <div class="ce-mode" role="radiogroup" aria-label="Event type">
          <label><input type="radio" name="c-mode" value="In person" checked><span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22s7-6.5 7-12a7 7 0 0 0-14 0c0 5.5 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/></svg><b>In person</b><small>At a venue</small></span></label>
          <label><input type="radio" name="c-mode" value="Online"><span><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="12" rx="2"/><path d="M8 21h8M12 17v4"/></svg><b>Online</b><small>Zoom, Meet, live stream</small></span></label>
          <label><input type="radio" name="c-mode" value="Hybrid"><span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 20s5-4.6 5-8.5a5 5 0 0 0-10 0C3 15.4 8 20 8 20z"/><rect x="14" y="4" width="8" height="6" rx="1"/></svg><b>Both</b><small>Venue and live stream</small></span></label>
        </div>
        <div class="inp"><label for="c-timezone">Event timezone</label><select id="c-timezone" name="timezone"><option value="Africa/Lagos">West Africa Time · Lagos</option><option value="Africa/Accra">Greenwich Mean Time · Accra</option><option value="Africa/Nairobi">East Africa Time · Nairobi</option><option value="Africa/Johannesburg">South Africa Time · Johannesburg</option><option value="Europe/London">United Kingdom · London</option><option value="America/New_York">Eastern Time · New York</option><option value="America/Toronto">Eastern Time · Toronto</option><option value="Asia/Dubai">Gulf Standard Time · Dubai</option></select><small>Times on tickets and event messages use this timezone.</small></div>
        <div class="ce-one">
          <div class="two">
            <div class="inp"><label for="c-date">Date</label><input id="c-date" name="start_date" type="date"></div>
            <div class="inp"><label for="c-time">Start time</label><input id="c-time" name="start_time" type="time"></div>
          </div>
          <div class="two">
            <div class="inp"><label for="c-edate">End date</label><input id="c-edate" name="end_date" type="date"></div>
            <div class="inp"><label for="c-etime">End time</label><input id="c-etime" name="end_time" type="time"></div>
          </div>
        </div>
        <ul class="od-sw ce-sw"><li><span><b>Runs on more than one date</b><small>Add each date. Buyers choose one at checkout.</small></span><input class="ck-sw" type="checkbox" id="c-multi" aria-label="Runs on more than one date"></li></ul>
        <div class="ce-dates" id="ce-dates" hidden></div>
        <button class="ce-add" type="button" id="ce-add-date" hidden>+ Add a date</button>
        <div class="two">
          <div class="inp"><label for="c-country">Country</label><select id="c-country" name="country"></select></div>
          <div class="inp"><label for="c-cur">Currency</label><select id="c-cur" name="currency"></select></div>
        </div>
        <p class="hint" id="c-cur-note">Buyers pay in this currency. It's set from the country, and you can change it.</p>
        <div class="ce-in">
          <div class="two">
            <div class="inp"><label for="c-venue">Venue</label><input id="c-venue" name="address" type="text" placeholder="Where is it happening?"></div>
            <div class="inp"><label for="c-city">City</label><input id="c-city" name="city" type="text" placeholder="Owerri"></div>
          </div>
          <div class="inp"><label for="c-state">State or region</label><input id="c-state" name="state" type="text" placeholder="Imo"></div>
        </div>
        <div class="ce-on" hidden>
          <div class="two">
            <div class="inp"><label for="c-plat">Platform</label><select id="c-plat"><option>Zoom</option><option>Google Meet</option><option>YouTube Live</option><option>Instagram Live</option><option>Other link</option></select></div>
            <div class="inp"><label for="c-rev">Show the join link</label><select id="c-rev" name="join_reveal_minutes"><option value="15">15 minutes before</option><option value="30">30 minutes before</option><option value="60">1 hour before</option><option value="0">Right after purchase</option></select></div>
          </div>
          <div class="inp"><label for="c-link">Join link</label><input id="c-link" name="online_join_url" type="url" placeholder="https://zoom.us/j/..."></div>
          <p class="hint">Only ticket holders see the link, on their ticket and by email. You can add it later.</p>
        </div>
      </fieldset>

      <fieldset id="ce-tix"><legend>Your tickets</legend>
        <div class="ce-entry-state" aria-hidden="true">
          <input type="radio" name="c-entry" value="ticketing" checked tabindex="-1">
          <input type="radio" name="c-entry" value="corporate" tabindex="-1">
        </div>
        <div class="ce-sell">
          <datalist id="ce-ticket-presets"><option value="Regular"></option><option value="VIP"></option><option value="VVIP"></option><option value="Early Bird"></option><option value="Student"></option><option value="Complimentary"></option></datalist>
          <div class="ce-tiers" id="ce-tiers">
            <div class="ce-tier ce-ticket-row"><div class="inp"><label for="c-tier">Ticket name</label><input id="c-tier" type="text" value="Regular" placeholder="Regular" list="ce-ticket-presets"></div><div class="inp"><label for="c-price">Price (₦)</label><input id="c-price" type="number" min="0" step="500" value="0" placeholder="e.g. 5000" inputmode="decimal"><small>Leave at 0 for a free event.</small></div><div class="inp"><label for="c-qty">Quantity</label><input id="c-qty" type="number" min="1" step="1" placeholder="Unlimited" inputmode="numeric"><small>Leave empty for unlimited tickets.</small></div></div>
          </div>
          <button class="ce-add" type="button" id="ce-add">+ Add a ticket type</button>
          <p class="hint">Use 0 for free tickets. You can add early bird, VIP or table tickets, and set when each one goes on sale.</p>
        </div>
        <section class="ce-registration-addon" aria-labelledby="ce-registration-title">
          <div class="ce-entry-choice">
            <label class="ce-choice-row" for="c-use-registration">
              <input id="c-use-registration" name="use_registration" type="checkbox" aria-controls="ce-registration-fields" aria-expanded="false">
              <span><b id="ce-registration-title">Add a registration form</b><small>Collect extra attendee details alongside these tickets.</small></span>
            </label>
          </div>
          <div class="ce-reg ce-disclosure" id="ce-registration-fields" hidden>
            <div class="ce-reg-empty" id="ce-reg-empty" hidden>
              <div><b>No registration forms yet</b><p>Create a reusable form first, then return here to attach it to this event.</p></div>
              <a class="btn btn-line" href="/organizer/forms">Create a form</a>
            </div>
            <div class="inp ce-reg-pick" id="ce-reg-pick" hidden>
              <label for="c-reg-form">Registration form</label>
              <select id="c-reg-form" name="registration_form_id"><option value="">Select a saved form</option></select>
              <div class="ce-reg-pick-foot"><small id="c-reg-form-note">Choose the questions attendees complete with their ticket.</small><a href="/organizer/forms">Manage forms</a></div>
            </div>
          </div>
        </section>
        <section class="ce-subsection" id="ce-fee" aria-labelledby="ce-fee-title">
        <h2 id="ce-fee-title">Fees &amp; payout</h2>
        <p class="ce-q">Who pays the <span data-fee-pct>6%</span> booking fee?</p>
        <div class="ce-payer" role="radiogroup" aria-label="Who pays the booking fee">
          <label><input type="radio" name="c-payer" value="buyer" checked><span><b>Buyers pay it</b><small id="ce-ex-b">Buyer pays ₦5,300. You get ₦5,000.</small></span></label>
          <label><input type="radio" name="c-payer" value="me"><span><b>I'll include it</b><small id="ce-ex-m">Buyer pays ₦5,000. You get ₦4,700.</small></span></label>
        </div>
        <div class="ce-bank" id="ce-bank"><span><b>Payout account</b><small>Not added yet. Add it before your event so you're paid on time.</small></span><a class="btn btn-line" href="/dashboard/payouts">Add bank account</a></div>
        </section>
      </fieldset>

      <fieldset id="ce-more"><legend>Media &amp; settings</legend>
        <div class="two">
          <div class="inp"><label for="c-vis">Who can find it</label><select id="c-vis" name="visibility"><option value="public">Public, on Discover</option><option value="unlisted">Link only</option><option value="private">Private, by invite</option></select></div>
          <div class="inp"><label for="c-ref">Refunds</label><select id="c-ref" name="refund_policy"><option value="">TICA standard</option><option>Up to 7 days before</option><option>Up to 48 hours before</option></select></div>
        </div>
        <ul class="od-sw ce-sw">
          <li><span><b>Show tickets left</b></span><input class="ck-sw" type="checkbox" id="c-left" checked aria-label="Show tickets left"></li>
          <li><span><b>Waitlist when sold out</b></span><input class="ck-sw" type="checkbox" id="c-wait" checked aria-label="Waitlist when sold out"></li>
          <li><span><b>Let buyers transfer tickets</b><small>Buyers can pass an unused ticket to someone else before the event.</small></span><input class="ck-sw" type="checkbox" id="c-trans" checked aria-label="Let buyers transfer tickets"></li>
          <li><span><b>Ask each attendee a question</b><small>For example, T-shirt size or dietary needs</small></span><input class="ck-sw" type="checkbox" id="c-askq" aria-label="Ask each attendee a question"></li>
          <li><span><b>Show a countdown</b><small>On your event page, counting down to the start</small></span><input class="ck-sw" type="checkbox" id="c-cd" checked aria-label="Show a countdown"></li>
        </ul>
        <div class="inp ce-qx" hidden><label for="c-question">Your question</label><input id="c-question" name="attendee_question" type="text" maxlength="200" placeholder="What's your T-shirt size?"></div>
        <div class="inp"><label for="c-flyer">Flyer</label><input id="c-flyer" type="file" accept="image/*"></div>
        <p class="hint">Optional. Upload a JPG or PNG up to 5 MB, or publish with TICA's neutral event artwork.</p>
        <section class="ce-programme" aria-labelledby="ce-programme-title">
          <label class="ce-choice-row ce-programme-toggle" for="c-programme">
            <input id="c-programme" name="programme_enabled" type="checkbox" aria-controls="ce-programme-fields" aria-expanded="false">
            <span><b id="ce-programme-title">Add speakers or a line-up</b><small>Introduce the people appearing and publish a clear event agenda.</small></span>
          </label>
          <div class="ce-programme-fields" id="ce-programme-fields" hidden>
            <div class="ce-programme-kind" role="radiogroup" aria-label="Programme type">
              <label><input type="radio" name="c-programme-type" value="speaker" checked><span><b>Speakers</b><small>Talks, panels, hosts and moderators</small></span></label>
              <label><input type="radio" name="c-programme-type" value="artist"><span><b>Line-up</b><small>Artists, DJs and performers</small></span></label>
            </div>
            <div class="ce-programme-head"><div><h2 id="ce-people-title">Featured speakers</h2><p>Add a portrait, role and the topic they will cover.</p></div><button class="btn btn-line" id="ce-add-person" type="button">+ Add speaker</button></div>
            <div class="ce-people" id="ce-people"></div>
            <div class="ce-programme-head ce-agenda-head"><div><h2>Agenda</h2><p>Optional. Give guests a simple running order for the day.</p></div><button class="btn btn-line" id="ce-add-agenda" type="button">+ Add agenda item</button></div>
            <div class="ce-agenda-editor" id="ce-agenda-editor"></div>
          </div>
        </section>
        <div class="two">
          <div class="inp"><label for="c-seo">Search description</label><input id="c-seo" name="meta_description" type="text" maxlength="300" placeholder="One line for Google and link previews"></div>
          <div class="inp"><label for="c-keys">Search keywords</label><input id="c-keys" name="meta_keywords" type="text" maxlength="300" placeholder="afrobeats, owerri, concert"></div>
        </div>
      </fieldset>
      <fieldset id="ce-review"><legend>Preview and publish</legend>
        <dl class="ce-sum" id="ce-sum"></dl>
        <p class="hint">Publish to put it on sale now, or save a draft and finish later.</p>
      </fieldset>
      <p class="ce-feedback" id="c-msg" role="status" aria-live="polite"></p>
      <div class="ce-go ce-nav"><button class="btn btn-line" type="button" id="ce-back">Back</button><button class="btn btn-hot" type="button" id="ce-next">Continue</button><button class="btn btn-line" type="button" id="ce-draft" hidden>Save draft</button><button class="btn btn-hot" type="submit" id="ce-publish" hidden>Publish event</button></div>
    </form>
    <aside class="preview" aria-label="Preview of your event card">
      <p class="previewlabel">Live preview</p>
      <article class="card">
        <div>
          <div class="flyer" id="p-flyer"><div class="f pblank"><div class="t" id="p-art">Your flyer here</div></div></div>
        </div>
        <div class="cardbody">
          <span class="tag ce-tag" id="p-mode" hidden>Online</span>
          <p class="cdate"><span id="p-day">--</span> <span id="p-mon">Date</span></p><h3 id="p-name">Your event name</h3>
          <p class="meta" id="p-meta">Time and venue</p>
          <div class="buy"><div class="price" id="p-price" hidden></div><span class="cdm" id="p-left"></span></div>
          <p class="ce-pf" id="p-fee" hidden></p>
        </div>
      </article>
    </aside>
  </div></div>
  <section class="ce-success" id="ce-success" role="dialog" aria-modal="true" aria-labelledby="ce-success-title" tabindex="-1" hidden>
    <div class="ce-success-card">
      <span class="ce-success-mark" aria-hidden="true">✓</span>
      <p class="ce-success-kicker">Event created</p>
      <h2 id="ce-success-title">Your event is ready</h2>
      <p id="ce-success-copy">The event was saved successfully.</p>
      <div class="ce-success-actions">
        <a class="btn btn-hot" id="ce-success-manage" href="/dashboard/events">Manage event</a>
        <a class="btn btn-line" id="ce-success-view" href="#" target="_blank" rel="noopener">View event page</a>
      </div>
    </div>
  </section>
  </div></div>
</section>

<!-- POLICIES -->
@endverbatim
