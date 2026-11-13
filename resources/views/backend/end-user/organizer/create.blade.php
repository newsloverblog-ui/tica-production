@extends('backend.layout')

@section('style')
    <link rel="stylesheet"
        href="{{ asset('assets/tica-ui/css/admin-organizer-create.css') }}?v={{ @filemtime(public_path('assets/tica-ui/css/admin-organizer-create.css')) }}">
@endsection

@section('content')
    <div class="tica-organizer-create">
        <header class="organizer-create-header">
            <div>
                <span class="organizer-create-eyebrow">{{ __('Organizer management') }}</span>
                <h1>{{ __('Create organizer account') }}</h1>
                <p>{{ __('Set up account access, contact details and the public organizer profile in one place.') }}</p>
            </div>
            <a class="organizer-back-link" href="{{ route('admin.organizer_management.registered_organizer') }}">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                <span>{{ __('All organizers') }}</span>
            </a>
        </header>

        <div class="organizer-create-shell">
            <aside class="organizer-identity-panel" aria-labelledby="organizer-photo-heading">
                <div class="identity-panel-content">
                    <div class="identity-copy">
                        <span class="identity-kicker">{{ __('Identity') }}</span>
                        <h2 id="organizer-photo-heading">{{ __('Organizer photo') }}</h2>
                        <p class="identity-panel-copy">{{ __('Use a clear square photo or brand mark that is easy to recognize.') }}</p>
                    </div>

                    <div class="photo-preview">
                        <img src="{{ asset('assets/admin/img/noimage.jpg') }}" alt="{{ __('Organizer photo preview') }}"
                            class="uploaded-img" onload="this.style.opacity='1'" onerror="this.style.opacity='0'">
                    </div>

                    <div class="upload-controls">
                        <label class="organizer-upload">
                            <i class="fas fa-cloud-upload-alt" aria-hidden="true"></i>
                            <span id="organizerUploadLabel">{{ __('Choose photo') }}</span>
                            <input type="file" class="img-input" name="photo" form="eventForm"
                                accept="image/png,image/jpeg,image/webp" aria-label="{{ __('Choose organizer photo') }}">
                        </label>
                        <p class="upload-meta">
                            <i class="fas fa-info-circle" aria-hidden="true"></i>
                            <span>{{ __('JPG, PNG or WebP · 300 × 300 px recommended') }}</span>
                        </p>
                        <p id="editErr_photo" class="mb-0 text-danger em field-error"></p>
                    </div>

                    <div class="identity-note">
                        <i class="fas fa-star" aria-hidden="true"></i>
                        <span>{{ __('A consistent profile helps admins identify organizer activity at a glance.') }}</span>
                    </div>
                </div>
            </aside>

            <form id="eventForm" class="organizer-form-area"
                action="{{ route('admin.organizer_management.save-organizer') }}" method="post"
                enctype="multipart/form-data">
                @csrf

                <div class="organizer-error-summary alert alert-danger dis-none" id="eventErrors" role="alert">
                    <button type="button" class="close" data-dismiss="alert" aria-label="{{ __('Close') }}">×</button>
                    <strong>{{ __('Please review the highlighted information.') }}</strong>
                    <ul class="mt-2"></ul>
                </div>

                <section class="organizer-section" aria-labelledby="account-access-heading">
                    <div class="section-heading">
                        <span class="section-number" aria-hidden="true">01</span>
                        <div>
                            <h2 id="account-access-heading">{{ __('Account access') }}</h2>
                            <p>{{ __('Credentials and contact details used to access the organizer dashboard.') }}</p>
                        </div>
                    </div>

                    <div class="organizer-fields">
                        <div class="form-group">
                            <label for="organizerEmail">{{ __('Email address') }} <span class="required-mark">*</span></label>
                            <input id="organizerEmail" type="email" class="form-control" name="email"
                                value="{{ old('email') }}" placeholder="name@example.com" autocomplete="email">
                            <p id="editErr_email" class="mb-0 text-danger em field-error"></p>
                        </div>

                        <div class="form-group">
                            <label for="organizerPhone">{{ __('Phone number') }}</label>
                            <input id="organizerPhone" type="tel" class="form-control" name="phone"
                                value="{{ old('phone') }}" placeholder="{{ __('Enter phone number') }}" autocomplete="tel">
                            <p id="editErr_phone" class="mb-0 text-danger em field-error"></p>
                        </div>

                        <div class="form-group">
                            <label for="organizerUsername">{{ __('Username') }} <span class="required-mark">*</span></label>
                            <input id="organizerUsername" type="text" class="form-control" name="username"
                                value="{{ old('username') }}" placeholder="{{ __('Choose a username') }}" autocomplete="username">
                            <p id="editErr_username" class="mb-0 text-danger em field-error"></p>
                        </div>

                        <div class="form-group">
                            <label for="organizerPassword">{{ __('Password') }} <span class="required-mark">*</span></label>
                            <input id="organizerPassword" type="password" class="form-control" name="password"
                                placeholder="{{ __('Create a secure password') }}" autocomplete="new-password">
                            <p id="editErr_password" class="mb-0 text-danger em field-error"></p>
                        </div>

                        <div class="form-group">
                            <label for="organizerPasswordConfirmation">{{ __('Confirm password') }} <span class="required-mark">*</span></label>
                            <input id="organizerPasswordConfirmation" type="password" class="form-control"
                                name="password_confirmation" placeholder="{{ __('Repeat the password') }}"
                                autocomplete="new-password">
                            <small class="field-help">{{ __('Use at least 8 characters with uppercase, lowercase and a number.') }}</small>
                            <p id="editErr_password_confirmation" class="mb-0 text-danger em field-error"></p>
                        </div>
                    </div>
                </section>

                <section class="organizer-section" aria-labelledby="public-links-heading">
                    <div class="section-heading">
                        <span class="section-number" aria-hidden="true">02</span>
                        <div>
                            <h2 id="public-links-heading">{{ __('Public links') }}</h2>
                            <p>{{ __('Optional social profiles shown with organizer information.') }}</p>
                        </div>
                    </div>

                    <div class="organizer-fields organizer-fields--three">
                        <div class="form-group">
                            <label for="organizerFacebook">{{ __('Facebook') }}</label>
                            <input id="organizerFacebook" type="url" class="form-control" name="facebook"
                                value="{{ old('facebook') }}" placeholder="https://facebook.com/...">
                        </div>
                        <div class="form-group">
                            <label for="organizerTwitter">{{ __('X / Twitter') }}</label>
                            <input id="organizerTwitter" type="url" class="form-control" name="twitter"
                                value="{{ old('twitter') }}" placeholder="https://x.com/...">
                        </div>
                        <div class="form-group">
                            <label for="organizerLinkedin">{{ __('LinkedIn') }}</label>
                            <input id="organizerLinkedin" type="url" class="form-control" name="linkedin"
                                value="{{ old('linkedin') }}" placeholder="https://linkedin.com/in/...">
                        </div>
                    </div>
                </section>

                <section class="organizer-section" aria-labelledby="profile-details-heading">
                    <div class="section-heading">
                        <span class="section-number" aria-hidden="true">03</span>
                        <div>
                            <h2 id="profile-details-heading">{{ __('Organizer profile') }}</h2>
                            <p>{{ __('Add the organizer details visitors and administrators will see.') }}</p>
                        </div>
                    </div>

                    <div id="accordion" class="language-stack">
                        @foreach ($languages as $language)
                            <div class="language-panel version">
                                <div class="version-header" id="heading{{ $language->id }}">
                                    <h3 class="mb-0">
                                        <button type="button" class="btn btn-link" data-toggle="collapse"
                                            data-target="#collapse{{ $language->id }}"
                                            aria-expanded="{{ $language->is_default == 1 ? 'true' : 'false' }}"
                                            aria-controls="collapse{{ $language->id }}">
                                            <span class="language-title">
                                                <i class="fas fa-language" aria-hidden="true"></i>
                                                <span>{{ $language->name . __(' profile') }}</span>
                                                @if ($language->is_default == 1)
                                                    <span class="language-default-badge">{{ __('Default') }}</span>
                                                @endif
                                            </span>
                                            <i class="fas fa-chevron-down language-chevron" aria-hidden="true"></i>
                                        </button>
                                    </h3>
                                </div>

                                <div id="collapse{{ $language->id }}"
                                    class="collapse {{ $language->is_default == 1 ? 'show' : '' }}"
                                    aria-labelledby="heading{{ $language->id }}" data-parent="#accordion">
                                    <div class="version-body">
                                        <div class="language-fields">
                                            <div class="form-group {{ $language->direction == 1 ? 'rtl text-right' : '' }}">
                                                <label for="{{ $language->code }}Name">{{ __('Display name') }} <span class="required-mark">*</span></label>
                                                <input id="{{ $language->code }}Name" type="text" class="form-control"
                                                    name="{{ $language->code }}_name"
                                                    value="{{ old($language->code . '_name') }}"
                                                    placeholder="{{ __('Enter organizer name') }}">
                                                @if ($errors->has($language->code . '_name'))
                                                    <p class="mb-0 text-danger field-error">{{ $errors->first($language->code . '_name') }}</p>
                                                @endif
                                                <p id="editErr_{{ $language->code }}_name" class="mb-0 text-danger em field-error"></p>
                                            </div>
                                            <div class="form-group">
                                                <label for="{{ $language->code }}Country">{{ __('Country') }}</label>
                                                <input id="{{ $language->code }}Country" type="text" class="form-control"
                                                    name="{{ $language->code }}_country"
                                                    value="{{ old($language->code . '_country') }}"
                                                    placeholder="{{ __('Enter country') }}">
                                            </div>

                                            <div class="form-group">
                                                <label for="{{ $language->code }}City">{{ __('City') }}</label>
                                                <input id="{{ $language->code }}City" type="text" class="form-control"
                                                    name="{{ $language->code }}_city"
                                                    value="{{ old($language->code . '_city') }}"
                                                    placeholder="{{ __('Enter city') }}">
                                            </div>

                                            <div class="form-group">
                                                <label for="{{ $language->code }}State">{{ __('State / Region') }}</label>
                                                <input id="{{ $language->code }}State" type="text" class="form-control"
                                                    name="{{ $language->code }}_state"
                                                    value="{{ old($language->code . '_state') }}"
                                                    placeholder="{{ __('Enter state or region') }}">
                                            </div>

                                            <div class="form-group">
                                                <label for="{{ $language->code }}Zip">{{ __('Postal code') }}</label>
                                                <input id="{{ $language->code }}Zip" type="text" class="form-control"
                                                    name="{{ $language->code }}_zip_code"
                                                    value="{{ old($language->code . '_zip_code') }}"
                                                    placeholder="{{ __('Enter postal code') }}">
                                            </div>

                                            <div class="form-group field-span-full">
                                                <label for="{{ $language->code }}Address">{{ __('Address') }}</label>
                                                <textarea id="{{ $language->code }}Address" name="{{ $language->code }}_address" rows="3"
                                                    class="form-control" placeholder="{{ __('Enter business address') }}">{{ old($language->code . '_address') }}</textarea>
                                            </div>

                                            <div class="form-group field-span-full">
                                                <label for="{{ $language->code }}Details">{{ __('About the organizer') }}</label>
                                                <textarea id="{{ $language->code }}Details" name="{{ $language->code }}_details" rows="5"
                                                    class="form-control" placeholder="{{ __('Add a short organizer description') }}">{{ old($language->code . '_details') }}</textarea>
                                            </div>
                                        </div>

                                        @php $currLang = $language; @endphp
                                        @if ($languages->count() > 1)
                                            <div class="clone-options">
                                                <span class="clone-label">{{ __('Copy these details to:') }}</span>
                                                @foreach ($languages as $targetLanguage)
                                                    @continue($targetLanguage->id == $currLang->id)
                                                    <label class="clone-choice">
                                                        <input type="checkbox"
                                                            onchange="cloneInput('collapse{{ $currLang->id }}', 'collapse{{ $targetLanguage->id }}', event)">
                                                        <span>{{ $targetLanguage->name }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <footer class="organizer-form-actions">
                    <p class="action-note">
                        <i class="fas fa-lock" aria-hidden="true"></i>
                        <span>{{ __('Account credentials are stored securely.') }}</span>
                    </p>
                    <div class="action-buttons">
                        <a class="btn btn-cancel" href="{{ route('admin.organizer_management.registered_organizer') }}">
                            {{ __('Cancel') }}
                        </a>
                        <button type="submit" id="EventSubmit" class="btn btn-create">
                            <i class="fas fa-user-plus" aria-hidden="true"></i>
                            <span>{{ __('Create organizer') }}</span>
                        </button>
                    </div>
                </footer>
            </form>
        </div>
    </div>
@endsection

@section('script')
    <script>
        (function () {
            'use strict';
            var photoInput = document.querySelector('.tica-organizer-create .img-input');
            var uploadLabel = document.getElementById('organizerUploadLabel');

            if (photoInput && uploadLabel) {
                photoInput.addEventListener('change', function (event) {
                    var file = event.target.files && event.target.files[0];
                    uploadLabel.textContent = file ? file.name : @json(__('Choose photo'));
                });
            }
        }());
    </script>
@endsection
