@extends('frontend.layout')
@section('pageHeading', __('Change Password'))
@section('react-only', 'true')
@section('content')
@php
  $nav = [
    ['label'=>__('Overview'),'url'=>route('customer.dashboard')],['label'=>__('My bookings'),'url'=>route('customer.booking.my_booking')],['label'=>__('Profile'),'url'=>route('customer.edit.profile')],['label'=>__('Password'),'url'=>route('customer.change.password'),'active'=>true],['label'=>__('Wishlist'),'url'=>route('customer.wishlist')],
  ];
  $fields = [
    ['name'=>'current_password','label'=>__('Current password'),'type'=>'password','required'=>true,'wide'=>true,'error'=>$errors->first('current_password')],
    ['name'=>'new_password','label'=>__('New password'),'type'=>'password','required'=>true,'error'=>$errors->first('new_password')],
    ['name'=>'new_password_confirmation','label'=>__('Confirm new password'),'type'=>'password','required'=>true,'error'=>$errors->first('new_password_confirmation')],
  ];
  $props = ['eyebrow'=>__('Account security'),'title'=>__('Change password'),'intro'=>__('Use a strong, unique password you do not use elsewhere.'),'nav'=>$nav,'csrf'=>csrf_token(),'success'=>Session::get('success'),'variant'=>'password','action'=>route('customer.password.update'),'fields'=>$fields];
@endphp
<div data-tica-react="account-settings" data-props='@json($props)'></div>
@endsection
