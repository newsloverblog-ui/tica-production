@verbatim
<section class="page" data-page="discover">
  <div class="wrap pagehead">
    <h1 class="display">Discover events</h1>
    <p class="lead">Find an event in Owerri, Port Harcourt, Abuja and beyond.</p>
  </div>
  <div class="cf" id="cf" role="group" aria-roledescription="carousel" aria-label="Featured events">
    <div class="cf-stage" id="cf-stage"></div>
    <button class="cf-btn prev" type="button" id="cf-prev" aria-label="Previous event"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg></button>
    <button class="cf-btn next" type="button" id="cf-next" aria-label="Next event"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg></button>
    <p class="sr" id="cf-live" aria-live="polite"></p>
  </div>
  <div class="tint"><div class="wrap sec tight">
    <div class="filters" role="group" aria-label="Filter events">
      <div class="pick wide"><label for="d-q">Search</label><input id="d-q" type="search" placeholder="Artist, event or organiser" autocomplete="off"></div>
      <button class="f-btn" type="button" id="f-btn" aria-haspopup="dialog" aria-controls="f-more"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg><span>Filters</span><i id="f-n" hidden></i></button>
      <div class="f-more" id="f-more" role="dialog" aria-modal="true" aria-labelledby="f-h"><div class="f-head"><h2 id="f-h">Filter events</h2><button class="f-x" type="button" id="f-x" aria-label="Close filters"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div><div class="f-grid">
      <div class="pick"><label for="d-country">Country</label><select id="d-country" data-country><option value="Nigeria">Nigeria</option><option value="Ghana">Ghana</option><option value="Kenya">Kenya</option><option value="South Africa">South Africa</option><option value="United Kingdom">United Kingdom</option><option value="United States">United States</option><option value="Online">Online</option></select></div><div class="pick"><label for="d-where">City</label><select id="d-where" data-city><option value="all">All cities</option></select></div>
      <div class="pick"><label for="d-cat">Category</label><select id="d-cat" class="catselect"></select></div>
      <div class="pick"><label for="d-price">Price</label><select id="d-price"><option value="all">Any price</option><option value="free">Free</option><option value="low">Under ₦5,000</option><option value="high">₦5,000 and above</option></select></div>
      <div class="pick"><label for="d-sort">Sort by</label><select id="d-sort"><option value="soon">Soonest first</option><option value="low">Price, low to high</option><option value="high">Price, high to low</option></select></div>
      <div class="pick pick-date"><label for="d-date">Date</label><input id="d-date" type="date" aria-label="Choose event date"><input id="d-month" type="hidden" value="all"><input id="d-day" type="hidden" value="all"></div><button class="d-search" id="d-submit" type="button"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4 4"/></svg><span>Search</span></button>
      </div><div class="f-foot"><button class="btn btn-line" type="button" id="f-clear">Clear all</button><button class="btn btn-hot" type="button" id="f-apply">Show results</button></div></div>
    </div>
    <div class="f-active" id="f-active"></div><div class="f-bd" id="f-bd" hidden></div>
    <div class="head small"><h2>All events</h2><p class="count" id="count" aria-live="polite"></p></div>
    <div class="rows" id="rows"></div>
    <div class="empty" id="empty" hidden><p>No events match those filters.</p><button class="btn btn-line" type="button" id="reset">Clear filters</button></div>
    
  </div></div>
</section>

<!-- EVENT -->
@endverbatim
