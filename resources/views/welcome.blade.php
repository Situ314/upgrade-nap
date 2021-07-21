<!doctype html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Nuvola API</title>
        <!-- Fonts -->
        <link href="https://fonts.googleapis.com/css?family=Raleway:100,600" rel="stylesheet" type="text/css">
        <link href="{{ asset('css/style.css') }}" rel="stylesheet">
        <link href="{{ asset('css/perfect-scrollbar.css') }}" rel="stylesheet">
        <link rel="shortcut icon" type="image/png" href="{{ asset('img/logo-n.ico') }}"/>
    </head>
    <body>
        <div class="flex-center position-ref full-height perfect-scrollbar">
            <div class="content">
                <div>
                    <img style="max-width: 80px; margin-bottom: 0px" src="{{ asset('img/logo-n.png') }}" alt="Nuvola logo">
                    <center><p class="sub">Documentation</p></center>
                    <div class="title"> Nuvola API </div>
                </div>
                <div class="btn-group">
                    @if(Auth::guard()->user())
                        <a class="btn btn-login" href="{{ url('/doc/v2/token#token') }}">Get Started</a>
                        {{-- <a href="{{ url('/doc/v1/') }}">API Rest v1</a>
                        <a href="{{ url('/doc/v2/') }}">API Rest v2</a> --}}
                    @else
                        <a class="btn btn-login" href="{{ url('/doc/login') }}">Login</a>
                    @endif
                </div>
            </div>
            <script src="{{ asset('js/jquery.min.js') }}"></script>
            <script src="{{ asset('js/perfect-scrollbar.min.js') }}"></script>
            <script src="{{ asset('js/app.js') }}"></script>
        </div>
    </body>
</html>
