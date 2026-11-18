@extends('backend.layout')

@section('style')
  <link rel="stylesheet" href="{{ asset('assets/tica-ui/css/admin-mail-templates.css') }}?v={{ @filemtime(public_path('assets/tica-ui/css/admin-mail-templates.css')) }}">
@endsection

@section('content')
  <div class="mail-templates-page">
    <header class="mail-templates-header">
      <div class="mail-templates-title">
        <h1>{{ __('Email templates') }}</h1>
        <span>{{ trans_choice(':count template|:count templates', count($templates), ['count' => count($templates)]) }}</span>
      </div>

      @if (count($templates) > 0)
        <div class="mail-template-search">
          <i class="fas fa-search" aria-hidden="true"></i>
          <label class="sr-only" for="mailTemplateSearch">{{ __('Search email templates') }}</label>
          <input id="mailTemplateSearch" type="search" autocomplete="off"
            placeholder="{{ __('Search templates') }}" aria-controls="mailTemplateList">
          <button id="mailTemplateSearchClear" type="button" aria-label="{{ __('Clear search') }}" hidden>
            <i class="fas fa-times" aria-hidden="true"></i>
          </button>
        </div>
      @endif
    </header>

    <section class="mail-template-surface" aria-label="{{ __('Email templates') }}">
      @if (count($templates) === 0)
        <div class="mail-template-empty">
          <span><i class="far fa-envelope" aria-hidden="true"></i></span>
          <strong>{{ __('No email templates found') }}</strong>
        </div>
      @else
        <div class="mail-template-columns" aria-hidden="true">
          <span>{{ __('Template') }}</span>
          <span>{{ __('Subject') }}</span>
          <span>{{ __('Action') }}</span>
        </div>

        <div class="mail-template-list" id="mailTemplateList">
          @foreach ($templates as $template)
            @php
              $mailType = str_replace('_', ' ', $template->mail_type);
              $searchText = strtolower($mailType . ' ' . $template->mail_subject);
              $icon = 'far fa-envelope';
              $tone = 'blue';

              if (str_contains($template->mail_type, 'verify')) {
                  $icon = 'fas fa-shield-alt';
                  $tone = 'orange';
              } elseif (str_contains($template->mail_type, 'password')) {
                  $icon = 'fas fa-key';
                  $tone = 'violet';
              } elseif (str_contains($template->mail_type, 'booking') || str_contains($template->mail_type, 'event')) {
                  $icon = 'fas fa-ticket-alt';
                  $tone = 'blue';
              } elseif (str_contains($template->mail_type, 'withdraw') || str_contains($template->mail_type, 'balance')) {
                  $icon = 'fas fa-wallet';
                  $tone = 'green';
              } elseif (str_contains($template->mail_type, 'product') || str_contains($template->mail_type, 'order')) {
                  $icon = 'fas fa-shopping-bag';
                  $tone = 'slate';
              }
            @endphp

            <article class="mail-template-row" data-template-search="{{ $searchText }}">
              <div class="mail-template-kind">
                <span class="mail-template-icon mail-template-icon--{{ $tone }}">
                  <i class="{{ $icon }}" aria-hidden="true"></i>
                </span>
                <strong class="text-capitalize">{{ $mailType }}</strong>
              </div>
              <p>{{ $template->mail_subject }}</p>
              <a class="mail-template-edit"
                href="{{ route('admin.basic_settings.edit_mail_template', ['id' => $template->id]) }}">
                <i class="fas fa-pen" aria-hidden="true"></i>
                <span>{{ __('Edit') }}</span>
              </a>
            </article>
          @endforeach
        </div>

        <div class="mail-template-empty mail-template-empty--search" id="mailTemplateNoResults" hidden>
          <span><i class="fas fa-search" aria-hidden="true"></i></span>
          <strong>{{ __('No templates match your search') }}</strong>
        </div>
      @endif
    </section>
  </div>
@endsection

@section('script')
  @if (count($templates) > 0)
    <script>
      (function () {
        var input = document.getElementById('mailTemplateSearch');
        var clearButton = document.getElementById('mailTemplateSearchClear');
        var rows = Array.prototype.slice.call(document.querySelectorAll('[data-template-search]'));
        var emptyState = document.getElementById('mailTemplateNoResults');

        function filterTemplates() {
          var query = input.value.trim().toLowerCase();
          var visible = 0;

          rows.forEach(function (row) {
            var matches = !query || row.dataset.templateSearch.indexOf(query) !== -1;
            row.hidden = !matches;
            if (matches) visible += 1;
          });

          clearButton.hidden = !query;
          emptyState.hidden = visible !== 0;
        }

        input.addEventListener('input', filterTemplates);
        clearButton.addEventListener('click', function () {
          input.value = '';
          filterTemplates();
          input.focus();
        });
      })();
    </script>
  @endif
@endsection
