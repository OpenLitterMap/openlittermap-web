<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Join the community mapping and sharing data on plastic pollution">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        window.PUSHER_APP_KEY = '{{ config('broadcasting.connections.pusher.key') }}';
        window.APP_DEBUG      = '{{ config('app.debug') ? 'true' : 'false' }}';

        <?php
            $permissions = auth()->check()
                ? auth()->user()->jsPermissions()
                : 'null'
        ?>

        window.Laravel = {
            jsPermissions: {!! $permissions !!}
        }
    </script>
    <title>OpenLitterMap</title>
    <!-- Font & icons -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css">
    <!-- Animate -->
    <link rel="stylesheet" href="/css/animate.min.css" />
    <!-- Dropzone -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/4.3.0/dropzone.css" />
    <!-- CSS -->
    <link rel="stylesheet" type="text/css" href="/css/bulma.css" />
    <link rel="stylesheet" type="text/css" href="{{ mix('/css/app.css') }}" />
    <!-- extra leaflet styles -->
    <link ref="stylesheet" type="text/css" href="/css/leaflet.css" />

    <link rel="stylesheet" type="text/css" href="/css/MarkerCluster.css">
    <link rel="stylesheet" type="text/css" href="/css/MarkerCluster.Default.css">

    <!-- code to rotate an img -->
    <style type="text/css">
        .rotateimg180 {
            -webkit-transform:rotate(180deg);
            -moz-transform: rotate(180deg);
            -ms-transform: rotate(180deg);
            -o-transform: rotate(180deg);
            transform: rotate(180deg);
        }
        .rotateimg90 {
            -webkit-transform:rotate(90deg);
            -moz-transform: rotate(90deg);
            -ms-transform: rotate(90deg);
            -o-transform: rotate(90deg);
            transform: rotate(90deg);
        }
    </style>
</head>
