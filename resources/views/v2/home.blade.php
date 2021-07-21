@extends('v2.layouts.layout')

@section('content')
    <section class="container">
        <div class="row max-850">
            <div class="col-12">
                <h1 class="mt-5">Nuvola's REST API</h1>
                <hr class="separator-h1">
                <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit. Eius illum obcaecati placeat similique impedit eos sint minus ratione amet? Hic neque exercitationem aspernatur a vitae nihil quis, illum veniam placeat.</p>
                <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit. Eius illum obcaecati placeat similique impedit eos sint minus ratione amet?Lorem ipsum dolor sit amet, consectetur adipisicing elit. Eius illum obcaecati placeat similique impedit eos sint minus ratione amet?Lorem ipsum dolor sit amet, consectetur adipisicing elit. Eius illum obcaecati placeat similique impedit eos sint minus ratione amet? Hic neque exercitationem aspernatur a vitae nihil quis, illum veniam placeat.</p>
                <div class="mt-5 text-center">
                    <button type="button" class="btn btn-pa min-200">GET STARTED</button>
                </div>
            </div>
            <div class="col-sm-12">
                <h2 class="mb-3 mt-5">Working wth Nuvola's API</h2>
                <hr class="separator-h2">
                <p>Lorem ipsum, dolor sit amet consectetur adipisicing elit. Illo distinctio eaque soluta, corporis reprehenderit explicabo alias maxime quos autem sunt non quae! Accusamus totam tempore, quam molestias culpa minus qui?</p>
            </div>      
            <div class="col-sm-12">
                <h3 class="mt-3">Authentication</h3>
                <p>Nuvola's API working with OAuth 2</p>
                <div class="card">
                    <div class="card-body">
                        <p class="card-text">OAuth 2.0 is the industry-standard protocol for authorization. OAuth 2.0 supersedes the work done on the original OAuth protocol created in 2006. OAuth 2.0 focuses on client developer simplicity while providing specific authorization flows for web applications, desktop applications, mobile phones, and living room devices. This specification and its extensions are being developed within the IETF OAuth Working Group.</p>
                    </div>
                    <div class="card-footer text-right">
                        <a href="https://oauth.net/2/" target="_blank" class="btn btn-sm">See more: https://oauth.net/2/</a>
                    </div>
                </div>
            </div>
            <div class="col-sm-12">
                <h3 class="mt-3 mb-3 mt-5">Works with...</h3>
            </div>
            <div class="col-sm-12 col-md-8 mb-5">
                <div class="card border-left">
                    <h5 class="card-header">Guest</h5>
                    <div class="card-body">
                            <p>Lorem ipsum dolor sit amet consectetur adipisicing elit. Velit, illum odit! Aliquid praesentium repellat necessitatibus nobis ad? Inventore nisi amet cumque nihil modi vitae hic exercitationem rem beatae. Culpa, iusto?</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-5">
                <div class="flex flex-center-center">
                    <img src="{{ asset('img/guest.svg') }}" alt="" srcset="" class="icon">
                </div>
            </div>
            <div class="col-md-4 mb-5">
                <div class="flex flex-center-center">
                    <img src="{{ asset('img/guest.svg') }}" alt="" srcset="" class="icon">
                </div>
            </div>
            <div class="col-sm-12 col-md-8 mb-5">
                <div class="card border-right">
                    <h5 class="card-header">Events</h5>
                    <div class="card-body">
                            <p>Lorem ipsum dolor sit amet consectetur adipisicing elit. Velit, illum odit! Aliquid praesentium repellat necessitatibus nobis ad? Inventore nisi amet cumque nihil modi vitae hic exercitationem rem beatae. Culpa, iusto?</p>
                    </div>
                </div>
            </div>
            <div class="col-sm-12 col-md-8 mb-5">
                <div class="card border-left">
                    <h5 class="card-header">Housekeeping</h5>
                    <div class="card-body">
                            <p>Lorem ipsum dolor sit amet consectetur adipisicing elit. Velit, illum odit! Aliquid praesentium repellat necessitatibus nobis ad? Inventore nisi amet cumque nihil modi vitae hic exercitationem rem beatae. Culpa, iusto?</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-5">
                <div class="flex flex-center-center">
                    <img src="{{ asset('img/guest.svg') }}" alt="" srcset="" class="icon">
                </div>
            </div>
        </div>      
    </section>
@endsection