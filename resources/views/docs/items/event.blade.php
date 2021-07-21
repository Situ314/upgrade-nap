@extends('layouts.app')

@section('content')
    <h1 id="event">EVENT</h1>
    <h2 id="evnt_get_list">GET EVENT LIST</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">http://api-dev.mynuvola.net/api/v1/event</td>
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
                <td colspan="2"><per id="json-event_get_list-response"></per></td>
            </tr>
            <tr class="note">
                <td class="bold"><i class="fa fa-info-circle" aria-hidden="true"></i></td>
                <td colspan="2"><p>The information provided by this endpoint is paged, the parameter "paginate" is optional, its default value is 100, this means that it divides the total content among 100.</p></td>
            </tr>
        </tbody>
    </table>
    <h2 id="event_new">NEW EVENT</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">http://api-dev.mynuvola.net/api/v1/event</td>
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
                <td colspan="2"><per id="json-new_event-data"><per></td>
            </tr>
            <tr>
                <td class="bold">Response</td>
                <td colspan="2"><per id="json-new_event-response"></per></td>
            </tr>
        </tbody>
    </table>
    <h2 id="event_update">UPDATE EVENT</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">http://api-dev.mynuvola.net/api/v1/event</td>
            </tr>
            <tr>
                <td class="bold">Method</td>
                <td colspan="2">PUT</td>
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
                <td colspan="2"><per id="json-update_event-data"><per></td>
            </tr>
            <tr>
                <td class="bold">Response</td>
                <td colspan="2"><per id="json-update_event-response"></per></td>
            </tr>
        </tbody>
    </table>
    <h2 id="event_delete">DELETE EVENT</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">http://api-dev.mynuvola.net/api/v1/event/<code>{ event_id }</code></td>
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
                <td colspan="2"><code>?hotel_id={ numeric | required }</code></td>
            </tr>
            <tr>
                <td class="bold">Response</td>
                <td colspan="2"><per id="json-delete_event-response"></per></td>
            </tr>
        </tbody>
    </table>
@endsection

@section('script')
    $('#json-new_event-data').jsonViewer({
        event: {
            hotel_id: "numeric | required",
            guest_id: "numeric | optional",
            issue: "string | optional",
            room_id: "string | optional",
            dept_tag_id: "string | required",
            priority: "numeric | optional | 1 => 'low', 2 => 'medium', 3 => 'high'"
        }
    }, config);
    $('#json-new_event-response').jsonViewer({
        create: "boolean",
        event_id: "numeric",
        message: "string",
        description: "Array<object>"
    }, config);
    $('#json-update_event-data').jsonViewer({
        event: {
            hotel_id: "numeric | required",
            guest_id: "numeric | optional",
            issue: "string | optional",
            room_id: "string | optional",
            dept_tag_id: "string | required",
            status: "numeric | optional | 1 => 'pending', 2 => 'completed', 3 => 'closed'",
            priority: "numeric | optional | 1 => 'low', 2 => 'medium', 3 => 'high'"
        }
    }, config);
    $('#json-update_event-response').jsonViewer({
        update: "boolean",
        message: "string",
        description: "Array<object>"
    }, config);
    $('#json-event_get_list-response').jsonViewer({
        current_page: "numeric",
        data: [{
            guest_id: "numeric",
            issue: "string",
            room_id: "numeric",
            dept_tag_id: "numeric",
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
    $('#json-delete_event-response').jsonViewer({
        delete: "boolean",
        message: "string",
        description: "Array<object>"
    }, config);
@endsection