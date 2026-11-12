@extends('frontend.layout')
@section('pageHeading', __('About TICA'))
@section('react-only', 'true')
@section('meta-keywords', !empty($seo->meta_keyword_about) ? $seo->meta_keyword_about : '')
@section('meta-description', !empty($seo->meta_description_about) ? $seo->meta_description_about : '')
@section('content')
  <div data-tica-react="content" data-props='@json(["page" => "about"], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)'>
    <main class="min-h-[60vh] bg-navy"></main>
  </div>
@endsection
