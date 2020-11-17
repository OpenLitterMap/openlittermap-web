<!DOCTYPE html>
<html lang="en">
    @include('header')
    <body>
        <div id="app">
            @yield('content')
        </div>
    </body>

    <script src="https://js.stripe.com/v3"></script>
    <script src="/js/app.js"></script>
    <script src="/js/wow.js"></script>
    <script>
        var wow = new WOW();
        wow.init();
    </script>
</html>
