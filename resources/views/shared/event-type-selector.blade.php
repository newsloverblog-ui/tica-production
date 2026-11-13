<style>
  .tica-type-shell { max-width: 1040px; margin: 18px auto 0; }
  .tica-type-hero { max-width: 680px; }
  .tica-type-hero h1 { margin: 0 0 10px; color: #0b2447; font-size: clamp(30px, 4vw, 44px); font-weight: 800; letter-spacing: -.035em; }
  .tica-type-hero p { margin: 0; color: #667085; font-size: 15px; line-height: 1.65; }
  .tica-type-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; margin-top: 30px; }
  .tica-type-card { position: relative; display: flex; min-height: 300px; overflow: hidden; padding: 28px; border: 1px solid #dfe7f0; border-radius: 22px; background: #fff; box-shadow: 0 12px 34px rgba(11, 36, 71, .06); text-decoration: none !important; transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease; }
  .tica-type-card:hover { border-color: #9bb8e5; box-shadow: 0 18px 42px rgba(11, 36, 71, .11); transform: translateY(-3px); }
  .tica-type-card:focus-visible { outline: 3px solid rgba(31, 95, 204, .28); outline-offset: 3px; }
  .tica-type-content { position: relative; z-index: 2; display: flex; flex-direction: column; align-items: flex-start; width: 100%; }
  .tica-type-icon { display: grid; width: 54px; height: 54px; place-items: center; border-radius: 15px; background: #eef3fd; color: #1f5fcc; font-size: 22px; }
  .tica-type-card h2 { margin: auto 0 9px; color: #0b2447; font-size: 27px; font-weight: 800; letter-spacing: -.025em; }
  .tica-type-card p { max-width: 40ch; margin: 0; color: #667085; line-height: 1.6; }
  .tica-type-action { display: inline-flex; align-items: center; gap: 8px; margin-top: 20px; color: #1f5fcc; font-weight: 750; }
  .tica-type-art { position: absolute; right: -42px; top: -54px; width: 210px; height: 210px; border-radius: 50%; background: radial-gradient(circle at 38% 40%, rgba(249, 115, 22, .18), rgba(31, 95, 204, .07) 56%, transparent 57%); }
  .tica-type-card.online .tica-type-art { background: radial-gradient(circle at 38% 40%, rgba(31, 95, 204, .20), rgba(249, 115, 22, .06) 56%, transparent 57%); }
  @media (max-width: 720px) { .tica-type-shell { margin-top: 4px; } .tica-type-grid { grid-template-columns: 1fr; } .tica-type-card { min-height: 250px; } }
  @media (prefers-reduced-motion: reduce) { .tica-type-card { transition: none; } }
</style>

<section class="tica-type-shell" aria-labelledby="tica-event-type-title">
  <header class="tica-type-hero">
    <h1 id="tica-event-type-title">{{ __('What kind of event is it?') }}</h1>
    <p>{{ __('Choose the event format first. The next screen keeps the same guided setup for event details, access, publishing and guest management.') }}</p>
  </header>

  <section class="tica-type-grid" aria-label="{{ __('Event formats') }}">
    <a class="tica-type-card" href="{{ $venueUrl }}">
      <span class="tica-type-art" aria-hidden="true"></span>
      <span class="tica-type-content">
        <span class="tica-type-icon"><i class="fal fa-map-marker-alt" aria-hidden="true"></i></span>
        <h2>{{ __('In-person event') }}</h2>
        <p>{{ __('Create an event at a venue with location details, ticket options, guest lists and gate check-in.') }}</p>
        <span class="tica-type-action">{{ __('Create in-person event') }} <i class="fal fa-arrow-right" aria-hidden="true"></i></span>
      </span>
    </a>

    <a class="tica-type-card online" href="{{ $onlineUrl }}">
      <span class="tica-type-art" aria-hidden="true"></span>
      <span class="tica-type-content">
        <span class="tica-type-icon"><i class="fal fa-broadcast-tower" aria-hidden="true"></i></span>
        <h2>{{ __('Online event') }}</h2>
        <p>{{ __('Host a virtual event with a private attendee link, capacity controls and ticket settings.') }}</p>
        <span class="tica-type-action">{{ __('Create online event') }} <i class="fal fa-arrow-right" aria-hidden="true"></i></span>
      </span>
    </a>
  </section>
</section>
