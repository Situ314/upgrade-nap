<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Nuvola  API</title>
    <!--icon-->
    <link rel="shortcut icon" type="image/png" href="{{ asset('img/logo-n.ico') }}"/>
    <!-- Styles -->
    <link href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/perfect-scrollbar.css') }}" rel="stylesheet">
    <link href="{{ asset('css/v2/style.css') }}" rel="stylesheet">    
</head>
<body>
    <div id="main">
        <header class="header">
            <div class="nuvola-logo-container">
                <a href="">
                    <img src="{{ asset('img/nuvola.svg') }}" alt="nuvola-logo">
                </a>                
            </div>
            <div class="flex-spacer"></div>
        </header>
        <div class="main-content">
            @yield('content')
        </div>
    </div>
</body>
</html>