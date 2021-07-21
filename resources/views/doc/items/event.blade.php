@extends('doc.layouts.app')

@section('content')
    <h1 id="event">8. Events</h1>
    <div class="separator max-700 mb-1"></div>
    <h2 id="event_list">Get Events List</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">{{$url}}/event</td>
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
            @if($version == 'v2')
            <tr>
                <td class="bold">Filter</td>
                <td colspan="2">
                    <b>Guest ID: </b> Returns a list of events related to a specific guest. <br>
                </td> 
            </tr>
            @endif
            <tr>
                <td class="bold">Data</td>
                <td colspan="2">
                    <code>
                        @if($version == 'v1')
                            ?hotel_id={ numeric | required }&paginate={ numeric | optional }
                        @else
                            ?hotel_id={ numeric | required }<br>
                            &paginate={ numeric | optional | default: 50 }<br>
                            &guest_id={ numeric | optional }
                        @endif
                    </code>
                </td>
            </tr>            
            <tr>
                <td class="bold">Response</td>
                <td colspan="2"><per id="json-event_get_list-response"></per></td>
            </tr>
        </tbody>
    </table>

    @if($version == 'v1')
        <h2 id="dept_tags">
            Get Event List by Guest ID
            <small>
                <a class="dept" href="{{ url("/doc/v2/event") }}#event_list_by_guest_id">Go to the link</a>
            </small>
        </h2>
        <p class="max-700">The endpoint provides a list of events related to a specific guest.</p>
        <div class="separator max-700 mb-4"></div>

        
        <h2 id="event_new">New Event</h2>
        <table class="table table-striped">
            <tbody>
                @if($version == 'v1')
                    <tr>
                        <td class="bold" colspan="2" style="background: #e83e3e; border-color: #e83e3e;"><small><code style="color:#fff;">Show last version <b>( Recommended )</b></code></small></td>
                        <td class="text-right" style="background: #e83e3e; border-color: #e83e3e;">
                            <a class="event" href="{{ url("/doc/v2/event") }}#event_new" style="color:#fff;">Version 2</a>
                        </td>
                    </tr>
                @endif
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">{{$url}}/event</td>
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
        <h2 id="event_update">Update Event</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">{{$url}}/event/<code>{ event_id }</code></td>
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
        <h2 id="event_delete">Delete Event</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">{{$url}}/event/<code>{ event_id }</code></td>
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
    @else
        <h2 id="event_new">New Event</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">{{$url}}/event</td>
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
                    <td colspan="2"><per id="json-new_event_2-data"><per></td>
                </tr>
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"><per id="json-new_event_2-response"></per></td>
                </tr>
            </tbody>
        </table>
        <h2 id="event_update">Update Event</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">{{$url}}/event/<code>{ event_id }</code></td>
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
                    <td colspan="2"><per id="json-update_event-v2-data"><per></td>
                </tr>
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"><per id="json-update_event-v2-response"></per></td>
                </tr>
            </tbody>
        </table>
        {{-- <h2 id="event_list_by_guest_id">Get Event List by Guest ID</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">{{$url}}/event/guest/<code>{ guest_id }</code></td>
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
                    <td colspan="2"><per id="json-event_get_list_by_guest_id-response"></per></td>
                </tr>
                <tr class="note">
                    <td class="bold"><i class="material-icons"> info </i></td>
                    <td colspan="2"><p>The information provided by this endpoint is paged, the parameter "paginate" is optional, its default value is 100, this means that it divides the total content among 100.</p></td>
                </tr>
            </tbody>
        </table>         
        --}}
    @endif
    
    <h2 id="event_delete">Delete Event</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">{{$url}}/event/<code>{ event_id }</code></td>
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
        hotel_id: "numeric | required",
        events: {      
            issue: "string | optional",
            guest_id: "numeric | required_without: room_id",            
            room_id: "string | required_without: guest_id",
            location: "string | required_without: room_id",
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

    $('#json-new_event_2-data').jsonViewer({
        hotel_id: "numeric | required",
        events: {      
            issue: "string | optional",
            guest_id: "numeric | required_without: room_id",            
            room_id: "string | required_without: guest_id",
            location: "string | required_without: room_id",
            dept_id: "string | required",
            tag_id: "string | required",
            priority: "numeric | optional | 1 => 'low', 2 => 'medium', 3 => 'high'"
        }
    }, config);

    $('#json-new_event_2-response').jsonViewer({
        create: "boolean",
        event_id: "numeric",
        message: "string",
        description: "Array<object>"
    }, config);

    $('#json-update_event-data').jsonViewer({
        hotel_id: "numeric|required",
        events: {            
            guest_id: "numeric|optional",
            issue: "string|optional",
            room_id: "string|optional",
            dept_tag_id: "string|required",
            status: "numeric|optional|1:'pending',2:'completed',3:'closed'",
            priority: "numeric|optional|1:'low',2:'medium',3:'high'"
        }
    }, config);

    $('#json-update_event-v2-data').jsonViewer({
        hotel_id: "numeric|required",
        events: {            
            guest_id:   "numeric|optional",
            issue:      "string|optional",
            room_id:    "string|optional",
            location:   "string|optional",
            dept_id:    "numeric|required_without:tag_id",
            tag_id:     "numeric|required_without:dept_id",
            status:     "numeric|optional|1:'pending',2:'completed',3:'closed'",
            priority:   "numeric|optional|1:'low',2:'medium',3:'high'"
        }
    }, config);

    

    $('#json-update_event-v2-response').jsonViewer({
        update: "boolean",
        message: "string",
        description: "Array<object> OR null"
    }, config);

    $('#json-update_event-response').jsonViewer({
        update: "boolean",
        message: "string",
        description: "Array<object>"
    }, config);

    $('#json-event_get_list-response').jsonViewer({
        current_page: "numeric",
        data: [{
            issue: "string",
            "guest": {
                "guest_id": "numeric",
                "firstname": "string",
                "lastname": "string",
                "email_address": "string"
            },
            "room": {
                "room_id": "numeric",
                "location": "string"
            },
            "dep_tag": {
                "dep_tag_id": "numeric",
                "departament": {
                    "dept_id": "numeric",
                    "dept_name": "string",
                    "short_name": "string"
                },
                "tag": {
                    "tag_id": "numeric",
                    "tag_name": "string"
                }
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

    $('#json-event_get_list_by_guest_id-response').jsonViewer({
        current_page: "numeric",
        data: [{
            issue: "string",
            "room": {
                "room_id": "numeric",
                "location": "string"
            },
            "dep_tag": {
                "dep_tag_id": "numeric",
                "departament": {
                    "dept_id": "numeric",
                    "dept_name": "string",
                    "short_name": "string"
                },
                "tag": {
                    "tag_id": "numeric",
                    "tag_name": "string"
                }
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

    

    $('#json-delete_event-response').jsonViewer({
        delete: "boolean",
        message: "string",
        description: "Array<object>"
    }, config);

@endsection