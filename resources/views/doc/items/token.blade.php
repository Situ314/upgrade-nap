@extends('doc.layouts.app',[ 'version' => $version ])

@section('content')
<h1 id="token">TOKEN</h1>
<div class="separator max-700 mb-4"></div>
<p class="max-700">
    The processing of the requests only is available with valid token, this information will be sent with the all requests. The information required is provided by Nuvola.
</p>
<h2 id="token_get">GET TOKEN</h2>
<table class="table table-striped">
    <tbody>
        <tr>
            <td class="bold">URL</td>
            <td>{{$url}}/oauth/token</td>
        </tr>
        <tr>
            <td class="bold">Method</td>
            <td>POST</td>
        </tr>
        <tr>
            <td class="bold">Data</td>
            <td><per id="json-token_get-data"></per></td>
        </tr>
        <tr>
            <td class="bold">Response</td>
            <td><per id="json-token_get-response"></per></td>
        </tr>
    </tbody>
</table>
<h2 id="token_refresh">REFRESH TOKEN</h2>
<table class="table table-striped">
    <tbody>
        <tr>
            <td class="bold">URL</td>
            <td>{{$url}}/oauth/token</td>
        </tr>
        <tr>
            <td class="bold">Method</td>
            <td>POST</td>
        </tr>
        <tr>
            <td class="bold">Data</td>
            <td><per id="json-token_refresh-data"></per></td>
        </tr>
        <tr>
            <td class="bold">Response</td>
            <td><per id="json-token_refresh-response"></per></td>
        </tr>
    </tbody>
</table>
@endsection

@section('script')
    

    $('#json-token_get-data').jsonViewer({ 
        client_id: "numeric | required", 
        client_secret: "string | required", 
        grant_type: " \"password\" | required", 
        username: "Nuvola username | required", 
        password: "Nuvola password | required", 
        scope: " \"*\" | required" 
    },config);
    $('#json-token_get-response').jsonViewer({ 
        token_type: "string", 
        expires_in: "numeric", 
        access_token: "string", 
        refresh_token: "string"  
    },config);
    $('#json-token_refresh-data').jsonViewer({ 
        grant_type: " \"refresh_token\" | string | required",
        refresh_token: "access_token | string | required",
        client_id: "client_id | numeric | required", 
        client_secret: "client_secret | string | required", 
        scope: " \"\" | required" 
    },config);
    $('#json-token_refresh-response').jsonViewer({ 
        token_type: "string", 
        expires_in: "numeric", 
        access_token: "string", 
        refresh_token: "string"  
    },config);
@endsection