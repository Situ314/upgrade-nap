@extends('layouts.app')

@section('content')
    <h1 id="rooms">GET ROOMS</h1>
    <h2 id="rooms_get_list">GET ROOMS LIST</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">http://api-dev.mynuvola.net/api/v1/hotel-room</td>
            </tr>
            <tr>
                <td class="bold">Method</td>
                <td colspan="2">GET</td>
            </tr>
            <tr>
                <td class="bold" rowspan="2">Headers</td>
                <td>Autorization</td>
                <td><code>{ token_type }</code> + " " + <code>{ access_token }</code></td>
            </tr>
            <tr>
                <td>Content-type</td>
                <td>"application/json"</td>
            </tr>
            <tr>
                <td class="bold">Data</td>
                <td colspan="2"><code>?hotel_id={ numeric | required }&paginate={ numeric | optional }</code></td>
            </tr>
            <tr>
                <td class="bold">Response</td>
                <td colspan="2"><per class="json-rooms_get_list-response"></per></td>
            </tr>
            <tr class="note">
                <td class="bold"><i class="fa fa-info-circle" aria-hidden="true"></i></td>
                <td colspan="2"><p>The information provided by this endpoint is paged, the parameter "paginate" is optional, its default value is 100, this means that it divides the total content among 100.</p></td>
            </tr>
        </tbody>
    </table>
    <h2 id="rooms_get_list_available">GET ROOMS LIST AVAILABLE</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">http://api-dev.mynuvola.net/api/v1/hotel-room-available</td>
            </tr>
            <tr>
                <td class="bold">Method</td>
                <td colspan="2">GET</td>
            </tr>
            <tr>
                <td class="bold" rowspan="2">Headers</td>
                <td>Autorization</td>
                <td><code>{ token_type }</code> + " " + <code>{ access_token }</code></td>
            </tr>
            <tr>
                <td>Content-type</td>
                <td>"application/json"</td>
            </tr>
            <tr>
                <td class="bold">Data</td>
                <td colspan="2"><code>?hotel_id={ numeric | required }&paginate={ numeric | optional }</code></td>
            </tr>
            <tr>
                <td class="bold">Response</td>
                <td colspan="2"><per class="json-rooms_get_list-response"></per></td>
            </tr>
            <tr class="note">
                <td class="bold"><i class="fa fa-info-circle" aria-hidden="true"></i></td>
                <td colspan="2"><p>The information provided by this endpoint is paged, the parameter "paginate" is optional, its default value is 100, this means that it divides the total content among 100.</p></td>
            </tr>
        </tbody>
    </table>
    <h2 id="rooms_new">NEW ROOM</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">http://api-dev.mynuvola.net/api/v1/hotel-room</td>
            </tr>
            <tr>
                <td class="bold">Method</td>
                <td colspan="2">POST</td>
            </tr>
            <tr>
                <td class="bold" rowspan="2">Headers</td>
                <td>Autorization</td>
                <td><code>{ token_type }</code> + " " + <code>{ access_token }</code></td>
            </tr>
            <tr>
                <td>Content-type</td>
                <td>"application/json"</td>
            </tr>
            <tr>
                <td class="bold">Data</td>
                <td colspan="2"><per id="json-rooms_new-data"></per></td>
            </tr>
            <tr>
                <td class="bold">Response</td>
                <td colspan="2"><per id="json-rooms_new-response"></per></td>
            </tr>
        </tbody>
    </table>
    <h2 id="rooms_update">UPDATE ROOM</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">http://api-dev.mynuvola.net/api/v1/hotel-room/<code>{ room_id }</code></td>
            </tr>
            <tr>
                <td class="bold">Method</td>
                <td colspan="2">UPDATE</td>
            </tr>
            <tr>
                <td class="bold" rowspan="2">Headers</td>
                <td>Autorization</td>
                <td><code>{ token_type }</code> + " " + <code>{ access_token }</code></td>
            </tr>
            <tr>
                <td>Content-type</td>
                <td>"application/json"</td>
            </tr>
            <tr>
                <td class="bold">Data</td>
                <td colspan="2"><per id="json-rooms_update-data"></per></td>
            </tr>
            <tr>
                <td class="bold">Response</td>
                <td colspan="2"><per id="json-rooms_update-response"></per></td>
            </tr>
        </tbody>
    </table>
    <h2 id="rooms_delete">DELETE ROOM</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">http://api-dev.mynuvola.net/api/v1/hotel-room/<code>{ room_id }</code></td>
            </tr>
            <tr>
                <td class="bold">Method</td>
                <td colspan="2">DELETE</td>
            </tr>
            <tr>
                <td class="bold" rowspan="2">Headers</td>
                <td>Autorization</td>
                <td><code>{ token_type }</code> + " " + <code>{ access_token }</code></td>
            </tr>
            <tr>
                <td>Content-type</td>
                <td>"application/json"</td>
            </tr>
            <tr>
                <td class="bold">Data</td>
                <td colspan="2"><code>?hotel_id={ hotel_id | numeric }</code></td>
            </tr>
            <tr>
                <td class="bold">Response</td>
                <td colspan="2"><per id="json-rooms_delete-response"></per></td>
            </tr>
        </tbody>
    </table>
@endsection

@section('script')
    $('.json-rooms_get_list-response').jsonViewer({
        current_page: "numeric",
        data: [{
            room_id: "numeric",
            location: "string",
        }],
        from: "numeric",
        last_page: "numeric",
        next_page_url: "string (url format)",
        path: "string (url format)",
        per_page: "numeric",
        prev_page_url: "string (url format) || NULL",
        top: "string (url format) || NULL",
        total: "numeric",
    },config);
    $('#json-rooms_new-data').jsonViewer({
        hotel_rooms: {
            hotel_id: "numeric | required",
            location: "string | required"
        }
    }, config);
    $('#json-rooms_new-response').jsonViewer({
        create: "boolean",
        room_id: "numeric",
        message: "string",
        description: "Array<Object>"
    }, config);
    $('#json-rooms_update-data').jsonViewer({
        location: "string | required"
    }, config);
    $('#json-rooms_update-response').jsonViewer({
        update: "boolean",
        message: "string",
        description: "Array<Object>"
    }, config);
    $('#json-rooms_delete-response').jsonViewer({
        delete: "boolean",
        message: "string",
        description: "Array<Object>"
    }, config);
@endsection