@extends('backend.layout')

{{-- this style will be applied when the direction of language is right-to-left --}}
@includeIf('backend.partials.rtl-style')

@section('style')
  <style>
    .tcat{--tc-blue:#2E4FD8;--tc-blue-50:#EEF2FD;--tc-ink:#0F172A;--tc-soft:#526079;--tc-line:#E2E8F1;--tc-orange:#B4682E;--tc-orange-50:#FEF3E8;color:var(--tc-ink)}
    .tcat-head{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:16px;margin:8px 0 24px}
    .tcat-head h1{margin:0 0 4px;font-size:26px;font-weight:800;letter-spacing:-.01em}
    .tcat-head p{margin:0;color:var(--tc-soft);font-size:15px}
    .tcat-tools{display:flex;flex-wrap:wrap;align-items:center;gap:10px}
    .tcat-tools .form-control{min-width:160px;height:44px;border-radius:10px}
    .tcat-btn{display:inline-flex;align-items:center;gap:8px;height:44px;padding:0 18px;border-radius:10px;border:0;font-weight:700;font-size:14px;cursor:pointer;text-decoration:none!important}
    .tcat-btn svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
    .tcat-btn-primary{background:var(--tc-blue);color:#fff!important}
    .tcat-btn-primary:hover{background:#1E3AB0}
    .tcat-btn-danger{background:#FEF2F2;color:#9B1C1C!important;border:1px solid #FBD5D5}
    .tcat-card{background:#fff;border:1px solid var(--tc-line);border-radius:16px;overflow:hidden}
    .tcat-table{width:100%;border-collapse:collapse}
    .tcat-table th{padding:14px 16px;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--tc-soft);background:#F8FAFD;border-bottom:1px solid var(--tc-line);text-align:left}
    .tcat-table td{padding:14px 16px;border-bottom:1px solid var(--tc-line);vertical-align:middle;font-size:15px}
    .tcat-table tr:last-child td{border-bottom:0}
    .tcat-table tbody tr:hover{background:#FAFBFE}
    .tcat-check{width:44px}
    .tcat-check input{width:18px;height:18px;accent-color:var(--tc-blue)}
    .tcat-name{display:flex;align-items:center;gap:14px}
    .tcat-ico{flex:none;width:44px;height:44px;border-radius:12px;background:var(--tc-blue-50);color:var(--tc-blue);display:grid;place-items:center}
    .tcat-ico svg,.tcat-opt-box svg{width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
    .tcat-ico.none{background:#FEF3E8;color:var(--tc-orange)}
    .tcat-name b{display:block;font-weight:700}
    .tcat-name small{display:block;color:var(--tc-soft);font-size:13px}
    .tcat-name small.warn{color:var(--tc-orange);font-weight:600}
    .tcat-pill{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:999px;font-size:13px;font-weight:700}
    .tcat-pill:before{content:"";width:7px;height:7px;border-radius:50%;background:currentColor}
    .tcat-pill.on{background:#ECFDF3;color:#067647}
    .tcat-pill.off{background:#F2F4F7;color:#475467}
    .tcat-feat{display:inline-flex;align-items:center;gap:6px;height:36px;padding:0 12px;border-radius:999px;border:1px solid var(--tc-line);background:#fff;font-size:13px;font-weight:600;color:var(--tc-soft);cursor:pointer}
    .tcat-feat svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2;stroke-linejoin:round}
    .tcat-feat.is-on{background:var(--tc-orange-50);border-color:#F2C594;color:var(--tc-orange)}
    .tcat-feat.is-on svg{fill:currentColor}
    .tcat-order{font-variant-numeric:tabular-nums;color:var(--tc-soft)}
    .tcat-actions{display:flex;gap:8px;justify-content:flex-end}
    .tcat-act{width:40px;height:40px;display:inline-grid;place-items:center;border-radius:10px;border:1px solid var(--tc-line);background:#fff;color:var(--tc-ink);cursor:pointer}
    .tcat-act svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
    .tcat-act:hover{border-color:var(--tc-blue);color:var(--tc-blue)}
    .tcat-act.del:hover{border-color:#F04438;color:#B42318}
    .tcat-empty{padding:56px 24px;text-align:center}
    .tcat-empty .tcat-ico{margin:0 auto 14px;width:56px;height:56px}
    .tcat-empty h2{font-size:18px;font-weight:700;margin:0 0 6px}
    .tcat-empty p{color:var(--tc-soft);margin:0 0 18px}
    .tcat-modal .modal-content{border-radius:16px;border:0}
    .tcat-modal .modal-header,.tcat-modal .modal-footer{border-color:var(--tc-line);padding:18px 24px}
    .tcat-modal .modal-body{padding:20px 24px}
    .tcat-modal .modal-title{font-weight:800}
    .tcat-modal label,.tcat-label{font-weight:700;font-size:14px;color:var(--tc-ink);margin-bottom:6px}
    .tcat-modal .form-control{height:46px;border-radius:10px}
    .tcat-modal .btn{height:44px;padding:0 20px;border-radius:10px;font-weight:700}
    .tcat-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
    .tcat-row-3{grid-template-columns:repeat(3,minmax(0,1fr))}
    .tcat-help{margin:0 0 12px;color:var(--tc-soft);font-size:13px}
    .tcat-picker{border:0;padding:0;margin:0 0 18px;min-width:0}
    .tcat-picker legend{width:auto;float:none;font-size:14px}
    .tcat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(52px,1fr));gap:8px}
    .tcat-opt{margin:0;cursor:pointer;position:relative}
    .tcat-opt input{position:absolute;opacity:0;inset:0}
    .tcat-opt-box{height:52px;border-radius:12px;border:1.5px solid var(--tc-line);display:grid;place-items:center;color:var(--tc-soft);background:#fff;transition:all .12s}
    .tcat-opt:hover .tcat-opt-box{border-color:#9DB0F0;color:var(--tc-blue)}
    .tcat-opt input:checked + .tcat-opt-box{border-color:var(--tc-blue);background:var(--tc-blue-50);color:var(--tc-blue);box-shadow:0 0 0 3px rgba(46,79,216,.15)}
    .tcat-opt input:focus-visible + .tcat-opt-box{outline:3px solid rgba(46,79,216,.35);outline-offset:2px}
    .tcat-picked{margin:10px 0 0;font-size:13px;color:var(--tc-soft)}
    .tcat-picked b{color:var(--tc-ink)}
    @media (max-width:767px){
      .tcat-row,.tcat-row-3{grid-template-columns:1fr}
      .tcat-table thead{display:none}
      .tcat-table tr{display:grid;grid-template-columns:44px 1fr auto;gap:8px 12px;padding:14px 12px;border-bottom:1px solid var(--tc-line)}
      .tcat-table td{border:0;padding:0}
      .tcat-table td.tcat-status,.tcat-table td.tcat-feat-cell,.tcat-table td.tcat-order{grid-column:2}
      .tcat-table td.tcat-actions-cell{grid-column:2/4}
      .tcat-actions{justify-content:flex-start}
    }
  </style>
@endsection

@section('content')
  <div class="tcat">
    <div class="tcat-head">
      <div>
        <h1>{{ __('Event categories') }}</h1>
        <p>{{ __('Organisers pick one of these when they create an event. Each shows with its icon on the home page and in Discover.') }}</p>
      </div>
      <div class="tcat-tools">
        @if (count($langs) > 1)
          @includeIf('backend.partials.languages')
        @endif
        <button type="button" class="tcat-btn tcat-btn-danger d-none bulk-delete"
          data-href="{{ route('admin.event_management.bulk_delete_category') }}">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 13h12l1-13M9 7V4h6v3"/></svg>
          {{ __('Delete selected') }}
        </button>
        <a href="#" data-toggle="modal" data-target="#createModal" class="tcat-btn tcat-btn-primary">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
          {{ __('Add category') }}
        </a>
      </div>
    </div>

    <div class="tcat-card">
      @if (count($categories) == 0)
        <div class="tcat-empty">
          <div class="tcat-ico"><svg viewBox="0 0 24 24" aria-hidden="true">{!! $icons['ticket']['svg'] !!}</svg></div>
          <h2>{{ __('No categories yet') }}</h2>
          <p>{{ __('Add your first category so organisers can file their events under it.') }}</p>
          <a href="#" data-toggle="modal" data-target="#createModal" class="tcat-btn tcat-btn-primary">{{ __('Add category') }}</a>
        </div>
      @else
        <div class="table-responsive">
          <table class="tcat-table">
            <thead>
              <tr>
                <th scope="col" class="tcat-check">
                  <input type="checkbox" class="bulk-check" data-val="all" aria-label="{{ __('Select all categories') }}">
                </th>
                <th scope="col">{{ __('Category') }}</th>
                <th scope="col">{{ __('Status') }}</th>
                <th scope="col">{{ __('Featured') }}</th>
                <th scope="col">{{ __('Order') }}</th>
                <th scope="col" class="text-right">{{ __('Actions') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($categories as $category)
                @php($icon = $icons[$category->icon] ?? null)
                <tr>
                  <td class="tcat-check">
                    <input type="checkbox" class="bulk-check" data-val="{{ $category->id }}" aria-label="{{ __('Select') }} {{ $category->name }}">
                  </td>
                  <td>
                    <div class="tcat-name">
                      @if ($icon)
                        <span class="tcat-ico" title="{{ __($icon['label']) }}"><svg viewBox="0 0 24 24" aria-hidden="true">{!! $icon['svg'] !!}</svg></span>
                      @else
                        <span class="tcat-ico none" title="{{ __('No icon') }}"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg></span>
                      @endif
                      <div>
                        <b>{{ $category->name }}</b>
                        @if ($icon)
                          <small>{{ $category->events_count }} {{ $category->events_count == 1 ? __('event') : __('events') }}</small>
                        @else
                          <small class="warn">{{ __('No icon yet. Edit to pick one.') }}</small>
                        @endif
                      </div>
                    </div>
                  </td>
                  <td class="tcat-status">
                    @if ($category->status == 1)
                      <span class="tcat-pill on">{{ __('Active') }}</span>
                    @else
                      <span class="tcat-pill off">{{ __('Hidden') }}</span>
                    @endif
                  </td>
                  <td class="tcat-feat-cell">
                    <form action="{{ route('admin.event_management.category.update_featured', ['id' => $category->id]) }}" method="post">
                      @csrf
                      <input type="hidden" name="is_featured" value="{{ $category->is_featured == 'yes' ? 'no' : 'yes' }}">
                      <button type="submit" class="tcat-feat {{ $category->is_featured == 'yes' ? 'is-on' : '' }}"
                        aria-pressed="{{ $category->is_featured == 'yes' ? 'true' : 'false' }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/></svg>
                        {{ $category->is_featured == 'yes' ? __('Featured') : __('Not featured') }}
                      </button>
                    </form>
                  </td>
                  <td class="tcat-order">{{ $category->serial_number }}</td>
                  <td class="tcat-actions-cell">
                    <div class="tcat-actions">
                      <a class="tcat-act editBtn" href="#" data-toggle="modal" data-target="#editEventCategoryModal"
                        aria-label="{{ __('Edit') }} {{ $category->name }}"
                        data-id="{{ $category->id }}" data-name="{{ $category->name }}"
                        data-cat_icon="{{ $category->icon }}" data-status="{{ $category->status }}"
                        data-serial_number="{{ $category->serial_number }}"
                        data-is_featured="{{ $category->is_featured }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M14 6l4 4"/></svg>
                      </a>
                      <form class="deleteForm d-inline-block"
                        action="{{ route('admin.event_management.delete_category', ['id' => $category->id]) }}" method="post">
                        @csrf
                        <button type="submit" class="tcat-act del deleteBtn" aria-label="{{ __('Delete') }} {{ $category->name }}"
                          @if ($category->events_count > 0) title="{{ __('Deleting this category also deletes its events.') }}" @endif>
                          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 13h12l1-13M9 7V4h6v3"/></svg>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>

  {{-- create modal --}}
  @include('backend.event.category.create')

  {{-- edit modal --}}
  @include('backend.event.category.edit')
@endsection

@section('script')
  <script>
    (function () {
      function sync(picker) {
        var checked = picker.querySelector('input[name="icon"]:checked');
        var out = picker.querySelector('.tcat-picked');
        if (!out) return;
        if (checked) {
          out.innerHTML = '';
          out.appendChild(document.createTextNode(@json(__('Picked: '))));
          var b = document.createElement('b');
          b.textContent = checked.getAttribute('data-label');
          out.appendChild(b);
        } else {
          out.textContent = @json(__('No icon picked yet.'));
        }
      }

      document.querySelectorAll('.tcat-picker').forEach(function (picker) {
        picker.addEventListener('change', function () { sync(picker); });
      });

      // Fill the edit modal's icon from the row that was clicked.
      $(document).on('click', '.editBtn[data-cat_icon]', function () {
        var icon = $(this).attr('data-cat_icon') || '';
        var picker = document.getElementById('tcat-edit-picker');
        picker.querySelectorAll('input[name="icon"]').forEach(function (input) {
          input.checked = input.value === icon;
        });
        sync(picker);
      });
    })();
  </script>
@endsection
