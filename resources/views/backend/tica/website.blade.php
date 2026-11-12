@extends('backend.layout')

@php
  $home = $site['home'];
  $img = fn ($path) => \App\Support\TicaSite::imageUrl($path);
  $intro = [
    'home' => __('The first page people see. Change the headline, photos and which sections show.'),
    'menu' => __('The links across the top of every page, and in the mobile menu.'),
    'footer' => __('The bottom of every page: your line, link columns and copyright.'),
    'contact' => __('Phone, emails and social links. They show on the Contact page and in the footer.'),
  ];
@endphp

@section('content')
  <header class="ta-head tw-head">
    <div>
      <p class="ta-kicker">{{ __('Website') }}</p>
      <h1>{{ __('Your website') }}</h1>
      <p>{{ __('Everything here changes the public TICA site straight away. Leave a field as it is to keep the design\'s original content.') }}</p>
    </div>
    <a class="btn btn-secondary" href="{{ url('/') }}" target="_blank" rel="noopener"><i class="fal fa-external-link" aria-hidden="true"></i>{{ __('View the site') }}</a>
  </header>

  @if ($errors->any())
    <div class="ta-alert bad" role="alert"><b>{{ __('Nothing was saved.') }}</b><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
  @endif

  <nav class="tw-tabs" aria-label="{{ __('Website sections') }}">
    @foreach ($tabs as $key => [$label])
      <a href="{{ route('admin.tica.website', ['tab' => $key]) }}" @if ($tab === $key) aria-current="page" @endif>{{ __($label) }}</a>
    @endforeach
  </nav>

  <div class="tw-grid">
    <div class="tw-main">
      <p class="tw-intro">{{ $intro[$tab] }}</p>

      @if ($tab === 'home')
        <form method="POST" action="{{ route('admin.tica.website.update', 'home') }}" enctype="multipart/form-data" class="ta-forms">
          @csrf
          <section class="ta-card">
            <div class="ta-card-h"><span class="ta-ic orange" aria-hidden="true"><i class="fal fa-image"></i></span><div><h2>{{ __('Top of the page') }}</h2><p>{{ __('The big headline over the photos, with the event search under it.') }}</p></div></div>
            <div class="ta-field"><label for="tw-ht">{{ __('Headline') }}</label><textarea class="form-control tw-short" id="tw-ht" name="hero_title" rows="2" maxlength="120" required>{{ old('hero_title', $home['hero_title']) }}</textarea><small class="tw-hint">{{ __('Press Enter to start a new line.') }}</small></div>
            <div class="ta-field"><label for="tw-hx">{{ __('Line under the headline') }}</label><input class="form-control" id="tw-hx" name="hero_text" maxlength="240" value="{{ old('hero_text', $home['hero_text']) }}"></div>
            <div class="ta-field">
              <span class="tw-l">{{ __('Background photos') }}</span>
              <small class="tw-hint">{{ __('They fade from one to the next. Use wide photos, at least 1800 pixels across.') }}</small>
              <div class="tw-photos" data-rows="photos">
                @foreach ($home['hero_images'] as $i => $path)
                  <div class="tw-photo" data-row>
                    <img src="{{ $img($path) }}" alt="">
                    <input type="hidden" name="hero_images[]" value="{{ $path }}">
                    <button type="button" class="tw-x" data-remove aria-label="{{ __('Remove this photo') }}"><i class="fal fa-times" aria-hidden="true"></i></button>
                  </div>
                @endforeach
                <label class="tw-photo tw-add-photo"><input type="file" name="hero_uploads[]" accept="image/jpeg,image/png,image/webp" multiple><i class="fal fa-plus" aria-hidden="true"></i><span>{{ __('Add photos') }}</span></label>
              </div>
            </div>
          </section>

          <section class="ta-card">
            <div class="ta-card-h"><span class="ta-ic blue" aria-hidden="true"><i class="fal fa-layer-group"></i></span><div><h2>{{ __('Sections on the page') }}</h2><p>{{ __('Switch off a section to hide it. The order follows the design.') }}</p></div></div>
            <ul class="ta-list">
              @foreach ($sections as $key => $label)
                <li><div><label for="tw-s-{{ $key }}">{{ __($label) }}</label></div><input class="ta-sw" id="tw-s-{{ $key }}" type="checkbox" name="sections[{{ $key }}]" value="1" @checked($home['sections'][$key] ?? true)></li>
              @endforeach
            </ul>
          </section>

          <section class="ta-card">
            <div class="ta-card-h"><span class="ta-ic purple" aria-hidden="true"><i class="fal fa-bullhorn"></i></span><div><h2>{{ __('For organisers') }}</h2><p>{{ __('The section inviting people to sell tickets on TICA.') }}</p></div></div>
            <div class="ta-field"><label for="tw-ot">{{ __('Heading') }}</label><input class="form-control" id="tw-ot" name="organisers_title" maxlength="120" required value="{{ old('organisers_title', $home['organisers_title']) }}"></div>
            <div class="ta-field"><label for="tw-ox">{{ __('Text') }}</label><textarea class="form-control" id="tw-ox" name="organisers_text" rows="3" maxlength="400">{{ old('organisers_text', $home['organisers_text']) }}</textarea></div>
            <span class="tw-l">{{ __('Points') }}</span>
            <div class="tw-rows" data-rows="op" data-max="4">
              @foreach ($home['organisers_points'] as $i => $row)
                <div class="tw-row two" data-row><input class="form-control" name="organisers_points[{{ $i }}][title]" maxlength="60" value="{{ $row['title'] }}" aria-label="{{ __('Point title') }}" placeholder="{{ __('Title') }}"><input class="form-control" name="organisers_points[{{ $i }}][text]" maxlength="140" value="{{ $row['text'] }}" aria-label="{{ __('Point text') }}" placeholder="{{ __('One short sentence') }}"><button type="button" class="tw-x" data-remove aria-label="{{ __('Remove') }}"><i class="fal fa-times" aria-hidden="true"></i></button></div>
              @endforeach
            </div>
            <template data-for="op"><div class="tw-row two" data-row><input class="form-control" name="organisers_points[__i__][title]" maxlength="60" aria-label="{{ __('Point title') }}" placeholder="{{ __('Title') }}"><input class="form-control" name="organisers_points[__i__][text]" maxlength="140" aria-label="{{ __('Point text') }}" placeholder="{{ __('One short sentence') }}"><button type="button" class="tw-x" data-remove aria-label="{{ __('Remove') }}"><i class="fal fa-times" aria-hidden="true"></i></button></div></template>
            <button type="button" class="tw-add" data-add="op"><i class="fal fa-plus" aria-hidden="true"></i>{{ __('Add a point') }}</button>
          </section>

          <section class="ta-card">
            <div class="ta-card-h"><span class="ta-ic green" aria-hidden="true"><i class="fal fa-ticket-alt"></i></span><div><h2>{{ __('How tickets work') }}</h2><p>{{ __('The steps a buyer follows, from choosing to the gate.') }}</p></div></div>
            <div class="ta-field"><label for="tw-et">{{ __('Heading') }}</label><input class="form-control" id="tw-et" name="eticket_title" maxlength="120" required value="{{ old('eticket_title', $home['eticket_title']) }}"></div>
            <span class="tw-l">{{ __('Steps') }}</span>
            <div class="tw-rows" data-rows="es" data-max="4">
              @foreach ($home['eticket_steps'] as $i => $row)
                <div class="tw-row two" data-row><input class="form-control" name="eticket_steps[{{ $i }}][title]" maxlength="60" value="{{ $row['title'] }}" aria-label="{{ __('Step title') }}" placeholder="{{ __('Title') }}"><input class="form-control" name="eticket_steps[{{ $i }}][text]" maxlength="140" value="{{ $row['text'] }}" aria-label="{{ __('Step text') }}" placeholder="{{ __('One short sentence') }}"><button type="button" class="tw-x" data-remove aria-label="{{ __('Remove') }}"><i class="fal fa-times" aria-hidden="true"></i></button></div>
              @endforeach
            </div>
            <template data-for="es"><div class="tw-row two" data-row><input class="form-control" name="eticket_steps[__i__][title]" maxlength="60" aria-label="{{ __('Step title') }}" placeholder="{{ __('Title') }}"><input class="form-control" name="eticket_steps[__i__][text]" maxlength="140" aria-label="{{ __('Step text') }}" placeholder="{{ __('One short sentence') }}"><button type="button" class="tw-x" data-remove aria-label="{{ __('Remove') }}"><i class="fal fa-times" aria-hidden="true"></i></button></div></template>
            <button type="button" class="tw-add" data-add="es"><i class="fal fa-plus" aria-hidden="true"></i>{{ __('Add a step') }}</button>
          </section>

          <section class="ta-card">
            <div class="ta-card-h"><span class="ta-ic orange" aria-hidden="true"><i class="fal fa-handshake"></i></span><div><h2>{{ __('Our partners') }}</h2><p>{{ __('Shown at the bottom of the home page. Add a logo, or a short name and full name.') }}</p></div></div>
            <div class="tw-rows" data-rows="pt" data-max="8">
              @foreach ($home['partners'] as $i => $row)
                <div class="tw-row three" data-row>
                  <input class="form-control" name="partners[{{ $i }}][short]" maxlength="12" value="{{ $row['short'] }}" aria-label="{{ __('Short name') }}" placeholder="{{ __('Short name') }}">
                  <input class="form-control" name="partners[{{ $i }}][name]" maxlength="60" value="{{ str_replace("\n", ' ', $row['name']) }}" aria-label="{{ __('Full name') }}" placeholder="{{ __('Full name') }}">
                  <label class="tw-logo">@if (!empty($row['logo']))<img src="{{ $img($row['logo']) }}" alt="">@else<i class="fal fa-image" aria-hidden="true"></i>@endif<span>{{ __('Logo') }}</span><input type="file" name="partner_logos[{{ $i }}]" accept="image/png,image/jpeg,image/webp"></label>
                  <input type="hidden" name="partners[{{ $i }}][logo]" value="{{ $row['logo'] }}">
                  <button type="button" class="tw-x" data-remove aria-label="{{ __('Remove') }}"><i class="fal fa-times" aria-hidden="true"></i></button>
                </div>
              @endforeach
            </div>
            <template data-for="pt"><div class="tw-row three" data-row><input class="form-control" name="partners[__i__][short]" maxlength="12" aria-label="{{ __('Short name') }}" placeholder="{{ __('Short name') }}"><input class="form-control" name="partners[__i__][name]" maxlength="60" aria-label="{{ __('Full name') }}" placeholder="{{ __('Full name') }}"><label class="tw-logo"><i class="fal fa-image" aria-hidden="true"></i><span>{{ __('Logo') }}</span><input type="file" name="partner_logos[__i__]" accept="image/png,image/jpeg,image/webp"></label><input type="hidden" name="partners[__i__][logo]" value=""><button type="button" class="tw-x" data-remove aria-label="{{ __('Remove') }}"><i class="fal fa-times" aria-hidden="true"></i></button></div></template>
            <button type="button" class="tw-add" data-add="pt"><i class="fal fa-plus" aria-hidden="true"></i>{{ __('Add a partner') }}</button>
          </section>
          @include('backend.tica.partials.website-save', ['area' => 'home'])
        </form>
      @endif

      @if ($tab === 'menu')
        <form method="POST" action="{{ route('admin.tica.website.update', 'menu') }}" class="ta-forms">
          @csrf
          <section class="ta-card">
            <div class="ta-card-h"><span class="ta-ic blue" aria-hidden="true"><i class="fal fa-bars"></i></span><div><h2>{{ __('Menu links') }}</h2><p>{{ __('Up to 7 links. Use the arrow to move a link up. Use a page on the site, like /about, or a full web address.') }}</p></div></div>
            <div class="tw-rows" data-rows="mn" data-max="7">
              @foreach ($site['menu'] as $i => $row)
                <div class="tw-row link" data-row><input class="form-control" name="links[{{ $i }}][label]" maxlength="30" value="{{ $row['label'] }}" aria-label="{{ __('Link text') }}" placeholder="{{ __('Link text') }}"><input class="form-control" name="links[{{ $i }}][url]" maxlength="300" value="{{ $row['url'] }}" aria-label="{{ __('Goes to') }}" placeholder="/about"><button type="button" class="tw-up" data-up aria-label="{{ __('Move up') }}"><i class="fal fa-arrow-up" aria-hidden="true"></i></button><button type="button" class="tw-x" data-remove aria-label="{{ __('Remove') }}"><i class="fal fa-times" aria-hidden="true"></i></button></div>
              @endforeach
            </div>
            <template data-for="mn"><div class="tw-row link" data-row><input class="form-control" name="links[__i__][label]" maxlength="30" aria-label="{{ __('Link text') }}" placeholder="{{ __('Link text') }}"><input class="form-control" name="links[__i__][url]" maxlength="300" aria-label="{{ __('Goes to') }}" placeholder="/about"><button type="button" class="tw-up" data-up aria-label="{{ __('Move up') }}"><i class="fal fa-arrow-up" aria-hidden="true"></i></button><button type="button" class="tw-x" data-remove aria-label="{{ __('Remove') }}"><i class="fal fa-times" aria-hidden="true"></i></button></div></template>
            <button type="button" class="tw-add" data-add="mn"><i class="fal fa-plus" aria-hidden="true"></i>{{ __('Add a link') }}</button>
            <p class="ta-note tw-pages">{{ __('Pages you can link to:') }} <code>/discover</code> <code>/how</code> <code>/pricing</code> <code>/about</code> <code>/contact</code> <code>/blog</code> <code>/faq</code>{{ __(', and any page you made under Pages.') }}</p>
          </section>
          @include('backend.tica.partials.website-save', ['area' => 'menu'])
        </form>
      @endif

      @if ($tab === 'footer')
        @php($footer = $site['footer'])
        <form method="POST" action="{{ route('admin.tica.website.update', 'footer') }}" class="ta-forms">
          @csrf
          <section class="ta-card">
            <div class="ta-card-h"><span class="ta-ic purple" aria-hidden="true"><i class="fal fa-window-maximize fa-rotate-180"></i></span><div><h2>{{ __('Footer text') }}</h2><p>{{ __('The big line at the bottom left of every page, and the copyright.') }}</p></div></div>
            <div class="ta-field"><label for="tw-ft">{{ __('Your line') }}</label><textarea class="form-control tw-short" id="tw-ft" name="tagline" rows="2" maxlength="120">{{ old('tagline', $footer['tagline']) }}</textarea></div>
            <div class="ta-field"><label for="tw-fc">{{ __('Copyright') }}</label><input class="form-control" id="tw-fc" name="copyright" maxlength="160" value="{{ old('copyright', $footer['copyright']) }}"><small class="tw-hint">{{ __('{year} becomes the current year.') }}</small></div>
          </section>
          @foreach (array_pad($footer['columns'], 2, ['title' => '', 'links' => []]) as $c => $column)
            <section class="ta-card">
              <div class="ta-card-h"><span class="ta-ic blue" aria-hidden="true"><i class="fal fa-columns"></i></span><div><h2>{{ __('Link column :n', ['n' => $c + 1]) }}</h2><p>{{ __('A heading with up to 8 links. Leave the heading empty to hide this column.') }}</p></div></div>
              <div class="ta-field"><label for="tw-col-{{ $c }}">{{ __('Heading') }}</label><input class="form-control" id="tw-col-{{ $c }}" name="columns[{{ $c }}][title]" maxlength="30" value="{{ $column['title'] }}"></div>
              <div class="tw-rows" data-rows="fc{{ $c }}" data-max="8">
                @foreach ($column['links'] as $i => $row)
                  <div class="tw-row two" data-row><input class="form-control" name="columns[{{ $c }}][links][{{ $i }}][label]" maxlength="40" value="{{ $row['label'] }}" aria-label="{{ __('Link text') }}" placeholder="{{ __('Link text') }}"><input class="form-control" name="columns[{{ $c }}][links][{{ $i }}][url]" maxlength="300" value="{{ $row['url'] }}" aria-label="{{ __('Goes to') }}" placeholder="/about"><button type="button" class="tw-x" data-remove aria-label="{{ __('Remove') }}"><i class="fal fa-times" aria-hidden="true"></i></button></div>
                @endforeach
              </div>
              <template data-for="fc{{ $c }}"><div class="tw-row two" data-row><input class="form-control" name="columns[{{ $c }}][links][__i__][label]" maxlength="40" aria-label="{{ __('Link text') }}" placeholder="{{ __('Link text') }}"><input class="form-control" name="columns[{{ $c }}][links][__i__][url]" maxlength="300" aria-label="{{ __('Goes to') }}" placeholder="/about"><button type="button" class="tw-x" data-remove aria-label="{{ __('Remove') }}"><i class="fal fa-times" aria-hidden="true"></i></button></div></template>
              <button type="button" class="tw-add" data-add="fc{{ $c }}"><i class="fal fa-plus" aria-hidden="true"></i>{{ __('Add a link') }}</button>
            </section>
          @endforeach
          <p class="ta-note">{{ __('The third column, "Talk to us", comes from Contact details. Refunds, Terms, Privacy and Cookie settings always stay at the bottom.') }}</p>
          @include('backend.tica.partials.website-save', ['area' => 'footer'])
        </form>
      @endif

      @if ($tab === 'contact')
        @php($contact = $site['contact'])
        <form method="POST" action="{{ route('admin.tica.website.update', 'contact') }}" class="ta-forms">
          @csrf
          <section class="ta-card">
            <div class="ta-card-h"><span class="ta-ic green" aria-hidden="true"><i class="fal fa-phone"></i></span><div><h2>{{ __('Phone and place') }}</h2><p>{{ __('Shown on the Contact page and in the footer.') }}</p></div></div>
            <div class="tw-two">
              <div class="ta-field"><label for="tw-ph">{{ __('Phone number') }}</label><input class="form-control" id="tw-ph" name="phone" maxlength="30" value="{{ old('phone', $contact['phone']) }}"></div>
              <div class="ta-field"><label for="tw-hr">{{ __('When people can call') }}</label><input class="form-control" id="tw-hr" name="phone_hours" maxlength="80" value="{{ old('phone_hours', $contact['phone_hours']) }}"></div>
            </div>
            <div class="ta-field"><label for="tw-ct">{{ __('City') }}</label><input class="form-control" id="tw-ct" name="city" maxlength="80" value="{{ old('city', $contact['city']) }}"></div>
          </section>
          <section class="ta-card">
            <div class="ta-card-h"><span class="ta-ic blue" aria-hidden="true"><i class="fal fa-envelope"></i></span><div><h2>{{ __('Email addresses') }}</h2><p>{{ __('Up to 5. The first one also shows in the footer.') }}</p></div></div>
            <div class="tw-rows" data-rows="em" data-max="5">
              @foreach ($contact['emails'] as $i => $row)
                <div class="tw-row two" data-row><input class="form-control" type="email" name="emails[{{ $i }}][address]" maxlength="120" value="{{ $row['address'] }}" aria-label="{{ __('Email address') }}" placeholder="hello@tica.ng"><input class="form-control" name="emails[{{ $i }}][label]" maxlength="30" value="{{ $row['label'] }}" aria-label="{{ __('What it is for') }}" placeholder="{{ __('What it is for') }}"><button type="button" class="tw-x" data-remove aria-label="{{ __('Remove') }}"><i class="fal fa-times" aria-hidden="true"></i></button></div>
              @endforeach
            </div>
            <template data-for="em"><div class="tw-row two" data-row><input class="form-control" type="email" name="emails[__i__][address]" maxlength="120" aria-label="{{ __('Email address') }}" placeholder="hello@tica.ng"><input class="form-control" name="emails[__i__][label]" maxlength="30" aria-label="{{ __('What it is for') }}" placeholder="{{ __('What it is for') }}"><button type="button" class="tw-x" data-remove aria-label="{{ __('Remove') }}"><i class="fal fa-times" aria-hidden="true"></i></button></div></template>
            <button type="button" class="tw-add" data-add="em"><i class="fal fa-plus" aria-hidden="true"></i>{{ __('Add an email') }}</button>
          </section>
          <section class="ta-card">
            <div class="ta-card-h"><span class="ta-ic purple" aria-hidden="true"><i class="fal fa-share-alt"></i></span><div><h2>{{ __('Social media') }}</h2><p>{{ __('The "Follow us" list on the Contact page.') }}</p></div></div>
            <div class="tw-rows" data-rows="so" data-max="7">
              @foreach ($contact['socials'] as $i => $row)
                <div class="tw-row three" data-row>
                  <select class="form-control" name="socials[{{ $i }}][network]" aria-label="{{ __('Network') }}">@foreach ($networks as $key => $name)<option value="{{ $key }}" @selected($row['network'] === $key)>{{ $name }}</option>@endforeach</select>
                  <input class="form-control" name="socials[{{ $i }}][handle]" maxlength="40" value="{{ $row['handle'] }}" aria-label="{{ __('Name or handle') }}" placeholder="@tica.ng">
                  <input class="form-control" type="url" name="socials[{{ $i }}][url]" maxlength="300" value="{{ $row['url'] }}" aria-label="{{ __('Link') }}" placeholder="https://">
                  <button type="button" class="tw-x" data-remove aria-label="{{ __('Remove') }}"><i class="fal fa-times" aria-hidden="true"></i></button>
                </div>
              @endforeach
            </div>
            <template data-for="so"><div class="tw-row three" data-row><select class="form-control" name="socials[__i__][network]" aria-label="{{ __('Network') }}">@foreach ($networks as $key => $name)<option value="{{ $key }}">{{ $name }}</option>@endforeach</select><input class="form-control" name="socials[__i__][handle]" maxlength="40" aria-label="{{ __('Name or handle') }}" placeholder="@tica.ng"><input class="form-control" type="url" name="socials[__i__][url]" maxlength="300" aria-label="{{ __('Link') }}" placeholder="https://"><button type="button" class="tw-x" data-remove aria-label="{{ __('Remove') }}"><i class="fal fa-times" aria-hidden="true"></i></button></div></template>
            <button type="button" class="tw-add" data-add="so"><i class="fal fa-plus" aria-hidden="true"></i>{{ __('Add a social link') }}</button>
          </section>
          @include('backend.tica.partials.website-save', ['area' => 'contact'])
        </form>
      @endif
    </div>

    <aside class="tw-side" aria-label="{{ __('Website content') }}">
      <p class="tw-side-h">{{ __('Pages and posts') }}</p>
      @if (!is_null($counts['pages']))
        <a class="tw-doc" href="{{ route('admin.custom_pages', ['language' => $defaultLang->code]) }}"><span class="ta-ic blue" aria-hidden="true"><i class="fal fa-file-alt"></i></span><span><b>{{ __('Pages') }}</b><small>{{ trans_choice(':count page|:count pages', $counts['pages'], ['count' => $counts['pages']]) }} · {{ __('opened at /page-name') }}</small></span></a>
      @endif
      @if (!is_null($counts['blog']))
        <a class="tw-doc" href="{{ route('admin.blog_management.blogs', ['language' => $defaultLang->code]) }}"><span class="ta-ic orange" aria-hidden="true"><i class="fal fa-newspaper"></i></span><span><b>{{ __('Blog') }}</b><small>{{ trans_choice(':count post|:count posts', $counts['blog'], ['count' => $counts['blog']]) }} · {{ __('shown at /blog') }}</small></span></a>
      @endif
      @if (!is_null($counts['faq']))
        <a class="tw-doc" href="{{ route('admin.faq_management', ['language' => $defaultLang->code]) }}"><span class="ta-ic green" aria-hidden="true"><i class="fal fa-question-circle"></i></span><span><b>{{ __('Questions (FAQ)') }}</b><small>{{ trans_choice(':count question|:count questions', $counts['faq'], ['count' => $counts['faq']]) }} · {{ __('on How TICA works and /faq') }}</small></span></a>
      @endif
      <p class="tw-side-note">{{ __('All of these use the TICA design on the site.') }}</p>
    </aside>
  </div>
