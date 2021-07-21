@extends('layouts.api.app')

@section('content')
    <div id="card-login-wrapper">
        <nav class="navbar">
            <div>
            <span class="icon">
                <i class="fa fa-cloud icon-cloud" aria-hidden="true"></i>
            </span>
            <span class="title">
                Nuvola
            </span>
            </div>
            <span class="icon icon-question">
                <i class="fa fa-question" aria-hidden="true"></i>
            </span>
        </nav>
        <div id="card-login" class="card">
            <div class="card-body">
                <p>Please provider your Nuvola credentials to turn on the integration</p>
                <form id="loginForm" class="form-horizontal">
                    <div class="form-group">
                            <input id="username" placeholder="Username" type="text" class="form-control" name="username" value="{{ old('username') }}" required autofocus>
                            @if ($errors->has('username'))
                                <span class="help-block">
                                    <strong>{{ $errors->first('username') }}</strong>
                                </span>
                            @endif
                    </div>
                    <div class="form-group">
                            <input id="password" placeholder="Password" type="password" class="form-control" name="password" required>

                            @if ($errors->has('password'))
                                <span class="help-block">
                                    <strong>{{ $errors->first('password') }}</strong>
                                </span>
                            @endif
                    </div>
                    <div >
                        <button id="btn" type="submit" class="btn btn-primary btn-block btn-rounded">
                            next
                        </button>
                    </div>
                    <div class="col-sm-12 text-center pt-3">
                        <span class="text-danger">
                            incorrect user and password
                        </span>
                    </div>
                </form>
            </div>
        </div>
        <div class="card-hotels-wrapper">
            <div id="card-hotels" class="card">
                <div class="card-body">
                    <p>Choose a Nuvola property to link with be hive and sync contacts and tasks.</p>
                    <ul class="list-group">
                    </ul>
                    
                </div>
            </div>

            <div class="mt-3 col-sm-12">
                <button id="btn-hotel" id="btn" type="submit" class="btn btn-primary btn-block btn-rounded">
                    next
                </button>
            </div>
        </div>
        <div class="spinner-wrapper">
            <div class="spinner">
                <div class="bar1"></div>
                <div class="bar2"></div>
                <div class="bar3"></div>
                <div class="bar4"></div>
                <div class="bar5"></div>
                <div class="bar6"></div>
                <div class="bar7"></div>
                <div class="bar8"></div>
                <div class="bar9"></div>
                <div class="bar10"></div>
                <div class="bar11"></div>
                <div class="bar12"></div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        $(document).ready(function($){
            var rs = {};
            $('.card-hotels-wrapper,.text-danger').css({ display: 'none' });

            $( "#loginForm" ).submit(function( event ) {
                
                var username = $('#username').val();
                var password = $('#password').val();

                $('.spinner-wrapper').toggleClass('active');
                $('.text-danger').css({ display: 'none' });
                $.ajax({
                    type: 'POST',
                    url: '/oauth/token',
                    data: {
                        grant_type: 'password',
                        client_id: {{ $client_id }},
                        client_secret: '{{ $client_secret }}',
                        username: username,
                        password: password,
                        scope: '*'
                    },
                    success: function(d) {
                        rs = d;
                        $.ajax({
                            type: 'GET',
                            url: '/api/hotels',
                            headers: {
                                "Authorization" : rs.token_type+' '+rs.access_token
                            },
                            success: function(d) {
                                $('.spinner-wrapper').toggleClass('active');
                                var hotels = d;
                                var innerHtml = '';
                                $(hotels).each( function( index, value ){
                                    innerHtml += '<li data-hotel_id = "'+value.hotel_id+'" class="list-group-item d-flex justify-content-between align-items-center list-group-item-action">'+value.hotel_name+'<span class="badge"><i class="fa fa-circle-o" aria-hidden="true"></i></span></li>';
                                });
                                $('.list-group').html(innerHtml);
                                $('#card-login').css({ display: 'none' });
                                $('.card-hotels-wrapper').css({ display: 'block' });
                            }
                        });
                    },error: function(d){
                        console.log(d);                        
                        $('.spinner-wrapper').removeClass('active');
                        $('.text-danger').css({ display: 'block' });
                        //incorrect user and password
                    }
                });
                event.preventDefault();
            });
            $('.list-group').on('click','.list-group-item', function(event){
                $('.list-group .list-group-item i').removeClass('active');
                $('.list-group .list-group-item i').removeClass('fa-check-circle-o');
                $('.list-group .list-group-item i').addClass('fa-circle-o');

                $(event.target).find('i').addClass('active');
                $(event.target).find('i').addClass('fa-check-circle-o');
                $(event.target).find('i').removeClass('fa-circle-o');

                rs["hotel_id"] = $(event.target).data('hotel_id');
            });
            $('#btn-hotel').on('click',function(){
                var form = $('<form action="{{ $redirect_to }}" method="POST">' +
                    '<input name="api_data" value=\'' + JSON.stringify(rs) + '\' />' +
                '</form>');
                $('body').append(form);
                form.submit();
            });
        });
    </script>
@endsection