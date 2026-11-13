<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $meta['title'] }}</title>
<meta name="description" content="{{ $meta['description'] }}"><meta property="og:title" content="{{ $meta['ogTitle'] }}"><meta property="og:description" content="{{ $meta['ogDescription'] }}"><meta property="og:type" content="website">@if(!empty($meta['image']))<meta property="og:image" content="{{ $meta['image'] }}">@endif<meta property="og:url" content="{{ url()->current() }}"><link rel="canonical" href="{{ url()->current() }}"><meta name="twitter:card" content="summary_large_image"><meta name="theme-color" content="#0B2447"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="robots" content="{{ $meta['robots'] }}"><link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%230F4C9E'/%3E%3Cpath d='M9 10h14v3h-5.500v11h-3V13H9z' fill='%23F97316'/%3E%3C/svg%3E">
<script>window.TICA_FB="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 800 600' preserveAspectRatio='xMidYMid slice'%3E%3Crect width='800' height='600' fill='%23F1F3F6'/%3E%3Cg fill='none' stroke='%23A3ADBD' stroke-width='10' stroke-linecap='round' stroke-linejoin='round' transform='translate(340 240)'%3E%3Crect x='0' y='0' width='120' height='96' rx='12'/%3E%3Ccircle cx='38' cy='34' r='12'/%3E%3Cpath d='M0 80l36-32 28 22 22-18 34 28'/%3E%3C/g%3E%3C/svg%3E";document.addEventListener("error",function(e){var t=e.target;if(t&&t.tagName==="IMG"&&!t.dataset.fb){t.dataset.fb=1;t.removeAttribute("srcset");t.src=window.TICA_FB}},true);</script>
<link rel="stylesheet" href="{{ asset('assets/tica-ui/css/tica.css') }}?v={{ $assetVersion }}">
<link rel="stylesheet" href="{{ asset('assets/tica-ui/css/ds.css') }}?v={{ $assetVersion }}">
<style id="tica-first-page">.page:not([data-page="{{ $initialPage }}"]){display:none}</style>
@if(!empty($structuredData))<script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>@endif
</head>
<body class="{{ Auth::guard('organizer')->check() && in_array($initialPage, ['dashboard', 'create', 'checkin'], true) && empty($payload['adminBuilder']) ? 'orgshell t-app' : '' }}">
@include('tica-ui.partials.header')

@php($orgShell = Auth::guard('organizer')->check() && empty($payload['adminBuilder']))
@if ($orgShell)
<x-shell.organizer :active="$initialPage === 'checkin' ? 'checkin' : ($initialPage === 'create' ? 'events' : (request()->segment(2) ?: 'overview'))" :spa="true" :title="request()->routeIs('tica.edit') ? 'Edit event' : ($initialPage === 'create' ? 'Create an event' : null)" />
@endif
<main id="main" tabindex="-1">

@foreach (['home', 'discover', 'event', 'checkout', 'ticket', 'how', 'pricing', 'about', 'contact', 'join', 'create', 'policies', 'tickets', 'dashboard', 'account', 'checkin', 'login', 'signup', 'forgot', 'missing'] as $designPage)
@include('tica-ui.pages.' . $designPage)
@endforeach
@if (!empty($content))
@include('tica-ui.pages.content', ['content' => $content])
@endif
</main>
@include('tica-ui.partials.footer')
<script>
window.TICA_DATA={!! json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
window.TICA_EVENTS=window.TICA_DATA.events;window.TICA_USER=window.TICA_DATA.user;window.TICA_ORG=!!window.TICA_DATA.organizer;window.TICA_ADMIN=!!window.TICA_DATA.adminBuilder;window.TICA_ORGNAME=window.TICA_DATA.organizer?window.TICA_DATA.organizer.name:null;window.TICA_CITY=window.TICA_DATA.city||null;
window.TICA_HEART="<svg viewBox=\"0 0 24 24\" aria-hidden=\"true\"><path d=\"M12 20.5s-8-4.9-8-11A4.5 4.5 0 0 1 12 6.7a4.5 4.5 0 0 1 8 2.8c0 6.1-8 11-8 11z\"/><\/svg>";window.TICA_TICK="<svg viewBox=\"0 0 16 16\" aria-label=\"Verified organiser\" role=\"img\"><circle cx=\"8\" cy=\"8\" r=\"8\" fill=\"#CFCBF0\"/><path d=\"M4.5 8.2l2.4 2.3 4.6-5\" stroke=\"#1E1763\" stroke-width=\"2\" fill=\"none\"/><\/svg>";
</script>
<script src="{{ asset('assets/tica-ui/js/qrcode.js') }}?v={{ $assetVersion }}"></script>
<script src="{{ asset('assets/tica-ui/js/tica.js') }}?v={{ $assetVersion }}"></script>
</body></html>
