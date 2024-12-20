<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    @include('header')
    <body>
        <div id="app">
            @filamentScripts
            @yield('content')
        </div>
    </body>

    <script src="https://js.stripe.com/v3"></script>
</html>
