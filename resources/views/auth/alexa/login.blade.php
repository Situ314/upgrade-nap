<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Nuvola + Alexa | integration</title>
    <!-- Styles -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta.2/css/bootstrap.min.css"
        integrity="sha384-PsH8R72JQ3SOdhVi3uxftmaW6Vc51MKb0q5P2rRUpPvrszuE4W1povHYgTpBfshb" crossorigin="anonymous">
    <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet"
        integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous">
    <link href="{{ asset('css/alexa.css') }}" rel="stylesheet">
</head>

<body>
    <div class="alx_login__wrapper">
        <!--
        -->
        <div class="alx_login__form">
            <div class="alx_login__form_wrapper">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-sm-12">
                            <img src="{{ asset('img/logo-login-amazon.png') }}" alt="Nuvola" width="250">
                            <form method="POST" action="/api/v1/alexa/login">
                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                {{-- <input name="secret" type="hidden" value="{{$data['client_secret']}}"> --}}
                                <input name="client_id" type="hidden" value="{{$data['client_id']}}">
                                <input name="redirect_uri" type="hidden" value="{{$data['redirect_uri']}}">
                                <input name="state" type="hidden" value="{{$data['state']}}">
                                <div class="form-group">
                                    <label for="user">Username / Email</label>
                                    <input name="username" autofocus="true" type="text" class="form-control" id="user"
                                        placeholder="Enter username or email">
                                </div>
                                <div class="form-group">
                                    <label for="password">Password</label>
                                    <input name="password" type="password" class="form-control" id="password" placeholder="Password">
                                </div>
                                <button type="submit" class="btn-login btn btn-primary btn-block login-button">Login</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <label class="footer"><?php echo "Copyright NUVOLA &#169 ".date('Y')." | All Rights Reserved"; ?></label>
        </div>
        <!--            
        -->
        <div class="alx_login__image" style="background-image: url('{{ asset('img/Alexa-Login-BG.jpg') }}')"></div>
    </div>
    <link href="{{ asset('js/alexa.css') }}" rel="stylesheet">
</body>

</html>