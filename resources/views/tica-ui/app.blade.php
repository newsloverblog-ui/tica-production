@php
  $adminShell = !empty($payload['adminBuilder']);
  $ticaBrand = \App\Support\TicaSite::get('branding');
  $ticaFavicon = !empty($ticaBrand['favicon']) ? \App\Support\TicaSite::imageUrl($ticaBrand['favicon']) : null;
@endphp
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $meta['title'] }}</title>
<meta name="description" content="{{ $meta['description'] }}"><meta property="og:title" content="{{ $meta['ogTitle'] }}"><meta property="og:description" content="{{ $meta['ogDescription'] }}"><meta property="og:type" content="website">@if(!empty($meta['image']))<meta property="og:image" content="{{ $meta['image'] }}">@endif<meta property="og:url" content="{{ url()->current() }}"><link rel="canonical" href="{{ url()->current() }}"><meta name="twitter:card" content="summary_large_image"><meta name="theme-color" content="{{ $ticaBrand['secondary_color'] }}"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="robots" content="{{ $meta['robots'] }}">@if($ticaFavicon)<link rel="icon" href="{{ $ticaFavicon }}">@else<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%230F4C9E'/%3E%3Cpath d='M9 10h14v3h-5.500v11h-3V13H9z' fill='%23F97316'/%3E%3C/svg%3E">@endif
<script>window.TICA_FB="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 800 600' preserveAspectRatio='xMidYMid slice'%3E%3Crect width='800' height='600' fill='%23F1F3F6'/%3E%3Cg fill='none' stroke='%23A3ADBD' stroke-width='10' stroke-linecap='round' stroke-linejoin='round' transform='translate(340 240)'%3E%3Crect x='0' y='0' width='120' height='96' rx='12'/%3E%3Ccircle cx='38' cy='34' r='12'/%3E%3Cpath d='M0 80l36-32 28 22 22-18 34 28'/%3E%3C/g%3E%3C/svg%3E";document.addEventListener("error",function(e){var t=e.target;if(t&&t.tagName==="IMG"&&!t.dataset.fb){t.dataset.fb=1;t.removeAttribute("srcset");t.src=window.TICA_FB}},true);</script>
<link rel="stylesheet" href="{{ asset('assets/tica-ui/css/tica.css') }}?v={{ $assetVersion }}">
<link rel="stylesheet" href="{{ asset('assets/tica-ui/css/ds.css') }}?v={{ $assetVersion }}">
<link rel="preload" href="{{ asset('assets/tica-ui/fonts/inter-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{{ asset('assets/tica-ui/css/human-ui.css') }}?v={{ tica_asset_version('assets/tica-ui/css/human-ui.css') }}">
<link rel="stylesheet" href="{{ asset('assets/tica-ui/css/shell.css') }}?v={{ $assetVersion }}">
<style>:root{--tica-brand-primary:{{ $ticaBrand['primary_color'] }};--tica-brand-deep:{{ $ticaBrand['secondary_color'] }};--tica-brand-accent:{{ $ticaBrand['accent_color'] }};--indigo-2:var(--tica-brand-primary);--indigo:var(--tica-brand-deep);--ink:var(--tica-brand-deep);--camwood:var(--tica-brand-accent);--accent-500:var(--tica-brand-accent)}</style>
@if ($adminShell)
@includeIf('backend.partials.styles')
<style>
body.tica-admin-event-builder .main-panel>.content{background:#f5f7fb}
body.tica-admin-event-builder #tica-main-content{padding:24px 28px 48px}
body.tica-admin-event-builder #main{min-width:0}
body.tica-admin-event-builder .page[data-page=create]{display:block!important}
body.tica-admin-event-builder .page[data-page=create]>.ce-shell{display:block}
body.tica-admin-event-builder .page[data-page=create] .ce-side{display:none!important}
body.tica-admin-event-builder .page[data-page=create] .ce-shell-main>.tint.full{min-height:0;padding:0;background:transparent}
body.tica-admin-event-builder .page[data-page=create] .wrap.pagehead.creategrid{width:100%;max-width:none;margin:0;padding:0}
@media(max-width:767px){body.tica-admin-event-builder #tica-main-content{padding:18px 14px 36px}}
</style>
@endif
<style id="tica-first-page">.page:not([data-page="{{ $initialPage }}"]){display:none}</style>
@if(!empty($structuredData))<script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>@endif
</head>
<body class="{{ $adminShell ? 'tica-control-center tica-admin tica-admin-event-builder' : (Auth::guard('organizer')->check() && in_array($initialPage, ['dashboard', 'create', 'checkin'], true) ? 'orgshell t-app' : '') }}" data-background-color="white">
@if ($adminShell)
<a class="tica-skip-link" href="#tica-main-content">{{ __('Skip to main content') }}</a>
<div class="wrapper">
@includeIf('backend.partials.top-navbar')
@includeIf('backend.partials.side-navbar')
<div class="main-panel"><div class="content"><main class="page-inner" id="tica-main-content" tabindex="-1"><div id="main">
@else
@include('tica-ui.partials.header')

@php($orgShell = Auth::guard('organizer')->check() && empty($payload['adminBuilder']))
@if ($orgShell)
<x-shell.organizer :active="$initialPage === 'checkin' ? 'checkin' : ($initialPage === 'create' ? 'events' : (request()->segment(2) ?: 'overview'))" :spa="true" :title="request()->routeIs('tica.edit') ? 'Edit event' : ($initialPage === 'create' ? 'Create an event' : null)" />
@endif
<main id="main" tabindex="-1">
@endif

@foreach (['home', 'discover', 'event', 'checkout', 'ticket', 'how', 'pricing', 'about', 'contact', 'join', 'create', 'policies', 'tickets', 'dashboard', 'account', 'checkin', 'login', 'signup', 'forgot', 'missing'] as $designPage)
@include('tica-ui.pages.' . $designPage)
@endforeach
@if (!empty($content))
@include('tica-ui.pages.content', ['content' => $content])
@endif
@if ($adminShell)
</div></main></div></div></div>
@else
</main>
@include('tica-ui.partials.footer')
@endif
<script>
window.TICA_DATA={!! json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
window.TICA_EVENTS=window.TICA_DATA.events;window.TICA_USER=window.TICA_DATA.user;window.TICA_ORG=!!window.TICA_DATA.organizer;window.TICA_ADMIN=!!window.TICA_DATA.adminBuilder;window.TICA_ORGNAME=window.TICA_DATA.organizer?window.TICA_DATA.organizer.name:null;window.TICA_CITY=window.TICA_DATA.city||null;
window.TICA_HEART="<svg viewBox=\"0 0 24 24\" aria-hidden=\"true\"><path d=\"M12 20.5s-8-4.9-8-11A4.5 4.5 0 0 1 12 6.7a4.5 4.5 0 0 1 8 2.8c0 6.1-8 11-8 11z\"/><\/svg>";window.TICA_TICK="<svg viewBox=\"0 0 16 16\" aria-label=\"Verified organiser\" role=\"img\"><circle cx=\"8\" cy=\"8\" r=\"8\" fill=\"#CFCBF0\"/><path d=\"M4.5 8.2l2.4 2.3 4.6-5\" stroke=\"#1E1763\" stroke-width=\"2\" fill=\"none\"/><\/svg>";
</script>
<script src="{{ asset('assets/tica-ui/js/qrcode.js') }}?v={{ $assetVersion }}"></script>
<script src="{{ asset('assets/tica-ui/js/tica.js') }}?v={{ $assetVersion }}"></script>
@if ($adminShell)
@includeIf('backend.partials.scripts')
@endif
</body></html>
