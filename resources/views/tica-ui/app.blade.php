<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $meta['title'] }}</title>
<meta name="description" content="{{ $meta['description'] }}"><meta property="og:title" content="{{ $meta['ogTitle'] }}"><meta property="og:description" content="{{ $meta['ogDescription'] }}"><meta property="og:type" content="website">@if(!empty($meta['image']))<meta property="og:image" content="{{ $meta['image'] }}">@endif<meta property="og:url" content="{{ url()->current() }}"><link rel="canonical" href="{{ url()->current() }}"><meta name="twitter:card" content="summary_large_image"><meta name="theme-color" content="#0B2447"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="robots" content="{{ $meta['robots'] }}"><link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%230F4C9E'/%3E%3Cpath d='M9 10h14v3h-5.500v11h-3V13H9z' fill='%23F97316'/%3E%3C/svg%3E">
<script>window.TICA_FB="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 800 600' preserveAspectRatio='xMidYMid slice'%3E%3Cdefs%3E%3ClinearGradient id='a' x1='0' y1='0' x2='1' y2='1'%3E%3Cstop offset='0' stop-color='%231A56B0'/%3E%3Cstop offset='1' stop-color='%230B2447'/%3E%3C/linearGradient%3E%3CradialGradient id='b' cx='.78' cy='.12' r='.6'%3E%3Cstop offset='0' stop-color='%23F97316' stop-opacity='.55'/%3E%3Cstop offset='1' stop-color='%23F97316' stop-opacity='0'/%3E%3C/radialGradient%3E%3ClinearGradient id='c' x1='0' y1='0' x2='0' y2='1'%3E%3Cstop offset='0' stop-color='%23fff' stop-opacity='.22'/%3E%3Cstop offset='1' stop-color='%23fff' stop-opacity='0'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='800' height='600' fill='url(%23a)'/%3E%3Crect width='800' height='600' fill='url(%23b)'/%3E%3Cpath d='M300 0L80 600h120zM470 0l40 600h130zM620 0L760 600h40z' fill='url(%23c)'/%3E%3Cg fill='%23081a33' opacity='.85'%3E%3Cpath d='M0 600v-70c40-25 60-45 90-45s45 30 70 30 40-60 75-60 45 50 80 50 40-40 75-40 50 55 85 55 45-35 80-35 50 45 85 45 40-55 80-55 50 40 80 40v115z'/%3E%3C/g%3E%3C/svg%3E";document.addEventListener("error",function(e){var t=e.target;if(t&&t.tagName==="IMG"&&!t.dataset.fb){t.dataset.fb=1;t.removeAttribute("srcset");t.src=window.TICA_FB}},true);</script>
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
