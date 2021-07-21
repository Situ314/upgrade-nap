@extends('layouts.app')

@section('content')
<div class="full-height-calculate">
    <div class="full-height-100 flex-center">
        <div class="login-container">
            <form method="POST" action="{{ route('login') }}" autocomplete="off">
                {{ csrf_field() }}
                <div class="form-group" style="text-align: center;">
                    <img style="max-width: 110px; margin-bottom: 20px" src="{{ asset('img/logo-n.png') }}" alt="Nuvola logo">
                </div>
                <div class="form-group">
                    <label for="username" style="margin: 0">Username</label>
                    <input type="text" class="form-control {{ $errors->has('username') ? 'is-invalid' : '' }}" value="{{ old('username') }}" name="username" aria-describedby="username">
                    @if ($errors->has('username'))
                        <small class="invalid-feedback">{{ $errors->first('username') }}</small>
                    @endif
                </div>
                <div class="form-group">
                    <label for="password" style="margin: 0">Password</label>
                    <input type="password" class="form-control {{ $errors->has('password') ? 'is-invalid' : '' }}" name="password" aria-describedby="username">
                    @if ($errors->has('password'))
                        <small class="invalid-feedback">{{ $errors->first('password') }}</small>
                    @endif
                </div>

                <div class="form-group login-button">
                    <button type="submit" class="btn btn-login">
                        Login
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
