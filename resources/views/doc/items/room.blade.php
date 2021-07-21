@extends('doc.layouts.app')

@section('content')    
    <h1 id="rooms">Rooms</h1>
    <div class="separator max-700 mb-4"></div>
    <h2 id="rooms_list">Get Rooms List</h2>
    <table class="table table-striped">
        <tbody>
            @if($version == 'v1')
                <tr>
                    <td class="bold" colspan="2" style="background: #e83e3e; border-color: #e83e3e;"><small><code style="color:#fff;">Show last version <b>( Recommended )</b></code></small></td>
                    <td class="text-right" style="background: #e83e3e; border-color: #e83e3e;">
                        <a class="rooms" style="color:#fff;" href="{{ url("/doc/v2/room") }}#rooms">Version 2</a>
                    </td>
                </tr>
            @endif
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">{{$url}}/hotel-room</td>
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
                <td class="bold">Params</td>
                <td colspan="2">
                    <code>
                        @if($version == 'v2')                    
                            hotel_id={ numeric | required }<br>
                            paginate={ numeric | optional }<br>
                            status={ string [occupied,available] | optional }
                        @else
                            ?hotel_id={ numeric | required }&paginate={ numeric | optional }
                        @endif
                    </code>
                </td>
            </tr>
            <tr>
                <td class="bold">Response</td>
                <td colspan="2"><per class="json-rooms_get_list-response"></per></td>
            </tr>
            @if($version == 'v2')
                <tr class="note">
                    <td class="bold"><i class="material-icons"> info </i></td>
                    <td colspan="2"><p>When we use <b>status</b> parameter this field is not returned</p></td>
                </tr>
            @endif
            <tr class="note">
                <td class="bold"><i class="material-icons"> info </i></td>
                <td colspan="2"><p>The information provided by this endpoint is paged, the parameter "paginate" is optional, its default value is 100, this means that it divides the total content among 100.</p></td>
            </tr>            
        </tbody>
    </table>
    @if($version  == 'v1')
        <h2 id="rooms_list_available">Get Rooms List Available</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td colspan="3" style="background: #e83e3e; border-color: #e83e3e;">
                        <code style="color:#fff;" >This endpoint will not have support, so we recommend using the last version</code>
                    </td>
                </tr>
                <tr>
                    <td class="bold">URL</td>
                    <td colspan="2">{{$url}}/hotel-room-available</td>
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
                    <td class="bold"><i class="material-icons"> info </i></td>
                    <td colspan="2"><p>The information provided by this endpoint is paged, the parameter "paginate" is optional, its default value is 100, this means that it divides the total content among 100.</p></td>
                </tr>
            </tbody>
        </table>
    @endif
    <h2 id="rooms_new">New Room</h2>
    <table class="table table-striped">
        <tbody>
            @if($version == 'v1')
                <tr>
                    <td class="bold" colspan="2" style="background: #e83e3e; border-color: #e83e3e;"><small><code style="color:#fff;">Show last version <b>( Recommended )</b></code></small></td>
                    <td class="text-right" style="background: #e83e3e; border-color: #e83e3e;">
                        <a class="rooms" href="{{ url("/doc/v2/room") }}#rooms_new" style="color:#fff;">Version 2</a>
                    </td>
                </tr>
            @endif
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">{{$url}}/hotel-room</td>
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
            @if( $version == 'v2')
                <tr>
                    <td colspan="3" style="padding-bottom: 0;">
                        <ul class="nav nav-tabs" style="border: 0;">
                            <li class="nav-item">
                                <a class="nav-link active" href="#new_room_s_data"  data-toggle="tab">Single</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link"  href="#new_room_m_data"  data-toggle="tab">Multiple</a>
                            </li>
                        </ul>
                    </td>
                </tr>
                <tr>
                    <td colspan="3">
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="new_room_s_data">                            
                                <table class="table table-striped margin-0">
                                    <tbody>
                                        <tr>
                                            <td class="bold">Data</td>
                                            <td colspan="2"> <per id="json_new_room_s_data"></per> <td>
                                        </tr>     
                                        <tr>
                                            <td class="bold">Response</td>
                                            <td colspan="2"> <per id="json_new_room_s_res"></per> <td>
                                        </tr>                          
                                    </tbody>
                                </table>
                            </div>

                            <div class="tab-pane fade" id="new_room_m_data">
                                <table class="table table-striped margin-0">
                                    <tbody>
                                        <tr>
                                            <td class="bold">Data</td>
                                            <td colspan="2"> <per id="json_new_room_m_data"></per> <td>
                                        </tr>
                                        <tr>
                                            <td class="bold">Response</td>
                                            <td colspan="2"> <per id="json_new_room_m_res"></per> <td>
                                        </tr>                                    
                                    </tbody>
                                </table>                            
                            </div>
                        </div>
                    </td>
                </tr>
            @else
                <tr>
                    <td class="bold">Data</td>
                    <td colspan="2"> <per id="json_new_room_s_data"></per> </td>
                </tr>     
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"> <per id="json_new_room_s_res"></per> </td>     
                </tr>   
            @endif
        </tbody>
    </table>
    <h2 id="rooms_update">Update Room</h2>
    <table class="table table-striped">
        <tbody>
            @if($version == 'v1')
                <tr>
                    <td class="bold" colspan="2" style="background: #e83e3e; border-color: #e83e3e;"><small><code style="color:#fff;">Show last version <b>( Recommended )</b></code></small></td>
                    <td class="text-right" style="background: #e83e3e; border-color: #e83e3e;">
                        <a class="rooms" href="{{ url("/doc/v2/room") }}#rooms_update" style="color:#fff;">Version 2</a>
                    </td>
                </tr>
            @endif
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">
                    @if( $version == 'v2' )
                        <b>Single: </b> {{$url}}/hotel-room/<code>{ room_id }</code>
                        <hr>
                        <b>Multiples: </b> {{$url}}/hotel-room/<code>multiple</code>
                    @else
                        {{$url}}/hotel-room/<code>{ room_id }</code>
                    @endif
                </td>
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
            @if( $version == 'v2' )
                <tr>
                    <td colspan="3" style="padding-bottom: 0;">
                        <ul class="nav nav-tabs" style="border: 0;">
                            <li class="nav-item">
                                <a class="nav-link active" href="#update_room_s_data"  data-toggle="tab">Single</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link"  href="#update_room_m_data"  data-toggle="tab">Multiple</a>
                            </li>
                        </ul>
                    </td>
                </tr>
                <tr>
                    <td colspan="3">
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="update_room_s_data">                            
                                <table class="table table-striped margin-0">
                                    <tbody>
                                        <tr>
                                            <td class="bold">Data</td>
                                            <td colspan="2"> <per id="json_update_room_s_data"></per> <td>
                                        </tr>     
                                        <tr>
                                            <td class="bold">Response</td>
                                            <td colspan="2"> <per id="json_update_room_s_res"></per> <td>
                                        </tr>                          
                                    </tbody>
                                </table>
                            </div>
                            <div class="tab-pane fade" id="update_room_m_data">
                                <table class="table table-striped margin-0">
                                    <tbody>
                                        <tr>
                                            <td class="bold">Data</td>
                                            <td colspan="2"> <per id="json_update_room_m_data"></per> <td>
                                        </tr>
                                        <tr>
                                            <td class="bold">Response</td>
                                            <td colspan="2"> <per id="json_update_room_m_res"></per> <td>
                                        </tr>                                    
                                    </tbody>
                                </table>                            
                            </div>
                        </div>
                    </td>
                </tr>
            @else
                <tr>
                    <td class="bold">Data</td>
                    <td colspan="2"><per id="json_update_room_s_data"></per></td>
                </tr>
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"><per id="json_update_room_s_res"></per></td>
                </tr>
            @endif
        </tbody>
    </table>
    <h2 id="rooms_delete">Delete Room</h2>
    <table class="table table-striped">
        <tbody>
            @if($version == 'v1')
                <tr>
                    <td class="bold" colspan="2" style="background: #e83e3e; border-color: #e83e3e;"><small><code style="color:#fff;">Show last version <b>( Recommended )</b></code></small></td>
                    <td class="text-right" style="background: #e83e3e; border-color: #e83e3e;">
                        <a class="rooms" href="{{ url("/doc/v2/room") }}#rooms_delete" style="color:#fff;">Version 2</a>
                    </td>
                </tr>
            @endif
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">
                    @if( $version == 'v2' )
                        <b>Single: </b> {{$url}}/hotel-room/<code>{ room_id }</code>
                        <hr>
                        <b>Multiples: </b> {{$url}}/hotel-room/<code>multiple</code>
                    @else
                        {{$url}}/hotel-room/<code>{ room_id }</code>
                    @endif
                </td>
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
            <!--tr>
                <td class="bold">Data</td>
                <td colspan="2"><code>?hotel_id={ numeric | required }</code></td>
            </tr>
            <tr>
                <td class="bold">Response</td>
                <td colspan="2"><per id="json-rooms_delete-response"></per></td>
            </tr-->
            @if( $version == 'v2' )
                <tr>
                    <td colspan="3" style="padding-bottom: 0;">
                        <ul class="nav nav-tabs" style="border: 0;">
                            <li class="nav-item">
                                <a class="nav-link active" href="#delete_room_s_data"  data-toggle="tab">Single</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link"  href="#delete_room_m_data"  data-toggle="tab">Multiple</a>
                            </li>
                        </ul>
                    </td>
                </tr>
                <tr>
                    <td colspan="3">
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="delete_room_s_data">                            
                                <table class="table table-striped margin-0">
                                    <tbody>
                                        <tr>
                                            <td class="bold">Data</td>
                                            <td colspan="2"> <per id="json_delete_room_s_data"></per> <td>
                                        </tr>     
                                        <tr>
                                            <td class="bold">Response</td>
                                            <td colspan="2"> <per id="json_delete_room_s_res"></per> <td>
                                        </tr>                          
                                    </tbody>
                                </table>
                            </div>
                            <div class="tab-pane fade" id="delete_room_m_data">
                                <table class="table table-striped margin-0">
                                    <tbody>
                                        <tr>
                                            <td class="bold">Data</td>
                                            <td colspan="2"> <per id="json_delete_room_m_data"></per> <td>
                                        </tr>
                                        <tr>
                                            <td class="bold">Response</td>
                                            <td colspan="2"> <per id="json_delete_room_m_res"></per> <td>
                                        </tr>                                    
                                    </tbody>
                                </table>                            
                            </div>
                        </div>
                    </td>
                </tr>
            @else
                <tr>
                    <td class="bold">Data</td>
                    <td colspan="2"><per id="json_delete_room_s_data"></per></td>
                </tr>
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"><per id="json_delete_room_s_res"></per></td>
                </tr>
            @endif
        </tbody>
    </table>
