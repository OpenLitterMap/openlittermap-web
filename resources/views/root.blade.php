@extends('app')
@section('content')
    <Root-Container
        auth="{{ $auth }}"
        user="{{ $user }}"
        verified="{{ $verified }}"
        unsub="{{ $unsub }}"
    ></Root-Container>
@stop
