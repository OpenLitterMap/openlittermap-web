@extends('app')
@section('content')
    <Root-Container
        auth="{{ $auth }}"
        user="{{ $user }}"
        verified="{{ $verified }}"
        unsub="{{ $unsub }}"
        username="{{ isset($username) ? $username : false }}"
        public-profile="{{ isset($publicProfile) ? $publicProfile : null }}"
        user-data="{{ isset($userData) ? $userData : null }}"
    ></Root-Container>
@stop
