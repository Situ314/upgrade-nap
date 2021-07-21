<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Nuvola + Alexa | integration</title>
    <!-- Styles -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta.2/css/bootstrap.min.css" integrity="sha384-PsH8R72JQ3SOdhVi3uxftmaW6Vc51MKb0q5P2rRUpPvrszuE4W1povHYgTpBfshb" crossorigin="anonymous">
    <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet" integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous">
    <link href="{{ asset('css/alexa.css') }}" rel="stylesheet">
</head>
<body>
    <div class="alx_authorize__wrapper">
        <div class="alx_authorize">
            <img src="{{ asset('img/logo-login-amazon.png') }}" alt="Nuvola" width="250">
            <button type="button" class="btn-login btn btn-authorize">Authorize</button>
            <button type="button" class="btn-login btn btn-cancel">Cancel</button>
        </div>        
    </div>
</body>
</html>