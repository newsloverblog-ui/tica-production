@extends('frontend.layout')
@section('pageHeading', $details->title)
@section('meta-keywords', $details->meta_keywords)
@section('meta-description', $details->meta_description)
@section('og-title', $details->title)
@section('og-description', strip_tags($details->content))
@section('og-image', asset('assets/admin/img/blogs/'.$details->image))
@section('react-only', 'true')
@section('content')
@php
  $directoryProps=['variant'=>'article','title'=>$details->title,'intro'=>trim($details->categoryName.' · '.$details->created_at->format('M d, Y').($details->author?' · '.$details->author:'')),'articleHtml'=>$details->content,'articleImage'=>asset('assets/admin/img/blogs/'.$details->image)];
@endphp
@include('frontend.partials.directory-react',['directoryProps'=>$directoryProps])
@endsection
