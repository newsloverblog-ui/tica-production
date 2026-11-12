@extends('frontend.layout')
@section('pageHeading', !empty($pageHeading) ? ($pageHeading->faq_page_title ?? __('Frequently asked questions')) : __('Frequently asked questions'))
@section('meta-keywords', !empty($seoInfo->meta_keyword_faq) ? $seoInfo->meta_keyword_faq : '')
@section('meta-description', !empty($seoInfo->meta_description_faq) ? $seoInfo->meta_description_faq : '')
@section('react-only', 'true')
@section('content')
@php
  $directoryProps=['variant'=>'faq','title'=>__('Questions, answered.'),'intro'=>__('Everything you need to know about finding events, buying tickets and running an event with TICA.'),'faqs'=>$faqs->map(fn($faq)=>['question'=>$faq->question,'answer'=>$faq->answer])->values()];
@endphp
@include('frontend.partials.directory-react',['directoryProps'=>$directoryProps])
@endsection
