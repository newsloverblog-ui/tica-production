@extends('frontend.layout')
@section('pageHeading', $pageInfo->title)
@section('meta-keywords', $pageInfo->meta_keywords)
@section('meta-description', $pageInfo->meta_description)
@section('og-title', $pageInfo->title)
@section('react-only', 'true')
@section('content')
@php
  $directoryProps=['variant'=>'article','title'=>$pageInfo->title,'intro'=>$pageInfo->meta_description ?: __('Information from TICA.'),'articleHtml'=>$pageInfo->content];
@endphp
@include('frontend.partials.directory-react',['directoryProps'=>$directoryProps])
@endsection
