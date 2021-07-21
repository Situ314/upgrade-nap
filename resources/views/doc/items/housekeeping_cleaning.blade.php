@extends('doc.layouts.app')

@section('content')
    <h1 id="hsk">Housekeeping</h1>
    
    <h2 id="hsk_list">Get Housekeeping Status List</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">{{$url}}/hsk</td>
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
                <td colspan="2">
                    <code>
                        ?hotel_id={ numeric | required }<br>
                        &paginate={ numeric | optional }<br>
                        &hk_status={ numeric | optional }<br>
                        &front_desk_status={ numeric | optional } <br>
                        &assigned_date={ YYYY-MM-dd | optional }<br>
                        &room_id={ numeric | optional}
                    </code>
                </td>
            </tr>
            <tr>
                <td class="bold">Response</td>
                <td colspan="2"><per id="json-hsk_get_list-response"></per></td>
            </tr>
            <tr class="note">
                <td class="bold"><i class="material-icons"> info </i></td>
                <td colspan="2">
                    <ul>
                        <li><b>Values of hk_status:</b>
                            <ul style="margin-left: 25px;">
                                <li>1: Dirty, 2: Cleaning, 3: Clean, 4: Inspected, 5: Pickup</li>
                            </ul>
                        </li>
                        <li><b>Values of front_desk_status: </b> 
                            <ul style="margin-left: 25px;">
                                <li>1: Vacant, 2: Stay Over, 3: Due In, 4: Due Out, 5: Check Out, 6: Arrived, 7: Day in Use, 8: Out of Order, 9: Out of Service</li>
                            </ul>
                        </li>
                    </ul>
                    <p>The information provided by this endpoint is paged, the parameter "paginate" is optional, its default value is 100, this means that it divides the total content among 100.</p>
                </td>
            </tr>
        </tbody>
    </table>

    <h2 id="hsk_by_cleaning_id">Get Housekeeping Status by Cleaning ID</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">{{$url}}/hsk/<code>{cleaning_id}</code></td>
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
                <td class="bold">Response</td>
                <td colspan="2"><per id="json-hsk_get-response"></per></td>
            </tr>
        </tbody>
    </table>


    <h2 id="hsk_list">Get Housekeeper List</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">{{$url}}/housekeeper</td>
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
                <td colspan="2">
                    <code>
                        ?hotel_id={ numeric | required }
                    </code>
                </td>
            </tr>
            <tr>
                <td class="bold">Response</td>
                <td colspan="2"><per id="json-housekeepert_list-response"></per></td>
            </tr>
            <tr class="note">
                <td class="bold"><i class="material-icons"> info </i></td>
                <td colspan="2">
                    <p>The information provided by this endpoint is paged, the parameter "paginate" is optional, its default value is 100, this means that it divides the total content among 100.</p>
                </td>
            </tr>
        </tbody>
    </table>

    <h2 id="hsk_update">Update Housekeeeping Status</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">{{$url}}/hsk/<code>{ cleaning_id }</code></td>
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
                <td colspan="2"><per id="json-hsk_update-data"></per></td>
            </tr>
            <tr>
                <td class="bold">Response</td>
                <td colspan="2"><per id="json-hsk_update-response"></per></td>
            </tr>
        </tbody>
    </table>

    <h2 id="pick_up">Pickup Room</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">{{$url}}/hsk/pickup</td>
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
                <td colspan="2"><per id="json-pick_up-data"></per></td>
            </tr>
            <tr>
                <td class="bold">Response</td>
                <td colspan="2"><per id="json-pick_up-response"></per></td>
            </tr>
            
            <tr class="note">
                <td class="bold"><i class="material-icons"> info </i></td>
                <td colspan="2">
                    <p>A Room can be picked up only if the room is VC or INS.</p>
                </td>
            </tr>
        </tbody>
    </table>

@endsection

@section('script')

    $('#json-hsk_get_list-response').jsonViewer({
        current_page: "numeric",
        data: [{
            cleaning_id: "numeric",
            room_id: "numeric",
            hk_status: "numeric",
            front_desk_status: "numeric",
            count_by_hotel_id: "numeric",
            created_on: "datetime",
            assigned_date: "date",
            room: {
                location: "string",
                room_id: "numeric"
            }
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

    $('#json-hsk_get-response').jsonViewer({
        cleaning_id: "numeric",
        room_id: "numeric",
        hk_status: "numeric",
        front_desk_status: "numeric",
        count_by_hotel_id: "numeric",
        created_on: "datetime",
        assigned_date: "date",
        room: {
            location: "string",
            room_id: "numeric"
        }
    },config);

    $('#json-housekeepert_list-response').jsonViewer({
        housekepers: [
            {
                housekeeper_id: "numeric",
                firstname: "string",
                lastname: "string",
                username: "string",
                email: "string"
            },
            {
                housekeeper_id: "numeric",
                firstname: "string",
                lastname: "string",
                username: "string",
                email: "string"
            },
            {
                housekeeper_id: "numeric",
                firstname: "string",
                lastname: "string",
                username: "string",
                email: "string"
            }
        ]
    },config);

    $('#json-hsk_update-data').jsonViewer({
        hsk: {
            hk_status: "numeric | required",
        }
    },config);

    $('#json-hsk_update-response').jsonViewer({
        update: "boolean",
        message: "string",
        description: "Array<object>"
    },config);

    $('#json-pick_up-data').jsonViewer({
        hotel_id: "numeric | require",
        pickup_event: {
            issue:      "string | optional",
            room_id:    "string | required_without: location",
            location:   "string | required_without: room_id",
            dept:       "string | required",
            tag:        "string | required",
            priority:   "numeric | optional | 1 => 'low', 2 => 'medium', 3 => 'high'"
        }
    },config);

    $('#json-pick_up-response').jsonViewer({
        pickup: "boolean",
        message: "string",
        description: "Array<object>"
    },config);
    
@endsection