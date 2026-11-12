<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{ $eventTitle }} badges</title>
  <style>
    @page { margin: 0; }
    body { margin: 0; color: #071224; font-family: DejaVu Sans, sans-serif; }
    .badge-page { position: relative; width: 100%; height: 100%; min-height: 520px; overflow: hidden; page-break-after: always; background: #fff; }
    .badge-page:last-child { page-break-after: auto; }
    .accent { height: 24px; background: {{ $setting->accent_color }}; }
    .content { padding: 34px 28px 24px; text-align: center; }
    .event { min-height: 34px; color: #667085; font-size: 13px; font-weight: bold; letter-spacing: .08em; text-transform: uppercase; }
    h1 { margin: 22px 0 8px; font-size: 29px; line-height: 1.08; }
    .company { margin: 0 0 4px; font-size: 16px; font-weight: bold; }
    .role { margin: 0; color: #667085; font-size: 13px; }
    .qr { width: 158px; height: 158px; margin: 28px auto 12px; }
    .qr img { width: 100%; height: 100%; }
    .type { display: inline-block; padding: 7px 12px; border-radius: 999px; color: #071224; background: {{ $setting->accent_color }}; font-size: 11px; font-weight: bold; }
    .ref { margin-top: 13px; color: #98a2b3; font-size: 8px; }
    .footer { position: absolute; right: 0; bottom: 0; left: 0; padding: 11px; color: #fff; background: #071224; font-size: 10px; text-align: center; }
  </style>
</head>
<body>
  @foreach ($badges as $badge)
    <section class="badge-page">
      <div class="accent"></div>
      <div class="content">
        <div class="event">{{ $setting->badge_title ?: $eventTitle }}</div>
        <h1>{{ $badge['name'] }}</h1>
        @if ($badge['company'])<p class="company">{{ $badge['company'] }}</p>@endif
        @if ($badge['role'])<p class="role">{{ $badge['role'] }}</p>@endif
        <div class="qr"><img src="{{ $badge['qr_data_uri'] }}" alt=""></div>
        @if ($badge['registration_type'])<span class="type">{{ $badge['registration_type'] }}</span>@endif
        <div class="ref">{{ $badge['reference'] }}</div>
      </div>
      <div class="footer">Scan at the event entrance - issued by Tica</div>
    </section>
  @endforeach
</body>
</html>