@endsection

@section('script')
  <script>
    (function () {
      function count(list) { return list.querySelectorAll('[data-row]').length; }
      function sync(list) {
        var btn = document.querySelector('[data-add="' + list.dataset.rows + '"]'), max = +list.dataset.max || 99;
        if (btn) btn.hidden = count(list) >= max;
      }
      document.querySelectorAll('[data-rows]').forEach(sync);
      document.addEventListener('click', function (ev) {
        var add = ev.target.closest('[data-add]');
        if (add) {
          var key = add.dataset.add, list = document.querySelector('[data-rows="' + key + '"]'), tpl = document.querySelector('template[data-for="' + key + '"]');
          var n = Date.now() % 100000;
          list.insertAdjacentHTML('beforeend', tpl.innerHTML.replace(/__i__/g, 'n' + n));
          var first = list.lastElementChild.querySelector('input,select'); if (first) first.focus();
          sync(list); return;
        }
        var rm = ev.target.closest('[data-remove]');
        if (rm) { var row = rm.closest('[data-row]'), l = row.parentNode; row.remove(); if (l.dataset && l.dataset.rows) sync(l); return; }
        var up = ev.target.closest('[data-up]');
        if (up) { var r = up.closest('[data-row]'); if (r.previousElementSibling) r.parentNode.insertBefore(r, r.previousElementSibling); up.focus(); }
      });
      var pick = document.querySelector('.tw-add-photo input');
      if (pick) pick.addEventListener('change', function () {
        var holder = pick.closest('.tw-add-photo');
        holder.querySelectorAll('img').forEach(function (i) { i.remove(); });
        var span = holder.querySelector('span');
        span.textContent = pick.files.length ? pick.files.length + ' ' + (pick.files.length === 1 ? @json(__('photo ready')) : @json(__('photos ready'))) : @json(__('Add photos'));
      });
      document.addEventListener('change', function (ev) {
        var inp = ev.target.closest('.tw-logo input'); if (!inp) return;
        var lab = inp.closest('.tw-logo'), f = inp.files[0]; if (!f) return;
          var url = URL.createObjectURL(f), old = lab.querySelector('img,i'); var img = document.createElement('img'); img.src = url; img.alt = '';
          if (old) old.replaceWith(img); else lab.prepend(img);
      });
    })();
  </script>
@endsection
