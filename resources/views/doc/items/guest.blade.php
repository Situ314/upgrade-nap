@extends('doc.layouts.app')

@section('content')
    {{------------------------------------------------------------------------------------------------------------------------------------------------}}
    <h1 id="guest">Guest</h1>
    <div class="separator max-700 mb-4"></div>

    {{------------------------------------------------------------------------------------------------------------------------------------------------}}
    <h2 id="guest_list">Get Guest List</h2>
    <table class="table table-striped">
        <tbody>
            @if($version == 'v1')
                <tr>
                    <td class="bold" colspan="2" style="background: #e83e3e; border-color: #e83e3e;"><small><code style="color:#fff;">Show last version <b>( Recommended )</b></code></small></td>
                    <td class="text-right" style="background: #e83e3e; border-color: #e83e3e;">
                        <a class="rooms" href="{{ url("/doc/v2/guest") }}#guest" style="color:#fff;">Version 2</a>
                    </td>
                </tr>
            @endif
            <tr>
                <td class="bold" width="20%">URL</td>
                <td colspan="2">{{$url}}/guest</td>
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
                        hotel_id={ numeric | required }<br>
                        paginate={ numeric | optional }
                        @if($version == 'v2')
                            <br>status={ numeric | optional | 1: active, 2: inactive }
                        @endif
                    </code>
                </td>
            </tr>            
            <tr>
                <td class="bold">Response</td>
                <td colspan="2"><per id="guest_list_response"></per></td>
            </tr>
            <tr class="note">
                <td class="bold"><i class="material-icons"> info </i></td>
                <td colspan="2"><p>The information provided by this endpoint is paged, the parameter "paginate" is optional, its default value is 100, this means that it divides the total content among 100.</p></td>
            </tr>
        </tbody>
    </table>

    {{------------------------------------------------------------------------------------------------------------------------------------------------}}
    @if($version == 'v2')
        <h2 id="guest_list">Get Guest by Guest Number</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold" width="20%">URL</td>
                    <td colspan="2">{{$url}}/partner/guest/<code>{ guest_number }</code></td>
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
                        <code>hotel_id={ numeric | required }</code>
                    </td>
                </tr>            
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"><per id="guest_by_guest_number_response"></per></td>
                </tr>
                <tr class="note">
                    <td class="bold"><i class="material-icons"> info </i></td>
                    <td colspan="2"><p>The information provided by this endpoint is paged, the parameter "paginate" is optional, its default value is 100, this means that it divides the total content among 100.</p></td>
                </tr>
            </tbody>
        </table>
    @endif

    {{------------------------------------------------------------------------------------------------------------------------------------------------}}
    <h2 id="guest_new">New Guest</h2>
    <table class="table table-striped">
        <tbody>
            @if($version == 'v1')
                <tr>
                    <td class="bold" colspan="2" style="background: #e83e3e; border-color: #e83e3e;"><small><code style="color:#fff;">Show last version <b>( Recommended )</b></code></small></td>
                    <td class="text-right" style="background: #e83e3e; border-color: #e83e3e;">
                        <a class="rooms" href="{{ url("/doc/v2/guest") }}#guest_new" style="color:#fff;">Version 2</a>
                    </td>
                </tr>
            @endif
            <tr>
                <td class="bold" width="20%">URL</td>
                <td colspan="2">{{$url}}/guest</td>
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
            @if($version == 'v1')
                <tr>
                    <td class="bold">Data</td>
                    <td colspan="2"> <per id="new_guest_data_v1"></per> </td>
                </tr>     
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"> <per id="new_guest_v1_response"></per> </td>     
                </tr>   
            @else
                <tr>
                    <td colspan="3" style="padding-bottom: 0;">
                        <ul class="nav nav-tabs" id="newGuestTab" role="tablist" style="border: 0;">
                            <li class="nav-item">
                                <a class="nav-link active" id="new-guest-one-tab" data-toggle="tab" href="#new-guest-single" role="tab" aria-controls="single" aria-selected="true">Single</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="new-guest-multiple-tab" data-toggle="tab" href="#new-guest-multiples" role="tab" aria-controls="multiple" aria-selected="false">Multiple</a>
                            </li>
                        </ul>
                    </td>
                </tr>
                <tr>
                    <td class="bold">Data</td>
                    <td colspan="2">                    
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="new-guest-single" role="tabpanel" aria-labelledby="new-guest-one-tab">
                                <per id="new_guest_data_one"></per>
                            </div>
                            <div class="tab-pane fade" id="new-guest-multiples" role="tabpanel" aria-labelledby="new-guest-multiple-tab">
                                <per id="new_guest_data_multiple"></per>
                            </div>
                        </div>
                    </td>
                    <tr>
                        <td class="bold">Response</td>
                        <td colspan="2"><per id="new_guest_v1_response"></per></td>
                    </tr>
                </tr>
            @endif            
            <tr class="note">
                <td class="bold"><i class="material-icons"> info </i></td>
                <td colspan="2">
                    <ul>
                        <li><b>hotel_id:</b> this parameter refers to the hotel identifier where the operation will be performed <a href="#get_hotels">GET HOTELS</a>.</li>
                        <!--li><b>firstname</b> and <b>lastname:</b> the combination of these fields are unique in the hotel system.</li>
                        <li><b>email_address</b> or <b>phone_number:</b> To be able to register one of this fields is necessary </li-->
                        <li>
                            <b>email_address</b> and <b>phone_no:</b> this field is unique in the hotel system.
                            <ul>
                                <li><a href="#validate_guest_s_email" style="color: #fff;">Validate email</a> </li>
                                <li><a href="#validate_guest_s_phone" style="color: #fff;">Validate phone</a></li>
                            </ul>
                        </li>
                        <li><b>room_no / room:</b> this field refers to the room_id / location parameter obtained from the end point <a href="#rooms_get_list">GET ROOMS LIST</a>.</li>
                    </ul>
                </td>
            </tr>
        </tbody>
    </table>
    @if($version == 'v2')
        <h2 id="guest_new_multiple">New Guest with multiple reservations</h2>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <td class="bold" width="20%">URL</td>
                    <td colspan="2">{{$url}}/guest/multiple-reservations</td>
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
                    <td colspan="2"> <per id="new_guest_multiple_data"></per> </td>
                </tr>     
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"> <per id="new_guest_multiple_response"></per> </td>     
                </tr>   
                <tr class="note">
                    <td class="bold"><i class="material-icons"> info </i></td>
                    <td colspan="2">
                        <ul>
                            <li><b>hotel_id:</b> this parameter refers to the hotel identifier where the operation will be performed <a href="#get_hotels">GET HOTELS</a>.</li>
                            <li>
                                <b>email_address</b> and <b>phone_no:</b> this field is unique in the hotel system.
                                <ul>
                                    <li><a href="#validate_guest_s_email" style="color: #fff;">Validate email</a> </li>
                                    <li><a href="#validate_guest_s_phone" style="color: #fff;">Validate phone</a></li>
                                </ul>
                            </li>
                            <li><b>room_no / room:</b> this field refers to the room_id / location parameter obtained from the end point <a href="#rooms_get_list">GET ROOMS LIST</a>.</li>
                        </ul>
                    </td>
                </tr>
            </tbody>
        </table>
    @endif

    {{------------------------------------------------------------------------------------------------------------------------------------------------}}
    <h2 id="guest_update">Update Guest</h2>
    <table class="table table-striped">
        <tbody>
            @if($version == 'v1')
                <tr>
                    <td class="bold" colspan="2" style="background: #e83e3e; border-color: #e83e3e;"><small><code style="color:#fff;">Show last version <b>( Recommended )</b></code></small></td>
                    <td class="text-right" style="background: #e83e3e; border-color: #e83e3e;">
                        <a class="rooms" href="{{ url("/doc/v2/guest") }}#guest_update" style="color:#fff;">Version 2</a>
                    </td>
                </tr>
            @endif
            <tr>
                <td class="bold" width="20%">URL</td>
                @if($version == 'v1')
                    <td colspan="2">
                        {{$url}}/guest/<code>{ guest_id }</code>
                    </td>
                @else
                    <td colspan="2">
                        <b>Single: </b> {{$url}}/guest/<code>{ guest_id }</code>
                        <hr>
                        <b>Multiples: </b> {{$url}}/guest/<code>multiple</code>
                    </td>
                @endif
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
            @if($version == 'v1')
                <tr>
                    <td class="bold">Data</td>
                    <td colspan="2"><per id="guest_update_v1_data"></per></td>
                </tr>
                <tr>
                    <td class="bold">Response</td>
                    <td colspan="2"><per id="guest_update_v1_response"></per></td>
                </tr>
            @else
                <tr>
                    <td colspan="3" style="padding-bottom: 0;">
                        <ul class="nav nav-tabs" id="updateGuestTab" role="tablist" style="border: 0;">
                            <li class="nav-item">
                                <a class="nav-link active" id="update-guest-single-tab" data-toggle="tab" href="#update-guest-single" role="tab" aria-controls="single" aria-selected="true">Single</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="update-guest-multiples-tab" data-toggle="tab" href="#update-guest-multiples" role="tab" aria-controls="multiple" aria-selected="false">Multiple</a>
                            </li>
                        </ul>
                    </td>
                </tr>
                <tr>
                    <td colspan="3">
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="update-guest-single" role="tabpanel" aria-labelledby="new-guest-one-tab">
                                <table class="table table-striped">
                                    <tbody>
                                        <tr>
                                            <td class="bold" width="18%">Data</td>
                                            <td colspan="2"><per id="guest_update_one_data"></per></td>
                                        </tr>
                                        <tr>
                                            <td class="bold">Response</td>
                                            <td colspan="2"><per id="guest_update_one_response"></per></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="tab-pane fade" id="update-guest-multiples" role="tabpanel" aria-labelledby="new-guest-multiple-tab">
                                <table class="table table-striped">
                                    <tbody>
                                        <tr>
                                            <td class="bold" width="18%">Data</td>
                                            <td colspan="2"><per id="guest_update_multiple_data"></per></td>
                                        </tr>
                                        <tr>
                                            <td class="bold">Response</td>
                                            <td colspan="2"><per id="guest_update_multiple_response"></per></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </td>
                </tr>
            @endif
        </tbody>
    </table>

    {{------------------------------------------------------------------------------------------------------------------------------------------------}}
    <h2 id="guest_delete">Delete Guest</h2>
    <table class="table table-striped">
        <tbody>
            @if($version == 'v1')
                <tr>
                    <td class="bold" colspan="2" style="background: #e83e3e; border-color: #e83e3e;"><small><code style="color:#fff;">Show last version <b>( Recommended )</b></code></small></td>
                    <td class="text-right" style="background: #e83e3e; border-color: #e83e3e;">
                        <a class="rooms" href="{{ url("/doc/v2/guest") }}#guest_delete" style="color:#fff;">Version 2</a>
                    </td>
                </tr>
            @endif
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">
                    @if($version == 'v1')
                        {{$url}}/guest/<code>{ guest_id }</code>
                    @else
                        <b>Single: </b> {{$url}}/guest/<code>{ guest_id }</code>
                        <hr>
                        <b>Multiples: </b> {{$url}}/guest/<code>multiple</code>
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
            <tr>
                <td class="bold">
                    @if($version == 'v1') 
                        Data
                    @else
                        Data: Single | multiple
                    @endif
                </td>
                <td colspan="2">
                    <per id="json-guest_delete-data"></per>
                </td>
            </tr>                                      
            <tr>
                <td class="bold">
                    @if($version == 'v1') 
                        Response
                    @else
                        Response: multiple | single
                    @endif
                </td>
                <td colspan="2"><per id="json-guest_delete-response"></per></td>
            </tr>
        </tbody>
    </table>

    {{------------------------------------------------------------------------------------------------------------------------------------------------}}
    <h3 id="validate_email">Validate Email</h3>
    <table class="table table-striped">
        <tbody>
            @if($version == 'v1')
                <tr>
                    <td class="bold" colspan="2" style="background: #e83e3e; border-color: #e83e3e;"><small><code style="color:#fff;">Show last version <b>( Recommended )</b></code></small></td>
                    <td class="text-right" style="background: #e83e3e; border-color: #e83e3e;">
                        <a class="rooms" href="{{ url("/doc/v2/guest") }}#validate_email" style="color:#fff;">Version 2</a>
                    </td>
                </tr>
            @endif
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">{{$url}}/guest/validate/email/<code>{ hotel_id }</code>/<code>{ email }</code></td>
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
                <td colspan="2"><per id="json-validate_guest_s_email-response"></per></td>
            </tr>
            <tr class="note">
                <td class="bold"><i class="material-icons"> info </i></td>
                <td colspan="2"><p>The variable "exists" returns null, this means that the information supplied (hotel_id) is not correct.</p></td>
            </tr>
        </tbody>
    </table>

    {{------------------------------------------------------------------------------------------------------------------------------------------------}}
    <h3 id="validate_phone">Validate Phone</h3>
    <table class="table table-striped">
        <tbody>
            @if($version == 'v1')
                <tr>
                    <td class="bold" colspan="2" style="background: #e83e3e; border-color: #e83e3e;"><small><code style="color:#fff;">Show last version <b>( Recommended )</b></code></small></td>
                    <td class="text-right" style="background: #e83e3e; border-color: #e83e3e;">
                        <a class="rooms" href="{{ url("/doc/v2/guest") }}#validate_phone" style="color:#fff;">Version 2</a>
                    </td>
                </tr>
            @endif
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">{{$url}}/guest/validate/phone/<code>{ hotel_id }</code>/<code>{ phone }</code></td>
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
                <td colspan="2"><per id="json-validate_guest_s_phone-response"></per></td>
            </tr>
            <tr class="note">
                <td class="bold"><i class="material-icons"> info </i></td>
                <td colspan="2"><p>The variable "exists" returns null, this means that the information supplied (hotel_id) is not correct.</p></td>
            </tr>
        </tbody>
    </table>

    {{------------------------------------------------------------------------------------------------------------------------------------------------}}
    <h2 id="close_checkin">Finish a stay <b>Checkedout</b></h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">{{$url}}/close-guest-checkin</td>
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
                <td colspan="2"><code>?hotel_id={ numeric | required }&guest_id={ numeric | required }&sno={ numeric | required }</code></td>
            </tr>
            <tr>
                <td class="bold">Response</td>
                <td colspan="2"><per id="json-close_guest_checkin-response"></per></td>
            </tr>
        </tbody>
    </table>    
