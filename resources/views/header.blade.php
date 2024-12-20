<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Join the global community mapping and sharing data on plastic pollution">
    <meta name="robots" content="index, follow">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>OpenLitterMap</title>

    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @filamentStyles

    <script>
        window.PUSHER_APP_KEY = '{{ config('broadcasting.connections.pusher.key') }}';
        window.APP_DEBUG = {{ json_encode(config('app.debug')) }};

        window.Laravel = {
            jsPermissions: @json($permissions ?? null),
        };
    </script>
</head>
