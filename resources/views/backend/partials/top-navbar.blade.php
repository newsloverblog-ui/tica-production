@php
  $topbarAdmin = Auth::guard('admin')->user();
  $topbarAdminName = trim(($topbarAdmin->first_name ?? '') . ' ' . ($topbarAdmin->last_name ?? ''));
  $topbarAdminName = $topbarAdminName !== '' ? $topbarAdminName : ($topbarAdmin->username ?? __('Administrator'));
  $topbarAdminParts = preg_split('/\s+/', $topbarAdminName, -1, PREG_SPLIT_NO_EMPTY);
  $topbarAdminInitials = '';
  foreach (array_slice($topbarAdminParts ?: [], 0, 2) as $topbarAdminPart) {
    $topbarAdminInitials .= mb_strtoupper(mb_substr($topbarAdminPart, 0, 1));
  }
  $topbarAdminImage = $topbarAdmin->image ? 'assets/admin/img/admins/' . $topbarAdmin->image : null;
  $topbarAdminHasImage = !empty($topbarAdminImage);
@endphp

<div class="main-header">
  <!-- Logo Header Start -->
  <div class="logo-header" data-background-color="white">
    <a href="{{ route('index') }}" class="logo" target="_blank" aria-label="{{ __('Open the TICA website') }}">
      <img src="{{ asset('assets/tica-ui/img/tica-logo.png') }}" alt="TICA" class="navbar-brand">
    </a>

    <button class="navbar-toggler sidenav-toggler ml-auto" type="button" data-toggle="collapse" data-target="collapse"
      aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon">
        <i class="icon-menu"></i>
      </span>
    </button>
    <button class="topbar-toggler more"><i class="icon-options-vertical"></i></button>

    <div class="nav-toggle">
      <button class="btn btn-toggle toggle-sidebar">
        <i class="icon-menu"></i>
      </button>
    </div>
  </div>
  <!-- Logo Header End -->

  <!-- Navbar Header Start -->
  <nav class="navbar navbar-header navbar-expand-lg"
    data-background-color="white2">
    <div class="container-fluid">
      <div class="tica-topbar-context d-none d-md-flex">
        <span class="tica-topbar-kicker">{{ __('Tica') }}</span>
        <strong>{{ __('Admin control center') }}</strong>
        <a href="{{ route('index') }}" target="_blank" rel="noopener">{{ __('View website') }} <i class="fal fa-external-link ml-1" aria-hidden="true"></i></a>
      </div>
      <ul class="navbar-nav topbar-nav ml-md-auto align-items-center">
        <form action="{{ route('admin.change_theme') }}" class="form-inline mr-3" method="POST">

          @csrf
          <div class="form-group">
            <div class="selectgroup selectgroup-secondary selectgroup-pills" role="group" aria-label="{{ __('Color theme') }}">
              <label class="selectgroup-item">
                <input type="radio" name="admin_theme_version" value="light" class="selectgroup-input"
                  {{ $settings->admin_theme_version == 'light' ? 'checked' : '' }} onchange="this.form.submit()">
                <span class="selectgroup-button selectgroup-button-icon"><i class="fa fa-sun"></i></span>
              </label>

              <label class="selectgroup-item">
                <input type="radio" name="admin_theme_version" value="dark" class="selectgroup-input"
                  {{ $settings->admin_theme_version == 'dark' ? 'checked' : '' }} onchange="this.form.submit()">
                <span class="selectgroup-button selectgroup-button-icon"><i class="fa fa-moon"></i></span>
              </label>
            </div>
          </div>
        </form>

        <li class="nav-item dropdown hidden-caret">
          <a class="dropdown-toggle profile-pic" data-toggle="dropdown" href="#" aria-expanded="false" aria-label="{{ __('Open admin account menu') }}">
            <div class="avatar-sm">
              <span class="tica-admin-initial-avatar" aria-hidden="true">{{ $topbarAdminInitials ?: 'TA' }}</span>
              @if ($topbarAdminHasImage)
                <img src="{{ asset($topbarAdminImage) }}"
                  alt="" class="avatar-img rounded-circle" onerror="this.remove()">
              @endif
            </div>
          </a>

          <ul class="dropdown-menu dropdown-user animated fadeIn">
            <div class="dropdown-user-scroll scrollbar-outer">
              <li>
                <div class="user-box">
                  <div class="avatar-lg">
                    <span class="tica-admin-initial-avatar" aria-hidden="true">{{ $topbarAdminInitials ?: 'TA' }}</span>
                    @if ($topbarAdminHasImage)
                      <img src="{{ asset($topbarAdminImage) }}"
                        alt="" class="avatar-img rounded-circle" onerror="this.remove()">
                    @endif
                  </div>

                  <div class="u-text">
                    <h4>
                      {{ $topbarAdminName }}
                    </h4>
                    <p class="text-muted">{{ Auth::guard('admin')->user()->email }}</p>
                  </div>
                </div>
              </li>

              <li>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="{{ route('admin.edit_profile') }}">
                  {{ __('Edit Profile') }}
                </a>

                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="{{ route('admin.change_password') }}">
                  {{ __('Change Password') }}
                </a>

                <div class="dropdown-divider"></div>
                <form method="POST" action="{{ route('admin.logout') }}">
                  @csrf
                  <button type="submit" class="dropdown-item">{{ __('Logout') }}</button>
                </form>
              </li>
            </div>
          </ul>
        </li>
      </ul>
    </div>
  </nav>
  <!-- Navbar Header End -->
</div>
