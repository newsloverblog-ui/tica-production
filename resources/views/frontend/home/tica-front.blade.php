@extends('frontend.layout')
@section('pageHeading', __('Events shape kulture'))
@section('meta-keywords', $seoInfo->meta_keyword_home ?? '')
@section('meta-description', $seoInfo->meta_description_home ?? __('Find events, buy tickets and sell tickets across Nigeria with TICA.'))
@section('react-only', 'true')
@section('body-class', 'tf-home')

@section('content')
@php
  $eventPricing = function ($event) use ($frontEventTickets) {
    $tickets = $frontEventTickets->get($event->id, collect());
    $prices = $tickets->flatMap(function ($ticket) {
      if (($ticket->pricing_type ?? null) === 'variation') return collect(json_decode($ticket->variations ?? '[]', true))->pluck('price');
      return collect([$ticket->f_price ?? $ticket->price ?? null]);
    })->filter(fn($price) => is_numeric($price));
    $minimum = $prices->isNotEmpty() ? (float) $prices->min() : 0;
    return ['kobo'=>(int) round($minimum * 100),'label'=>$prices->isNotEmpty() ? symbolPrice($minimum) : __('Free')];
  };
  $eventImage = function ($event) {
    $path='assets/admin/img/event/thumbnail/'.$event->thumbnail;
    return !empty($event->thumbnail)?\App\Support\PublicImage::flyer($event->thumbnail):asset('assets/front/images/home/organizer-live-event-unsplash.jpg');
  };
  $homeEvents=$frontEvents->take(8)->map(function($event) use($eventPricing,$eventImage){
    try{$start=\Carbon\Carbon::parse($event->start_date_time ?: trim($event->start_date.' '.$event->start_time));}catch(\Throwable $e){$start=now();}
    $pricing=$eventPricing($event);
    return ['id'=>(int)$event->id,'slug'=>$event->slug,'name'=>$event->title,'url'=>route('event.details',[$event->slug,$event->id]),'image'=>$eventImage($event),'date'=>$start->format('d'),'month'=>strtoupper($start->format('M')),'venue'=>$event->event_type==='online'?__('Online event'):($event->address ?: __('Venue announced soon')),'city'=>$event->event_type==='online'?'':trim(collect([$event->city,$event->country])->filter()->join(', ')),'priceKobo'=>$pricing['kobo'],'priceLabel'=>$pricing['label'],'category'=>data_get($event,'category',__('Event')) ?: __('Event'),'organiser'=>data_get($event,'organizer_username',__('TICA organiser')) ?: __('TICA organiser')];
  })->values();
  $heroImages=$homeEvents->pluck('image')->unique()->take(3)->values();
  foreach ([asset('assets/front/images/home/organizer-stage-crowd-unsplash.jpg'),asset('assets/front/images/home/organizer-live-event-unsplash.jpg'),asset('assets/front/images/hero-bg.jpg')] as $fallbackImage) { if ($heroImages->count() >= 3) break; if (!$heroImages->contains($fallbackImage)) $heroImages->push($fallbackImage); }
  $homeCategories=$eventCategories->map(fn($category)=>['name'=>$category->name,'slug'=>$category->slug])->values();
  foreach ([['name'=>__('Music'),'slug'=>'music'],['name'=>__('Nightlife'),'slug'=>'nightlife'],['name'=>__('Arts & culture'),'slug'=>'arts-culture'],['name'=>__('Comedy'),'slug'=>'comedy'],['name'=>__('Food & drink'),'slug'=>'food-drink'],['name'=>__('Wellness'),'slug'=>'wellness']] as $fallbackCategory) {
    if ($homeCategories->count() >= 6) break;
    if (!$homeCategories->contains(fn($category) => $category['slug'] === $fallbackCategory['slug'])) $homeCategories->push($fallbackCategory);
  }
  $homeProps=['events'=>$homeEvents,'heroImages'=>$heroImages,'categories'=>$homeCategories->take(6)->values(),'cities'=>['Lagos','Abuja','Port Harcourt','Owerri'],'routes'=>['discover'=>route('events'),'create'=>route('organizer.signup'),'pricing'=>route('pricing'),'about'=>route('about')]];
@endphp
<div data-tica-react="home" data-props='@json($homeProps, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)'>
  <main class="min-h-[70vh] bg-navy"></main>
</div>
@endsection
