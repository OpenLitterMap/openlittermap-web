@extends('app')
@section('content')

    <div class="section has-text-centered">
        <div class="container">
            <h1 class="title is-1">Thanks for checking out OpenLitterMap!</h1>
            <h2 class="subtitle is-3" style="margin-bottom: 0;">Oops! This impact report is still chilling in the future.</h2>
            <h2 class="subtitle is-3">Come back with a time-machine or try again later.</h2>

            <div class="image-container mt-5 mb-5">
                <img
                    src="/assets/images/cleaning-planet.webp"
                    alt="Cleaning Planet"
                    class="image is-inline-block"
                    style="max-width: 650px;"
                />
            </div>

            <p class="mt-5">
                <a href="/" class="button is-primary is-large">
                    Return to OpenLitterMap
                </a>
            </p>
        </div>
    </div>

@stop
