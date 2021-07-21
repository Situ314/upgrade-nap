@extends('layouts.app')

@section('content')
    <h1 id="hotels">HOTELS</h1>
    <h2 id="hotels_get_list">GET HOTELS LIST</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">http://api-dev.mynuvola.net/api/hotels</td>
            </tr>
            <tr>
                <td class="bold">Method</td>
                <td colspan="2">GET</td>
            </tr>
            <tr>
                <td class="bold">Headers</td>
                <td>Autorization</td>
                <td><code>{ token_type }</code> + " " + <code>{ access_token }</code></td>
            </tr>
            <tr>
                <td class="bold">Response</td>
                <td colspan="2"><per id="json-hotels_get_list-response"></per></td>
            </tr>
        </tbody>
    </table>
@endsection

@section('script')
    $('#json-hotels_get_list-response').jsonViewer([{
        hotel_id: "numeric",
        hotel_name: "string"
    }],config);
@endsection