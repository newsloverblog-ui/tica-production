@verbatim
<section class="page" data-page="how">
  <div class="wrap x-hero">
    <div>
      <h1 class="x-h1">From idea<br>to full house.</h1>
      <p class="x-lead">Create your event, sell tickets and welcome people at the door, all from one place. Going out instead? Getting your ticket takes a few steps.</p>
      <div class="btns"><a class="btn btn-hot" href="/create">Create an event</a><a class="btn btn-line" href="/discover">Discover events</a></div>
    </div>
    <div class="x-collage">
      <img class="ph a" src="https://images.unsplash.com/photo-1690013429722-87852aae164b?auto=format&fit=crop&w=900&q=72" alt="A performer on a lit stage in front of a large crowd">
      <img class="ph b" loading="lazy" src="https://images.unsplash.com/photo-1740761026455-5af64649899e?auto=format&fit=crop&w=600&q=72" alt="A speaker on stage in front of an audience">
      <div class="float x-f"><span>Instant e-tickets</span><b>QR ready at the gate</b></div>
    </div>
  </div>

  <div class="tint"><div class="wrap x-sec x-journey">
    <div class="x-side">
      <h2 class="x-h2">Your journey into TICAKulture</h2>
      <p class="x-sub">Simple, fast and clear, whichever side of the gate you're on.</p>
      <div class="x-seg" role="group" aria-label="Choose your journey">
        <button type="button" data-j="org" aria-pressed="true">I'm hosting</button>
        <button type="button" data-j="att" aria-pressed="false">I'm attending</button>
      </div>
    </div>
    <div>
      <ol class="x-line" data-jp="org">
        <li><b>Join the movement</b><span>Sign up as a KultureCreator. It's free to get started.</span></li>
        <li><b>Create kulture</b><span>Add your flyer, dates and venue, then set your ticket types.</span></li>
        <li><b>Connect and celebrate</b><span>Share your link, sell tickets and welcome everyone with QR check-in.</span></li>
        <li><b>Get rewarded</b><span>Follow every sale as it happens and receive fast payouts.</span></li>
      </ol>
      <ol class="x-line" data-jp="att" hidden>
        <li><b>Find your event</b><span>Search by city, category or date, or browse what's popular.</span></li>
        <li><b>Choose your tickets</b><span>Pick your ticket types and add a name for each one.</span></li>
        <li><b>Pay securely</b><span>Check out with Paystack using card, bank transfer or USSD.</span></li>
        <li><b>Show it at the gate</b><span>Your e-ticket appears instantly and goes to your email.</span></li>
      </ol>
    </div>
  </div></div>

  <div class="wrap x-sec">
    <div class="x-head nz"><h2 class="x-h2">Everything your event needs</h2><p class="x-sub">One place for the work before, during and after your event.</p></div>
    <div class="x-bento">
      <article class="bn dark s7 nz">
        <div><h3>Sell every kind of ticket</h3><p>Free, regular, VIP or table for six. Name them, price them and publish.</p></div>
        <div class="bn-ui"><div><span>Regular</span><b>₦5,000</b></div><div><span>VIP</span><b>₦15,000</b></div><div><span>Table for six</span><b>₦100,000</b></div><small>Current ticket types</small></div>
      </article>
      <article class="bn lite s5 nz">
        <div><h3>Know who's in</h3><p>Scan tickets at the gate and watch your check-ins climb.</p></div>
        <div class="bn-num"><b>311</b><span>guests checked in today</span><div class="bar"><i style="width:59%"></i></div></div>
      </article>
      <article class="bn warm s5 nz">
        <div><h3>Get paid fast</h3><p>Follow every sale in one dashboard and receive fast payouts.</p></div>
        <div class="bn-num"><b>₦1.84M</b><span>gross ticket revenue</span></div>
      </article>
      <article class="bn pic s7 nz">
        <img loading="lazy" src="https://images.unsplash.com/photo-1711743658461-38c875d37c8e?auto=format&fit=crop&w=1000&q=72" alt="">
        <div class="bn-cap"><h3>Share one link everywhere</h3><p>Your event gets its own page, ready for WhatsApp, Instagram and anywhere your people are.</p></div>
      </article>
    </div>
  </div>

@endverbatim
@php($tsFaqs = \App\Models\FAQ::query()->where('language_id', \App\Models\Language::query()->where('is_default', 1)->value('id'))->orderBy('serial_number')->get(['question', 'answer']))
  <div class="tint" id="faq"@if ($tsFaqs->isEmpty()) style="display:none"@endif><div class="wrap x-sec x-faq">
    <div class="x-side">
      <h2 class="x-h2">Frequently asked questions</h2>
      <p class="x-sub">Can't find your answer? We're happy to help.</p>
      <a class="btn btn-line" href="/contact">Contact us</a>
    </div>
    <div class="faq">
@foreach ($tsFaqs as $tsI => $tsFaq)
      <details @if ($tsI === 0) open @endif><summary>{{ $tsFaq->question }}</summary><p>{!! \App\Support\TicaSite::richText($tsFaq->answer) !!}</p></details>
@endforeach
    </div>
@verbatim
  </div></div>
</section>

<!-- PRICING -->
@endverbatim
