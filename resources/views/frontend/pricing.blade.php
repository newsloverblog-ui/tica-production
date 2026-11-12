@extends('frontend.layout')
@section('pageHeading', __('Pricing'))
@section('react-only', 'true')
@section('meta-keywords', $seoInfo->meta_keyword_contact ?? 'TICA event pricing')
@section('meta-description', $seoInfo->meta_description_contact ?? 'Clear event ticketing fees with TICA.')
@section('content')
  <div data-tica-react="content" data-props='@json(["page" => "pricing"], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)'>
    <main class="min-h-[60vh] bg-navy"></main>
  </div>
@endsection