@endsection

@section('script')

    $('#guest_list_response').jsonViewer({
        current_page: "numeric",
        data: [{            
            guest_id: "numeric",
            firstname: "string",
            lastname: "string",
            email_address: "string",
            phone_no: "string",
            @if($version == 'v2')
                guest_checking_detail: [
                    {
                        sno: "numeric",
                        check_in: "YYYY-mm-dd H:i:s",
                        check_out: "YYYY-mm-dd H:i:s",
                        status: "numeric",
                        reservation_number: "string",
                        room: {
                            room_id: "numeric",
                            location: "string"
                        }
                    },
                ]
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

    $('#guest_by_guest_number_response').jsonViewer({
        guest_id: "numeric",
        firstname: "string",
        lastname: "string",
        email_address: "string",
        phone_no: "string",
        guest_number: "string",
        guest_checking_detail: [
            {
                sno: "numeric",
                guest_id: "numeric",
                room_no: "numeric",
                check_in: "string",
                check_out: "string",
                status: "numeric",
                room: {
                    room_id: "numeric",
                    location: "string"
                }
            }
        ]
    }, config);

    $('#new_guest_data_v1').jsonViewer({
        guest_registration: {
            hotel_id:       "numeric | required",
            firstname:      "string | required",
            lastname:       "string | required",
            email_address:  "string | required_without:phone_no",
            phone_no:       "string | required_without:email_address",
            angel_status:   "numeric | required | in: 0 => no send,1 => send",
            language:       "string | option | acepted value: 'en':'english', 'es':'spanish'"
        },
        guest_checkin_details: {
            room_no:    "numeric | required_without: room",
            room:       "string | required_without: room_no",
            check_in:   "YYYY-mm-dd H:i:s | required",
            check_out:  "YYYY-mm-dd H:i:s | required",
            reservation_number: "string | |optional"
        }
    },config);

    $('#new_guest_multiple_data').jsonViewer({
        hotel_id: "numeric | required",
        guest: {
            firstname: "string | required",
            lastname: "string | required",
            email_address: "string | email | required_without:phone_no",
            phone_no: "string | phone number format | required_without:email_address",
            angel_status: "numeric | required | in:0,1",
            category: "numeric | in:0,1,2,3,4,5",
            language: "string | in:en,es",
            guest_number: "string",
            comment: "string",
        },
        reservations: [
            {
                room: "string | required_without: room_no",
                room_no: "string | required_without:room",
                check_in: "required | date_format:'YYYY-mm-dd H:i:s'",
                check_out: "required | date_format:'YYYY-mm-dd H:i:s' | after:check_in",
                comment: "string",
                reservation_number: "string | required | unique"
            },
            {
                room: "string | required_without: room_no",
                room_no: "string | required_without:room",
                check_in: "required | date_format:'YYYY-mm-dd H:i:s'",
                check_out: "required | date_format:'YYYY-mm-dd H:i:s' | after:check_in",
                comment: "string",
                reservation_number: "string | required | unique"
            }
        ]
    },config);

    $('#new_guest_data_one').jsonViewer({
        guest_registration: {
            hotel_id:       "numeric | required",
            firstname:      "string | required",
            lastname:       "string | required",
            email_address:  "string | required_without:phone_no",
            phone_no:       "string | required_without:email_address",
            angel_status:   "numeric | required | in: 0 => no send,1 => send",
            language:       "string | option | acepted value: 'en':'english', 'es':'spanish'"
        },
        guest_checkin_details: {
            room_no:    "numeric | required_without: room",
            room:       "string | required_without: room_no",
            check_in:   "YYYY-mm-dd H:i:s | required",
            check_out:  "YYYY-mm-dd H:i:s | required",
            reservation_number: "string | optional",
        }
    },config);

    $('#new_guest_data_multiple').jsonViewer({
        guest_registration: [{
            hotel_id: "numeric | required",
            firstname: "string | required",
            lastname: "string | required",
            email_address: "string | required_without:phone_no",
            phone_no: "string | required_without:email_address",
            angel_status: "numeric (1 => send Email; 0) | required",
            language: "string | option | acepted value: 'en':'english', 'es':'spanish'"
        },{
            hotel_id: "numeric | required",
            firstname: "string | required",
            lastname: "string | required",
            email_address: "string | required_without:phone_no",
            phone_no: "string | required_without:email_address",
            angel_status: "numeric (1 => send Email; 0) | required",
            language: "string | option | acepted value: 'en' => 'english', 'es' => 'spanish'"
        },{
            hotel_id: "numeric | required",
            firstname: "string | required",
            lastname: "string | required",
            email_address: "string | required_without:phone_no",
            phone_no: "string | required_without:email_address",
            angel_status: "numeric (1 => send Email; 0) | required",
            language: "string | option | acepted value: 'en' => 'english', 'es' => 'spanish'"
        }],
        guest_checkin_details: [{
            room_no: "numeric | required_without: room",
            room: "string | required_without: room_no",
            check_in: "YYYY-mm-dd H:i:s | required",
            check_out: "YYYY-mm-dd H:i:s | required",
            reservation_number: "string | optional",
        },{
            room_no: "numeric | required_without: room",
            room: "string | required_without: room_no",
            check_in: "YYYY-mm-dd H:i:s | required",
            check_out: "YYYY-mm-dd H:i:s | required",
            reservation_number: "string | optional",
        },{
            room_no: "numeric | required_without: room",
            room: "string | required_without: room_no",
            check_in: "YYYY-mm-dd H:i:s | required",
            check_out: "YYYY-mm-dd H:i:s | required",
            reservation_number: "string | optional",
        }]
    },config);

    $('#new_guest_v1_response').jsonViewer({
        create      : "boolean",
        guest_id    : "string",
        message     : "string",
        description : "Array<object>"
    },config);

    $('#new_guest_multiple_response').jsonViewer({
        create  :"boolean",
        message : "string",
        success : "Array<object>",
        error   : "Array<object>"
    },config);

    $('#new_guest_v1_response').jsonViewer({
        create  :"boolean",
        message : "string",
        success : "Array<object>",
        error   : "Array<object>"
    },config);

    $('#guest_update_v1_data').jsonViewer({
        guest_registration: {
            firstname:      "string | optional",
            lastname:       "string | optional",
            email_address:  "string | optional",
            phone_no:       "string | optional",
            angel_status:   "numeric | optional | in: 0 => no send,1 => send",
            language:       "string | option | acepted value: 'en':'english', 'es':'spanish'"
        },
        guest_checkin_details: {
            sno:        "numeric | required",
            room_no:    "numeric | required_without: room | current room, not updatable",
            room:       "string  | required_without: room_no | current room, not updatable",
            check_in:   "YYYY-mm-dd H:i:s | required",
            check_out:  "YYYY-mm-dd H:i:s | required",
            reservation_number: "string | optional",
        }
    },config);

    $('#guest_update_one_data').jsonViewer({
        guest_registration: {
            firstname:      "string | optional",
            lastname:       "string | optional",
            email_address:  "string | optional",
            phone_no:       "string | optional",
            angel_status:   "numeric | optional | in: 0 => no send,1 => send",
            language:       "string | option | acepted value: 'en':'english', 'es':'spanish'"
        },
        guest_checkin_details: {
            sno:        "numeric | required",
            room_no:    "numeric | required_without: room | current room, not updatable",
            room:       "string  | required_without: room_no | current room, not updatable",
            check_in:   "YYYY-mm-dd H:i:s | required",
            check_out:  "YYYY-mm-dd H:i:s | required",
            reservation_number: "string | optional",
        }
    },config);

    $('#guest_update_multiple_data').jsonViewer({
        guest_registration: [
            {
                guest_id:       "numeric | required",
                firstname:      "string | optional",
                lastname:       "string | optional",
                email_address:  "string | optional",
                phone_no:       "string | optional"
            },
            {
                guest_id:       "numeric | required",
                firstname:      "string | optional",
                lastname:       "string | optional",
                email_address:  "string | optional",
                phone_no:       "string | optional"
            },
            {
                guest_id:       "numeric | required",
                firstname:      "string | optional",
                lastname:       "string | optional",
                email_address:  "string | optional",
                phone_no:       "string | optional"
            }
        ],
        guest_checkin_details: [
            {
                sno:        "numeric | required",
                room_no:    "numeric | required_without: room | current room, not updatable",
                room:       "string  | required_without: room_no | current room, not updatable",
                check_in:   "YYYY-mm-dd H:i:s | required",
                check_out:  "YYYY-mm-dd H:i:s | required",
                reservation_number: "string | optional",
            },
            {
                sno:        "numeric | required",
                room_no:    "numeric | required_without: room | current room, not updatable",
                room:       "string  | required_without: room_no | current room, not updatable",
                check_in:   "YYYY-mm-dd H:i:s | required",
                check_out:  "YYYY-mm-dd H:i:s | required",
                reservation_number: "string | optional",
            },
            {
                sno:        "numeric | required",
                room_no:    "numeric | required_without: room | current room, not updatable",
                room:       "string  | required_without: room_no | current room, not updatable",
                check_in:   "YYYY-mm-dd H:i:s | required",
                check_out:  "YYYY-mm-dd H:i:s | required",
                reservation_number: "string | optional",
            }
        ]
    },config);

    $('#guest_update_v1_response').jsonViewer({
        update: "boolean",
        message: "string",
        description: "Array<object>"
    },config);

    $('#guest_update_one_response').jsonViewer({
        update: "boolean",
        message: "string",
        description: "Array<object>"
    },config);

    $('#guest_update_multiple_response').jsonViewer({
        update: "boolean",
        message: "string",
        description: "Array<object>"
    },config);

    $('#json-guest_delete-data').jsonViewer({
        "hotel_id": "string",
        "guests": [
            { "guest_id" : "numeric|required" },
            { "guest_id" : "numeric|required" },
            { "guest_id" : "numeric|required" },
            { "guest_id" : "numeric|required" }
        ]
    },config);

    $('#json-guest_delete-response').jsonViewer({
        delete: "boolean",
        error: "Array"
    },config);

    $('#json-validate_guest_s_email-response').jsonViewer({
        exists: "boolean || NULL",
        message: "string"
    },config);

    $('#json-validate_guest_s_phone-response').jsonViewer({
        exists: "boolean || NULL",
        message: "string"
    },config);

    $('#json-close_guest_checkin-response').jsonViewer({
        close: "boolean",
        message: "string",
        description: "Array<object>"
    },config);

@endsection