<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Nuvola API Doc</title>
    <!-- style -->
    <link href="{{ asset('css/doc.css') }}" rel="stylesheet">    
    <link href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/perfect-scrollbar.css') }}" rel="stylesheet">
    <link href="{{ asset('css/jquery.json-viewer.css') }}" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="shortcut icon" type="image/png" href="{{ asset('img/logo-n.ico') }}"/>
</head>
<body>
    @include('doc.layouts.menu',[ 'version' => $version ])
    @include('doc.layouts.nav')
    <div class="mainContainer perfect-scrollbar">
        @yield('content')
    </div>
    <!-- Script -->
    <script src="{{ asset('js/jquery.min.js') }}"></script>
    <script src="{{ asset('js/perfect-scrollbar.min.js') }}"></script>
    <script src="{{ asset('js/bootstrap.min.js') }}"></script>
    
    <script src="{{ asset('js/jquery.json-viewer.js') }}"></script>
    <script src="{{ asset('js/app.js') }}"></script>

    <script>
        $(document).ready(function(){
            var config = {
                collapsed: true, 
                withQuotes: false
            };
            
            $('#nav').find('li').find('a').removeClass('active');

            var to_active = window.location.hash;
            $(to_active.replace('#','.')).addClass('active');

            $('#nav').find('li').find('a').click(function(){
                $('#nav').find('li').find('a').removeClass('active');
                $(this).addClass('active');
            });

            if($('a.active').length){
                var poss = $('a.active').position().top ;
                $('.sidebar.perfect-scrollbar').scrollTop(poss);
            }
            
            $('.dropdown-menu-version a').click(function(){
                var currentVersion = ($('#navbarDropdown').text()).trim();
                var version = ($(this).text()).trim();

                if(currentVersion != version) {
                    var pathname = window.location.pathname;
                    pathname = pathname.replace(currentVersion, version);
                    window.location.href = pathname;
                }
            });
            
            var hash = window.location.hash;
            window.location.hash = "";
            window.location.hash = hash;

            $('#btn-menu').on('click', function(){
                $('.sidebar, .mainContainer').toggleClass('menu-show');
            });

            @yield('script')
        });

    </script>
</body>
</html>