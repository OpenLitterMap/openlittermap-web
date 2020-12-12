@extends('app')
@section('content')
    <Root-Container
        auth="{{ $auth }}"
        user="{{ $user }}"
        verified="{{ $verified }}"
    />
@stop
