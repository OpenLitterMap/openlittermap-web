<!DOCTYPE html>
<html lang="en">
    @include('header')
    <body>
        <div id="app">
            @yield('content')
        </div>
    </body>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>

    <script src="https://js.stripe.com/v3"></script>

{{--    'resources/css/app.css',--}}
    @vite(['resources/js/app.js'])
</html>
