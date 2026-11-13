<?php

/*
| Event category icons.
|
| One source of truth for the icon an admin picks for a category. The admin
| picker, the admin category list and the public site (home rail, Discover)
| all read from here. Each icon is the inner markup of a 24x24 stroke SVG.
*/

return [
  'party' => ['label' => 'Party & nightlife', 'svg' => '<path d="M5 3h14l-7 9-7-9z"/><path d="M12 12v8M8 21h8"/>'],
  'music' => ['label' => 'Music & concerts', 'svg' => '<path d="M9 18V6l10-2v12"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="16.5" cy="16" r="2.5"/>'],
  'wedding' => ['label' => 'Weddings', 'svg' => '<circle cx="9" cy="14" r="5"/><circle cx="15" cy="14" r="5"/><path d="M10 4l2 3 2-3"/>'],
  'conference' => ['label' => 'Conferences', 'svg' => '<rect x="3" y="4" width="18" height="12" rx="1"/><path d="M12 16v5M8 21h8"/>'],
  'business' => ['label' => 'Business', 'svg' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M3 13h18"/>'],
  'career' => ['label' => 'Career & growth', 'svg' => '<path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/>'],
  'campus' => ['label' => 'Campus & education', 'svg' => '<path d="M3 9l9-4 9 4-9 4-9-4z"/><path d="M7 11v5c3 2 7 2 10 0v-5"/>'],
  'tech' => ['label' => 'Tech', 'svg' => '<path d="M8 8l-4 4 4 4M16 8l4 4-4 4M14 5l-4 14"/>'],
  'sport' => ['label' => 'Sports & fitness', 'svg' => '<circle cx="14" cy="5" r="2"/><path d="M5 20l4-6 3 2 2-5-4-2-3 3M14 11l3 3h3"/>'],
  'health' => ['label' => 'Health & wellness', 'svg' => '<path d="M20.8 8.6a5 5 0 0 0-8.8-3.2 5 5 0 0 0-8.8 3.2c0 5.4 8.8 11.4 8.8 11.4s8.8-6 8.8-11.4z"/>'],
  'faith' => ['label' => 'Faith', 'svg' => '<path d="M12 21c-4-3-6-6-6-9 0-3 3-6 6-9 3 3 6 6 6 9 0 3-2 6-6 9z"/>'],
  'arts' => ['label' => 'Arts & culture', 'svg' => '<circle cx="12" cy="12" r="9"/><circle cx="8.5" cy="10" r="1"/><circle cx="14" cy="8" r="1"/><circle cx="16" cy="13" r="1"/>'],
  'comedy' => ['label' => 'Comedy & talks', 'svg' => '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3M8 21h8"/>'],
  'film' => ['label' => 'Film & screenings', 'svg' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 5v14M17 5v14M3 9h4M3 15h4M17 9h4M17 15h4"/>'],
  'food' => ['label' => 'Food & drink', 'svg' => '<path d="M7 3v8a2 2 0 0 0 2 2v8M5 3v5a2 2 0 0 0 2 2M9 3v5a2 2 0 0 1-2 2M17 3c-2 2-2 6 0 8v10"/>'],
  'fashion' => ['label' => 'Fashion & beauty', 'svg' => '<path d="M10 6a2 2 0 1 1 2 2v2L3 17h18l-9-7"/>'],
  'family' => ['label' => 'Kids & family', 'svg' => '<circle cx="8" cy="7" r="3"/><circle cx="17" cy="9" r="2"/><path d="M3 20v-2a5 5 0 0 1 10 0v2M14 20v-1a3 3 0 0 1 6 0v1"/>'],
  'outing' => ['label' => 'Tours & outings', 'svg' => '<path d="M12 21s-7-6-7-11a7 7 0 0 1 14 0c0 5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>'],
  'ticket' => ['label' => 'General', 'svg' => '<path d="M3 9a2 2 0 0 0 0 6v3h18v-3a2 2 0 0 0 0-6V6H3z"/><path d="M14 6v2M14 11v2M14 16v2"/>'],
];
