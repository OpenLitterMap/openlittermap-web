<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    @include('header')
    <body>
        <div id="app">
            @yield('content')
        </div>
    </body>

    <script>
        window.initialProps = {!! json_encode([
            'auth'           => $auth ?? false,
            'user'           => $user ?? null,
            'impersonating'  => $impersonating ?? false,
        ]) !!};
    </script>

    <script src="https://js.stripe.com/v3"></script>
</html>
