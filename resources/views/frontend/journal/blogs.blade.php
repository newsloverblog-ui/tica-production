@extends('frontend.layout')
@section('pageHeading', !empty($pageHeading) ? ($pageHeading->blog_page_title ?? __('Stories')) : __('Stories'))
@section('meta-keywords', !empty($seoInfo->meta_keyword_blog) ? $seoInfo->meta_keyword_blog : '')
@section('meta-description', !empty($seoInfo->meta_description_blog) ? $seoInfo->meta_description_blog : '')
@section('react-only', 'true')
@section('content')
@php
  $directoryProps=['variant'=>'blogs','title'=>__('Stories from the culture.'),'intro'=>__('Ideas, guides and stories for people who attend events and the organisers who make them happen.'),'cards'=>collect($blogs->items())->map(fn($blog)=>[
    'title'=>$blog->title,'eyebrow'=>$blog->categoryName,'summary'=>Illuminate\Support\Str::limit(strip_tags($blog->content),130),
    'image'=>asset('assets/admin/img/blogs/'.$blog->image),'href'=>route('blog_details',['slug'=>$blog->slug]),'meta'=>$blog->created_at->format('M d, Y')
  ])->values(),'previousUrl'=>$blogs->appends(request()->query())->previousPageUrl(),'nextUrl'=>$blogs->appends(request()->query())->nextPageUrl(),'search'=>['action'=>route('blogs'),'name'=>'title','value'=>request('title'),'placeholder'=>__('Search stories')]];
@endphp
@include('frontend.partials.directory-react',['directoryProps'=>$directoryProps])
@endsection