@endsection

@section('script')
    $('.json-rooms_get_list-response').jsonViewer({
        current_page: "numeric",
        data: [{
            room_id: "numeric",
            location: "string",
            @if($version == 'v2')
                status: "string | 'ocupied' : 'available'"
            @endif
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


    $('#json_new_room_s_data').jsonViewer({
        hotel_rooms: {
            hotel_id: "numeric | required",
            location: "string | required"
        }
    }, config);

    $('#json_new_room_m_data').jsonViewer({
        hotel_id: "numeric | required",
        hotel_rooms: [
            { location: "string | required" },
            { location: "string | required" },
            { location: "string | required" },
            '...'
        ]          
    }, config);

    $('#json_new_room_s_res').jsonViewer({
        create: "boolean",
        room_id: "numeric",
        message: "string",
        description: "Array<Object>"
    }, config);
    
    $('#json_new_room_m_res').jsonViewer({
        create: "boolean",
        "success" : "Array<Object>",
        "error" : "Array<Object>"
    }, config);




    $('#json_update_room_s_data').jsonViewer({
        hotel_id: "numeric | required",
        location: "string | required"
    }, config);

    $('#json_update_room_m_data').jsonViewer({
        hotel_id: "numeric | required",
        location: [
            {
                room_id: "numeric | required",
                location: "string | required"   
            },
            {
                room_id: "numeric | required",
                location: "string | required"   
            },
            {
                room_id: "numeric | required",
                location: "string | required"   
            },
            "..."
        ]
    }, config);

    $('#json_update_room_s_res').jsonViewer({
        update: "boolean",
        message: "string",
        description: "Array<Object>"
    }, config);

    $('#json_update_room_m_res').jsonViewer({
        update: "boolean",
        success: "Array<Object>",
        error: "Array<Object>"
    }, config);

    $('#json_delete_room_s_data').jsonViewer({
        hotel_id: "numeric | required"        
    }, config);

    $('#json_delete_room_m_data').jsonViewer({
        hotel_id: "numeric | required",
        location: [
            {
                room_id: "numeric | required"
            },
            {
                room_id: "numeric | required"
            },
            {
                room_id: "numeric | required"
            },
            "..."
        ]
    }, config);

    $('#json_delete_room_s_res').jsonViewer({
        delete: "boolean",
        message: "string",
        description: "Array<Object>"
    }, config);

    $('#json_delete_room_m_res').jsonViewer({
        delete: "boolean",
        success: "Array<Object>",
        error: "Array<Object>"
    }, config);

@endsection