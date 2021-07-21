@extends('doc.layouts.app')

@section('content')
    <h1 id="hotels">HOTELS</h1>
    <div class="separator max-700 mb-4"></div>
    <p class="max-700">This option provides a list of hotels in which the current user is associated.</p>
    <h2 id="hotels_get_list">GET HOTELS LIST</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">{{$url}}/hotels</td>
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