@extends('frontend.layout')
@section('pageHeading', __('Organisers'))
@section('react-only', 'true')
@section('content')
@php
  $directoryProps=['variant'=>'organisers','title'=>__('Meet the people behind the moments.'),'intro'=>__('Explore verified organisers and discover what they are creating next.'),'cards'=>collect($collection->items())->map(function($item){$info=$item->organizer_info;return [
    'title'=>optional($info)->name ?? $item->username,'eyebrow'=>__('Event organiser'),'summary'=>trim(collect([optional($info)->city,optional($info)->state,optional($info)->country])->filter()->join(', ')),
    'image'=>$item->photo?asset('assets/admin/img/organizer-photo/'.$item->photo):asset('assets/front/images/user.png'),
    'href'=>route('frontend.organizer.details',[$item->id,str_replace(' ','-',$item->username)]),'meta'=>trans_choice(':count event|:count events',OrganizerEventCount($item->id),['count'=>OrganizerEventCount($item->id)])
  ];})->values(),'previousUrl'=>$collection->appends(request()->query())->previousPageUrl(),'nextUrl'=>$collection->appends(request()->query())->nextPageUrl(),'search'=>['action'=>route('frontend.all.organizer'),'name'=>'organizer','value'=>request('organizer'),'placeholder'=>__('Search organisers')]];
@endphp
@include('frontend.partials.directory-react',['directoryProps'=>$directoryProps])
@endsection
