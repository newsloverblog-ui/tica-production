@extends('organizer.layout')

@section('style')
<style>
.tica-event-builder>.row>.col-md-12>.card>.card-header{display:none}
.tica-event-builder .page-header{display:none}.tica-builder-hero{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;padding:28px 30px;margin-bottom:24px;border:1px solid #dfe7f0;border-radius:24px;background:linear-gradient(125deg,#fff 0%,#f5f8fc 65%,#fff1e8 100%);box-shadow:0 16px 42px rgba(11,36,71,.07)}.tica-builder-hero h1{margin:5px 0 7px;color:#0b2447;font-size:clamp(28px,4vw,42px);font-weight:800;letter-spacing:-.035em}.tica-builder-hero p{max-width:690px;margin:0;color:#667085;font-size:15px}.tica-builder-kicker{color:#f97316!important;font-size:12px!important;font-weight:800;letter-spacing:.16em;text-transform:uppercase}.tica-builder-steps{display:flex;gap:8px;flex-wrap:wrap}.tica-builder-steps span{display:inline-flex;padding:9px 12px;border:1px solid #dfe7f0;border-radius:999px;background:#fff;color:#667085;font-size:12px;font-weight:700}.tica-builder-steps span:first-child{border-color:#0b2447;background:#0b2447;color:#fff}.tica-event-builder>.row>.col-md-12>.card{border:1px solid #dfe7f0;border-radius:22px;box-shadow:0 14px 40px rgba(11,36,71,.07)}.tica-event-builder .card-header{padding:20px 24px;border-radius:22px 22px 0 0}.tica-event-builder .col-lg-8.offset-lg-2{max-width:980px;flex:0 0 100%;margin:0 auto}.tica-builder-section-title{display:flex;align-items:center;gap:12px;margin:30px 0 18px;padding-top:26px;border-top:1px solid #e5eaf1;color:#0b2447;font-size:19px;font-weight:800}.tica-builder-section-title:first-of-type{margin-top:0;padding-top:0;border:0}.tica-builder-section-title span{display:grid;width:34px;height:34px;place-items:center;border-radius:10px;background:#fff1e8;color:#f97316;font-size:14px}.tica-event-builder label{color:#0b2447;font-size:13px;font-weight:700}.tica-event-builder .form-control,.tica-event-builder .select2-container .select2-selection{min-height:46px;border:1px solid #dfe7f0!important;border-radius:12px!important;background:#fff!important;box-shadow:none!important}.tica-event-builder textarea.form-control{min-height:112px}.tica-event-builder .form-control:focus{border-color:#0f4c9e!important;box-shadow:0 0 0 4px rgba(15,76,158,.1)!important}.tica-event-builder .selectgroup-button{min-height:44px;padding:11px 16px;border-color:#dfe7f0;border-radius:11px!important;font-weight:700}.tica-event-builder .selectgroup-input:checked+.selectgroup-button{border-color:#0b2447!important;background:#0b2447!important;color:#fff!important}.tica-event-builder .dropzone{min-height:170px!important;border:1.5px dashed #aab9ca!important;border-radius:16px!important;background:#f7f9fc!important}.tica-event-builder .thumb-preview{overflow:hidden;width:190px;height:125px;border-radius:16px;background:#f3f6fb}.tica-event-builder .thumb-preview img{width:100%;height:100%;object-fit:cover}.tica-event-builder .version{overflow:hidden;margin-bottom:14px;border:1px solid #dfe7f0!important;border-radius:16px!important}.tica-event-builder .version-header{background:#f7f9fc!important}.tica-event-builder .version-header .btn{color:#0b2447!important;font-weight:800}.tica-event-builder .version-body{padding:22px!important}.tica-launch-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.tica-launch-option{position:relative;margin:0!important;cursor:pointer}.tica-launch-option input{position:absolute;opacity:0}.tica-launch-card{display:flex;gap:12px;min-height:88px;padding:15px;border:1px solid #dfe7f0;border-radius:15px;background:#fff;transition:.2s ease}.tica-launch-card i{display:grid;flex:0 0 36px;height:36px;place-items:center;border-radius:10px;background:#fff1e8;color:#f97316}.tica-launch-card strong{display:block;color:#0b2447;font-size:14px}.tica-launch-card small{display:block;margin-top:4px;color:#667085;line-height:1.4}.tica-launch-option input:checked+.tica-launch-card{border-color:#0f4c9e;background:#f2f6fd;box-shadow:0 0 0 3px rgba(15,76,158,.1)}.tica-event-builder .card-footer{position:sticky;z-index:10;bottom:12px;margin:0 24px 18px;padding:14px!important;border:1px solid #dfe7f0!important;border-radius:16px!important;background:rgba(255,255,255,.94)!important;box-shadow:0 14px 36px rgba(11,36,71,.14);backdrop-filter:blur(12px)}.tica-event-builder #EventSubmit{min-width:210px;min-height:48px;border:0;border-radius:12px;background:#f97316!important;color:#0b2447!important;font-weight:800}@media(max-width:767px){.tica-builder-hero{align-items:flex-start;flex-direction:column;padding:22px}.tica-builder-steps{display:grid;width:100%;grid-template-columns:1fr 1fr}.tica-event-builder .card-body{padding:18px!important}.tica-launch-grid{grid-template-columns:1fr}.tica-event-builder .card-footer{margin:0 12px 12px}.tica-event-builder #EventSubmit{width:100%}.tica-event-builder #multiple_dates{overflow-x:auto}.tica-event-builder #multiple_dates table{min-width:760px}}
</style>
@endsection

@section('content')
  <div class="tica-event-builder">
  <section class="tica-builder-hero" aria-labelledby="event-builder-title">
    <div><p class="tica-builder-kicker">{{ __('Create an experience') }}</p><h1 id="event-builder-title">{{ request()->input('type') === 'online' ? __('Create an online event') : __('Create a venue event') }}</h1><p>{{ __('Add the essentials, then continue directly into tickets, registration, guest management or check-in setup.') }}</p></div>
    <div class="tica-builder-steps" aria-label="{{ __('Creation progress') }}"><span>1 · {{ __('Event') }}</span><span>2 · {{ __('Access') }}</span><span>3 · {{ __('Guests') }}</span></div>
  </section>
  <div class="page-header">
    <h4 class="page-title">{{ __('Add Event') }}</h4>
    <ul class="breadcrumbs">
      <li class="nav-home">
        <a href="{{ route('organizer.dashboard') }}">
          <i class="flaticon-home"></i>
        </a>
      </li>
      <li class="separator">
        <i class="flaticon-right-arrow"></i>
      </li>
      <li class="nav-item">
        <a href="#">{{ __('Event Management') }}</a>
      </li>
      <li class="separator">
        <i class="flaticon-right-arrow"></i>
      </li>
      <li class="nav-item">
        <a href="{{ route('choose-event-type', ['language' => $defaultLang->code]) }}">{{ __('Choose Event Type') }}</a>
      </li>
      <li class="separator">
        <i class="flaticon-right-arrow"></i>
      </li>
      <li class="nav-item">
        <a href="#">{{ __('Add Event') }}</a>
      </li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="card">
        <div class="card-header">
          <div class="card-title d-inline-block">{{ __('Add Event') }}</div>
          <a class="btn btn-info btn-sm float-right d-inline-block"
            href="{{ route('organizer.event_management.event', ['language' => $defaultLang->code]) }}">
            <span class="btn-label">
              <i class="fas fa-backward"></i>
            </span>
            {{ __('Back') }}
          </a>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-lg-8 offset-lg-2">
              <div class="alert alert-danger pb-1 dis-none" id="eventErrors">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <ul></ul>
              </div>
              <h2 class="tica-builder-section-title"><span>1</span>{{ __('Event media') }}</h2>
              <div class="col-lg-12 px-0">
                <label for="" class="mb-2"><strong>{{ __('Gallery Images') }} **</strong></label>
                <form action="{{ route('organizer.event.imagesstore') }}" id="my-dropzone" enctype="multipart/formdata"
                  class="dropzone create">
                  @csrf
                  <div class="fallback">
                    <input name="file" type="file" multiple />
                  </div>
                </form>
                <div class=" mb-0" id="errpreimg">

                </div>
                <p class="text-warning">{{ __('Image Size') . ' All' }}</p>
              </div>
              <form id="eventForm" action="{{ route('organizer.event_management.store_event') }}" method="POST"
                enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="event_type" value="{{ request()->input('type') }}">
                <h2 class="tica-builder-section-title"><span>2</span>{{ __('Schedule and publishing') }}</h2>
                <div class="form-group">
                  <label for="">{{ __('Thumbnail Image') . '*' }}</label>
                  <br>
                  <div class="thumb-preview">
                    <img src="{{ asset('assets/admin/img/noimage.jpg') }}" alt="..." class="uploaded-img">
                  </div>

                  <div class="mt-3">
                    <div role="button" class="btn btn-primary btn-sm upload-btn">
                      {{ __('Choose Image') }}
                      <input type="file" class="img-input" name="thumbnail">
                    </div>
                  </div>
                  <p class="text-warning">{{ __('Image Size') . ' : All' }}</p>
                </div>

                <div class="row">
                  <div class="col-lg-12">
                    <div class="form-group mt-1">
                      <label for="">{{ __('Date Type') . '*' }}</label>
                      <div class="selectgroup w-100">
                        <label class="selectgroup-item">
                          <input type="radio" name="date_type" value="single" class="selectgroup-input eventDateType"
                            checked>
                          <span class="selectgroup-button">{{ __('Single') }}</span>
                        </label>

                        <label class="selectgroup-item">
                          <input type="radio" name="date_type" value="multiple" class="selectgroup-input eventDateType">
                          <span class="selectgroup-button">{{ __('Multiple') }}</span>
                        </label>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row countDownStatus">
                  <div class="col-lg-12">
                    <div class="form-group mt-1">
                      <label for="">{{ __('Countdown Status') . '*' }}</label>
                      <div class="selectgroup w-100">
                        <label class="selectgroup-item">
                          <input type="radio" name="countdown_status" value="1" class="selectgroup-input" checked>
                          <span class="selectgroup-button">{{ __('Active') }}</span>
                        </label>

                        <label class="selectgroup-item">
                          <input type="radio" name="countdown_status" value="0" class="selectgroup-input">
                          <span class="selectgroup-button">{{ __('Deactive') }}</span>
                        </label>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row" id="single_dates">
                  <div class="col-lg-3">
                    <div class="form-group">
                      <label>{{ __('Start Date') . '*' }}</label>
                      <input type="date" name="start_date" placeholder="Enter Start Date" class="form-control">
                    </div>
                  </div>

                  <div class="col-lg-3">
                    <div class="form-group">
                      <label for="">{{ __('Start Time') . '*' }}</label>
                      <input type="time" name="start_time" class="form-control">
                    </div>
                  </div>
                  <div class="col-lg-3">
                    <div class="form-group">
                      <label>{{ __('End Date') . '*' }}</label>
                      <input type="date" name="end_date" placeholder="Enter End Date" class="form-control">
                    </div>
                  </div>

                  <div class="col-lg-3">
                    <div class="form-group">
                      <label for="">{{ __('End Time') . '*' }}</label>
                      <input type="time" name="end_time" class="form-control">
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-lg-12 d-none" id="multiple_dates">
                    <div class="form-group">
                      <table class="table table-bordered ">
                        <thead>
                          <tr>
                            <th>{{ __('Start Date') }}</th>
                            <th>{{ __('Start Time') }}</th>
                            <th>{{ __('End Date') }}</th>
                            <th>{{ __('End Time') }}</th>
                            <th><a href="javascrit:void(0)" class="btn btn-success addDateRow"><i
                                  class="fas fa-plus-circle"></i></a></th>
                          </tr>
                        <tbody>
                          <tr>
                            <td>
                              <div class="form-group">
                                <label for="">{{ __('Start Date') . '*' }}</label>
                                <input type="date" name="m_start_date[]" class="form-control">
                              </div>
                            </td>
                            <td>
                              <div class="form-group">
                                <label for="">{{ __('Start Time') . '*' }}</label>
                                <input type="time" name="m_start_time[]" class="form-control">
                              </div>
                            </td>
                            <td>
                              <div class="form-group">
                                <label for="">{{ __('End Date') . '*' }} </label>
                                <input type="date" name="m_end_date[]" class="form-control">
                              </div>
                            </td>
                            <td>
                              <div class="form-group">
                                <label for="">{{ __('End Time') . '*' }} </label>
                                <input type="time" name="m_end_time[]" class="form-control">
                              </div>
                            </td>
                            <td>
                              <a href="javascript:void(0)" class="btn btn-danger deleteDateRow">
                                <i class="fas fa-minus"></i></a>
                            </td>
                          </tr>
                        </tbody>
                        </thead>
                      </table>
                    </div>
                  </div>
                </div>

                <div class="row ">
                  <div class="col-lg-4">
                    <div class="form-group">
                      <label for="">{{ __('Status') . '*' }}</label>
                      <select name="status" class="form-control">
                        <option selected disabled>{{ __('Select a Status') }}</option>
                        <option value="1">{{ __('Active') }}</option>
                        <option value="0">{{ __('Deactive') }}</option>
                      </select>
                    </div>
                  </div>
                  <div class="col-lg-4">
                    <div class="form-group">
                      <label for="">{{ __('Is Feature') . '*' }}</label>
                      <select name="is_featured" class="form-control">
                        <option selected disabled>{{ __('Select') }}</option>
                        <option value="yes">{{ __('Yes') }}</option>
                        <option value="no">{{ __('No') }}</option>
                      </select>
                    </div>
                  </div>
                  @if (request()->input('type') == 'venue')
                    <!--<div class="col-lg-4">-->
                    <!--  <div class="form-group">-->
                    <!--    <label for="">{{ __('Latitude') }}</label>-->
                    <!--    <input type="text" name="latitude" placeholder="{{ __('Latitude') }}"-->
                    <!--      class="form-control">-->
                    <!--  </div>-->
                    <!--</div>-->
                    <!--<div class="col-lg-4">-->
                    <!--  <div class="form-group">-->
                    <!--    <label for="">{{ __('Longitude') }}</label>-->
                    <!--    <input type="text" placeholder="{{ __('Longitude') }}" name="longitude"-->
                    <!--      class="form-control">-->
                    <!--  </div>-->
                    <!--</div>-->
                  @endif
                </div>
                @if (request()->input('type') == 'online')
                  <h2 class="tica-builder-section-title"><span>3</span>{{ __('Tickets and online access') }}</h2>
                  {{-- /*****--Ticekt limtit & ticket for each customer start--****** --}}

                  <div class="row">

                    <div class="col-lg-12">
                      <div class="form-group">
                        <label>{{ __('Private online join link') . '*' }}</label>
                        <input type="url" name="online_join_url" value="{{ old('online_join_url') }}"
                          placeholder="https://zoom.us/j/..." class="form-control" autocomplete="off">
                        <small class="form-text text-muted">{{ __('Kept private and emailed to paid attendees shortly before the event.') }}</small>
                      </div>
                    </div>

                    <div class="col-lg-6">
                      <div class="form-group mt-1">
                        <label for="">{{ __('Total Number of Available Tickets') . '*' }}</label>
                        <div class="selectgroup w-100">
                          <label class="selectgroup-item">
                            <input type="radio" name="ticket_available_type" value="unlimited"
                              class="selectgroup-input" checked>
                            <span class="selectgroup-button">{{ __('Unlimited') }}</span>
                          </label>

                          <label class="selectgroup-item">
                            <input type="radio" name="ticket_available_type" value="limited"
                              class="selectgroup-input">
                            <span class="selectgroup-button">{{ __('Limited') }}</span>
                          </label>
                        </div>
                      </div>
                    </div>
                    <div class="col-lg-6 d-none" id="ticket_available">
                      <div class="form-group">
                        <label>{{ __('Enter total number of available tickets') . '*' }}</label>
                        <input type="number" name="ticket_available"
                          placeholder="{{ __('Enter total number of available tickets') }}" class="form-control">
                      </div>
                    </div>
                    @if ($websiteInfo->event_guest_checkout_status != 1)
                      <div class="col-lg-6">
                        <div class="form-group mt-1">
                          <label for="">{{ __('Maximum number of tickets for each customer') . '*' }}</label>
                          <div class="selectgroup w-100">
                            <label class="selectgroup-item">
                              <input type="radio" name="max_ticket_buy_type" value="unlimited"
                                class="selectgroup-input" checked>
                              <span class="selectgroup-button">{{ __('Unlimited') }}</span>
                            </label>

                            <label class="selectgroup-item">
                              <input type="radio" name="max_ticket_buy_type" value="limited"
                                class="selectgroup-input">
                              <span class="selectgroup-button">{{ __('Limited') }}</span>
                            </label>
                          </div>
                        </div>
                      </div>
                    @else
                      <input type="hidden" name="max_ticket_buy_type" value="unlimited">
                    @endif
                    <div class="col-lg-6 d-none" id="max_buy_ticket">
                      <div class="form-group">
                        <label>{{ __('Enter Maximum number of tickets for each customer') . '*' }}</label>
                        <input type="number" name="max_buy_ticket"
                          placeholder="{{ __('Enter Maximum number of tickets for each customer') }}"
                          class="form-control">
                      </div>
                    </div>

                    <div class="col-lg-6">
                      <div class="">
                        <div class="form-group">
                          <label for="">{{ __('Price') }}
                            ({{ $getCurrencyInfo->base_currency_text }}) *
                          </label>
                          <input type="number" name="price" id="ticket-pricing" class="form-control"
                            placeholder="{{ __('Enter Ticket Price') }}">
                        </div>
                      </div>
                      <div class="form-group">
                        <input type="checkbox" name="pricing_type" value="free" class="" id="free_ticket">
                        <label for="free_ticket">{{ __('Tickets are Free') }}</label>
                      </div>
                    </div>
                  </div>
                  <div class="row" id="early_bird_discount_free">
                    <div class="col-lg-12">
                      <div class="form-group mt-1">
                        <label for="">{{ __('Early Bird Discount') . '*' }}</label>
                        <div class="selectgroup w-100">
                          <label class="selectgroup-item">
                            <input type="radio" name="early_bird_discount_type" value="disable"
                              class="selectgroup-input" checked>
                            <span class="selectgroup-button">{{ __('Disable') }}</span>
                          </label>

                          <label class="selectgroup-item">
                            <input type="radio" name="early_bird_discount_type" value="enable"
                              class="selectgroup-input">
                            <span class="selectgroup-button">{{ __('Enable') }}</span>
                          </label>
                        </div>
                      </div>
                    </div>
                    <div class="col-lg-12 d-none" id="early_bird_dicount">
                      <div class="row">
                        <div class="col-lg-3">
                          <div class="form-group">
                            <label for="">{{ __('Discount') }} * </label>
                            <select name="discount_type" class="form-control">
                              <option disabled>{{ __('Select Discount Type') }}</option>
                              <option value="fixed">{{ __('Fixed') }}</option>
                              <option value="percentage">{{ __('Percentage') }}</option>
                            </select>
                          </div>
                        </div>
                        <div class="col-lg-3">
                          <div class="form-group">
                            <label for="">{{ __('Amount') }} * </label>
                            <input type="number" name="early_bird_discount_amount" class="form-control">
                          </div>
                        </div>
                        <div class="col-lg-3">
                          <div class="form-group">
                            <label for="">{{ __('Discount End Date') }} *</label>
                            <input type="date" name="early_bird_discount_date" class="form-control">
                          </div>
                        </div>
                        <div class="col-lg-3">
                          <div class="form-group">
                            <label for="">{{ __('Discount End Time') }} *</label>
                            <input type="time" name="early_bird_discount_time" class="form-control">
                          </div>
                        </div>

                      </div>
                    </div>
                  </div>
                @endif


                <h2 class="tica-builder-section-title"><span>4</span>{{ __('Event content and discovery') }}</h2>
                <div id="accordion" class="mt-3">
                  @foreach ($languages as $language)
                    <div class="version">
                      <div class="version-header" id="heading{{ $language->id }}">
                        <h5 class="mb-0">
                          <button type="button" class="btn btn-link" data-toggle="collapse"
                            data-target="#collapse{{ $language->id }}"
                            aria-expanded="{{ $language->is_default == 1 ? 'true' : 'false' }}"
                            aria-controls="collapse{{ $language->id }}">
                            {{ $language->name . ' ' . __('Language') }}
                            {{ $language->is_default == 1 ? '(' . __('Default') . ')' : '' }}
                          </button>
                        </h5>
                      </div>

                      <div id="collapse{{ $language->id }}"
                        class="collapse {{ $language->is_default == 1 ? 'show' : '' }}"
                        aria-labelledby="heading{{ $language->id }}" data-parent="#accordion">
                        <div class="version-body">
                          <div class="row">
                            <div class="col-lg-6">
                              <div class="form-group {{ $language->direction == 1 ? 'rtl text-right' : '' }}">
                                <label>{{ __('Event Title') . '*' }}</label>
                                <input type="text" class="form-control" name="{{ $language->code }}_title"
                                  placeholder="{{ __('Enter Event Name') }}">
                              </div>
                            </div>

                            <div class="col-lg-6">
                              <div class="form-group {{ $language->direction == 1 ? 'rtl text-right' : '' }}">
                                @php
                                  $categories = DB::table('event_categories')
                                      ->where('language_id', $language->id)
                                      ->where('status', 1)
                                      ->orderBy('serial_number', 'asc')
                                      ->get();
                                @endphp

                                <label for="">{{ __('Category') . '*' }}</label>
                                <select name="{{ $language->code }}_category_id" class="form-control">
                                  <option selected disabled>{{ __('Select Category') }}
                                  </option>

                                  @foreach ($categories as $category)
                                    <option value="{{ $category->id }}">
                                      {{ $category->name }}</option>
                                  @endforeach
                                </select>
                              </div>
                            </div>
                          </div>

                          @if (request()->input('type') == 'venue')
                            <div class="row">
                              <div class="col-lg-8">
                                <div class="form-group">
                                  <label for="">{{ __('Address') . '*' }}</label>
                                  <input type="text" name="{{ $language->code }}_address"
                                    class="form-control {{ $language->direction == 1 ? 'rtl text-right' : '' }}"
                                    placeholder="{{ __('Enter Address') }}">
                                </div>
                              </div>
                              <div class="col-lg-4">
                                <div class="form-group">
                                  <label for="">{{ __('County') . '*' }}</label>
                                  <input type="text" name="{{ $language->code }}_country"
                                    placeholder="{{ __('Enter Country') }}"
                                    class="form-control {{ $language->direction == 1 ? 'rtl text-right' : '' }}">
                                </div>
                              </div>
                              <div class="col-lg-4">
                                <div class="form-group">
                                  <label for="">{{ __('State') }}</label>
                                  <input type="text" name="{{ $language->code }}_state"
                                    class="form-control {{ $language->direction == 1 ? 'rtl text-right' : '' }}"
                                    placeholder="{{ __('Enter State') }}">
                                </div>
                              </div>
                              <div class="col-lg-4">
                                <div class="form-group">
                                  <label for="">{{ __('City') . '*' }}</label>
                                  <input type="text" name="{{ $language->code }}_city"
                                    class="form-control {{ $language->direction == 1 ? 'rtl text-right' : '' }}"
                                    placeholder="Enter City">
                                </div>
                              </div>
                              <div class="col-lg-4">
                                <div class="form-group">
                                  <label for="">{{ __('Zip/Post Code') }}</label>
                                  <input type="text" placeholder="{{ __('Enter Zip/Post Code') }}"
                                    name="{{ $language->code }}_zip_code"
                                    class="form-control {{ $language->direction == 1 ? 'rtl text-right' : '' }}">
                                </div>
                              </div>
                            </div>
                          @endif

                          <div class="row">
                            <div class="col">
                              <div class="form-group {{ $language->direction == 1 ? 'rtl text-right' : '' }}">
                                <label>{{ __('Description') . '*' }}</label>
                                <textarea id="descriptionTmce{{ $language->id }}" class="form-control summernote"
                                  name="{{ $language->code }}_description" placeholder="{{ __('Enter Event Description') }}" data-height="300"></textarea>
                              </div>
                            </div>
                          </div>

                          <div class="row">
                            <div class="col-lg-12">
                              <div class="form-group {{ $language->direction == 1 ? 'rtl text-right' : '' }}">
                                <label>{{ __('Refund Policy') }} *</label>
                                <textarea class="form-control" name="{{ $language->code }}_refund_policy" rows="5"
                                  placeholder="{{ __('Enter Refund Policy') }}"></textarea>
                              </div>
                            </div>
                          </div>

                          <div class="row">
                            <div class="col-lg-12">
                              <div class="form-group {{ $language->direction == 1 ? 'rtl text-right' : '' }}">
                                <label>{{ __('Meta Keywords') }}</label>
                                <input class="form-control" name="{{ $language->code }}_meta_keywords"
                                  placeholder="{{ __('Enter Meta Keywords') }}" data-role="tagsinput">
                              </div>
                            </div>
                          </div>

                          <div class="row">
                            <div class="col-lg-12">
                              <div class="form-group {{ $language->direction == 1 ? 'rtl text-right' : '' }}">
                                <label>{{ __('Meta Description') }}</label>
                                <textarea class="form-control" name="{{ $language->code }}_meta_description" rows="5"
                                  placeholder="{{ __('Enter Meta Description') }}"></textarea>
                              </div>
                            </div>
                          </div>

                          <div class="row">
                            <div class="col">
                              @php $currLang = $language; @endphp

                              @foreach ($languages as $language)
                                @continue($language->id == $currLang->id)

                                <div class="form-check py-0">
                                  <label class="form-check-label">
                                    <input class="form-check-input" type="checkbox"
                                      onchange="cloneInput('collapse{{ $currLang->id }}', 'collapse{{ $language->id }}', event)">
                                    <span class="form-check-sign">{{ __('Clone for') }}
                                      <strong class="text-capitalize text-secondary">{{ $language->name }}</strong>
                                      {{ __('language') }}</span>
                                  </label>
                                </div>
                              @endforeach
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  @endforeach
                </div>

                <div id="sliders"></div>
                <h2 class="tica-builder-section-title"><span>5</span>{{ __('Choose what to set up next') }}</h2>
                <div class="tica-launch-grid" role="radiogroup" aria-label="{{ __('After saving this event') }}">
                  <label class="tica-launch-option"><input type="radio" name="next_step" value="tickets" checked><span class="tica-launch-card"><i class="fal fa-ticket-alt"></i><span><strong>{{ __('Tickets and pricing') }}</strong><small>{{ __('Create ticket types, inventory, sale windows and attendee limits.') }}</small></span></span></label>
                  <label class="tica-launch-option"><input type="radio" name="next_step" value="registration"><span class="tica-launch-card"><i class="fal fa-clipboard-list-check"></i><span><strong>{{ __('Registration form') }}</strong><small>{{ __('Collect custom attendee details, files, sessions and approvals.') }}</small></span></span></label>
                  <label class="tica-launch-option"><input type="radio" name="next_step" value="guests"><span class="tica-launch-card"><i class="fal fa-users"></i><span><strong>{{ __('Guest list') }}</strong><small>{{ __('Invite guests individually or import a list after saving.') }}</small></span></span></label>
                  <label class="tica-launch-option"><input type="radio" name="next_step" value="checkin"><span class="tica-launch-card"><i class="fal fa-qrcode"></i><span><strong>{{ __('Check-in setup') }}</strong><small>{{ __('Prepare scanner access, devices, staff and offline entry workflows.') }}</small></span></span></label>
                  <label class="tica-launch-option"><input type="radio" name="next_step" value="events"><span class="tica-launch-card"><i class="fal fa-calendar-check"></i><span><strong>{{ __('Finish for now') }}</strong><small>{{ __('Return to your events and continue setup later.') }}</small></span></span></label>
                </div>
              </form>
            </div>
          </div>
        </div>

        <div class="card-footer">
          <div class="row">
            <div class="col-12 text-center">
              <button type="submit" id="EventSubmit" class="btn btn-success">
                {{ __('Save and continue') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  </div>
@endsection

@section('script')
  @php
    $languages = App\Models\Language::get();
  @endphp
  <script>
    let languages = "{{ $languages }}";
  </script>
  <script type="text/javascript" src="{{ asset('assets/admin/js/admin-partial.js') }}"></script>
  <script src="{{ asset('assets/admin/js/admin_dropzone.js') }}"></script>
@endsection

@section('variables')
  <script>
    "use strict";
    var storeUrl = "{{ route('organizer.event.imagesstore') }}";
    var removeUrl = "{{ route('organizer.event.imagermv') }}";
    var loadImgs = 0;
  </script>
@endsection
