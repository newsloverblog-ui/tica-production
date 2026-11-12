@extends('frontend.layout')
@section('pageHeading', __('Edit Profile'))
@section('react-only', 'true')
@section('content')
@php
  $customer = Auth::guard('customer')->user();
  $profileFields = [
    ['name'=>'fname','label'=>__('First name'),'value'=>old('fname',$customer->fname),'required'=>true,'error'=>$errors->first('fname')],
    ['name'=>'lname','label'=>__('Last name'),'value'=>old('lname',$customer->lname),'required'=>true,'error'=>$errors->first('lname')],
    ['name'=>'email','label'=>__('Email address'),'type'=>'email','value'=>old('email',$customer->email),'required'=>true,'error'=>$errors->first('email')],
    ['name'=>'current_password','label'=>__('Current password'),'type'=>'password','placeholder'=>__('Required only when changing your email'),'help'=>__('Confirm your password before changing your email.'),'error'=>$errors->first('current_password')],
    ['name'=>'username','label'=>__('Username'),'value'=>old('username',$customer->username),'required'=>true,'error'=>$errors->first('username')],
    ['name'=>'phone','label'=>__('Phone'),'value'=>old('phone',$customer->phone)],
    ['name'=>'country','label'=>__('Country'),'value'=>old('country',$customer->country)],
    ['name'=>'city','label'=>__('City'),'value'=>old('city',$customer->city)],
    ['name'=>'state','label'=>__('State'),'value'=>old('state',$customer->state)],
    ['name'=>'zip_code','label'=>__('Postcode'),'value'=>old('zip_code',$customer->zip_code)],
    ['name'=>'address','label'=>__('Address'),'value'=>old('address',$customer->address),'wide'=>true],
  ];
  $nav = [
    ['label'=>__('Overview'),'url'=>route('customer.dashboard')],['label'=>__('My bookings'),'url'=>route('customer.booking.my_booking')],['label'=>__('Profile'),'url'=>route('customer.edit.profile'),'active'=>true],['label'=>__('Password'),'url'=>route('customer.change.password')],['label'=>__('Wishlist'),'url'=>route('customer.wishlist')],
  ];
  $props = ['eyebrow'=>__('Your TICA account'),'title'=>__('Edit profile'),'intro'=>__('Keep your contact and location details accurate for smoother bookings.'),'nav'=>$nav,'csrf'=>csrf_token(),'success'=>Session::get('success'),'variant'=>'profile','action'=>route('customer.profile.update'),'photoUrl'=>$customer->photo ? asset('assets/admin/img/customer-profile/'.$customer->photo) : asset('assets/front/images/profile.jpg'),'fields'=>$profileFields];
@endphp
<div data-tica-react="account-settings" data-props='@json($props)'></div>
@endsection
