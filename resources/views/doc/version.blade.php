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
    </head>
    <body>
        <div class="flex-center position-ref full-height perfect-scrollbar">
            <div class="content">
                <center><p class="sub">Documentation V 1.0</p></center>
                <div class="btn-group-1">
                    <a href="{{ url('/doc/v1/token') }}">API Rest</a>
                </div>
                <div class="coming-soon">
                    <center><p class="sub">Documentation V 2.0</p></center>
                    <div class="btn-group-1">
                        <a href="#">API Rest</a>
                        <a href="#">GraphQL</a>

                        <!--a href="{{ url('/doc/v2/token') }}">API Rest</a>
                        <a href="{{ url('/doc/home') }}">GraphQL</a-->
                    </div>
                </div>
            </div>
            

            <script src="{{ asset('js/jquery.min.js') }}"></script>
            <script src="{{ asset('js/perfect-scrollbar.min.js') }}"></script>
            <script src="{{ asset('js/app.js') }}"></script>
        </div>
    </body>
</html>