@extends('app')

@section('content')
    <section class="hero is-info is-fullheight">
        <div class="columns" style="width: 100%;margin-top: auto;margin-bottom: auto;margin-left: 0;margin-right: 0;">
            <div class="column"></div>
            <div class="column is-one-quarter">
                <p class="title is-1 has-text-centered">Reset Password</p>
                <div class="panel-body">
                    @if (session('status'))
                        <div class="alert alert-success">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form class="form-horizontal" role="form" method="POST" action="{{ url('/password/email') }}">
                        {{ csrf_field() }}

                        <div class="field" style="margin-right:24px;margin-left: 24px;">
                            <label class="label has-text-white" for="email">E-Mail Address</label>

                            <div class="control">
                                <input
                                    id="email"
                                    type="email"
                                    class="input {{ $errors->has('email') ? 'is-danger' : '' }}"
                                    name="email"
                                    value="{{ old('email') }}"
                                    required
                                    autofocus
                                    placeholder="you@email.com"
                                />

                                @if ($errors->has('email'))
                                    <p class="help has-text-white has-text-weight-bold">{{ $errors->first('email') }}</p>
                                @endif
                            </div>
                        </div>

                        <div class="field has-text-centered">
                            <div class="control">
                                <button type="submit" class="button is-primary">
                                    Send Password Reset Link
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="column"></div>
        </div>
    </section>
@endsection
