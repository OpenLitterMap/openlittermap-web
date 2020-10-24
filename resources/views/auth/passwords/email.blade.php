@extends('app')

@section('content')
<section class="hero is-info is-fullheight">
    <div class="columns">
        <div class="column is-half is-offset-3 has-text-centered" style="margin-top: 10%;">
            <p class="title is-1">Reset Password</p>
            <div class="panel-body">
                @if (session('status'))
                <div class="alert alert-success">
                    {{ session('status') }}
                </div>
                @endif

                <form class="form-horizontal" role="form" method="POST" action="{{ url('/password/email') }}">
                    {{ csrf_field() }}

                    <div class="form-group{{ $errors->has('email') ? ' has-error' : '' }}">
                        <label for="email">E-Mail Address</label>

                        <div class="col-md-6">
                            <input id="email" type="email" class="input" name="email" value="{{ old('email') }}" required placeholder="you@email.com" style="width: 50%;" />

                            @if ($errors->has('email'))
                            <br>
                            <span class="help-block">
                                    <strong>{{ $errors->first('email') }}</strong>
                                </span>
                            @endif
                        </div>
                    </div>

                    <button type="submit" class="button is-primary" style="margin-top: 2em;">
                        Send Password Reset Link
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection
