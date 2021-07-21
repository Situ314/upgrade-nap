@extends('layouts.app')

@section('content')
    <h2 id="guest_get_list">GET GUEST LIST</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">http://api-dev.mynuvola.net/api/v1/guest</td>
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
                <td colspan="2"><per id="json-guest_get_list-response"></per></td>
            </tr>
            <tr class="note">
                <td class="bold"><i class="fa fa-info-circle" aria-hidden="true"></i></td>
                <td colspan="2"><p>The information provided by this endpoint is paged, the parameter "paginate" is optional, its default value is 100, this means that it divides the total content among 100.</p></td>
            </tr>
        </tbody>
    </table>
    <h2 id="guest_new">NEW GUEST</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">http://api-dev.mynuvola.net/api/v1/guest</td>
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
                <td class="bold">Data_option_1</td>
                <td colspan="2"><per id="json-new_guest_1-data"></per></td>
            </tr>
            <tr class="note">
                <td class="bold"><i class="fa fa-info-circle" aria-hidden="true"></i></td>
                <td colspan="2">
                    <ul>
                        <li><b>hotel_id:</b> this parameter refers to the hotel identifier where the operation will be performed <a href="#get_hotels">GET HOTELS</a>.</li>
                        <li><b>firstname</b> and <b>lastname:</b> the combination of these fields are unique in the hotel system.</li>
                        <li><b>email_address</b> or <b>phone_number:</b> To be able to register one of this fields is necessary </li>
                        <li>
                            <b>email_address</b> and <b>phone_number:</b> this field is unique in the hotel system.
                            <ul>
                                <li><a href="#validate_guest_s_email">Validate email</a> </li>
                                <li><a href="#validate_guest_s_phone">Validate phone</a></li>
                            </ul>
                        </li>
                        <li><b>room_no:</b> this field refers to the room_id parameter obtained from the end point <a href="#rooms_get_list">GET ROOMS LIST</a>.</li>
                    </ul>
                </td>
            </tr>
            <tr>
                <td class="bold">Data_option_2</td>
                <td colspan="2"><per id="json-new_guest_2-data"></per></td>
            </tr>
            <tr>
                <td class="bold">Response</td>
                <td colspan="2"><per id="json-new_guest-response"></per></td>
            </tr>
        </tbody>
    </table>
    <h2 id="guest_update">UPDATE GUEST</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">http://api-dev.mynuvola.net/api/v1/guest/<code>{ guest_id }</code></td>
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
                <td class="bold">Data_option_1</td>
                <td colspan="2"><per id="json-guest_update_1-data"></per></td>
            </tr>
            <tr>
                <td class="bold">Data_option_2</td>
                <td colspan="2"><per id="json-guest_update_2-data"></per></td>
            </tr>
            <tr>
                <td class="bold">Response</td>
                <td colspan="2"><per id="json-guest_update-response"></per></td>
            </tr>
        </tbody>
    </table>
    <h2 id="guest_delete">DELETE GUEST</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">http://api-dev.mynuvola.net/api/v1/guest/<code>{ guest_id }</code></td>
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
                <td class="bold">Response</td>
                <td colspan="2"><per id="json-guest_delete-response"></per></td>
            </tr>
        </tbody>
    </table>
    <h3 id="validate_guest_s_email">Validat email</h3>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">http://api-dev.mynuvola.net/api/v1/guest/validate/email/<code>{ hotel_id }</code>/<code>{ email }</code></td>
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
                <td class="bold"><i class="fa fa-info-circle" aria-hidden="true"></i></td>
                <td colspan="2"><p>The variable "exists" returns null, this means that the information supplied (hotel_id) is not correct.</p></td>
            </tr>
        </tbody>
    </table>
    <h3 id="validate_guest_s_phone">Validate phone</h3>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">http://api-dev.mynuvola.net/api/v1/guest/validate/phone/<code>{ hotel_id }</code>/<code>{ phone }</code></td>
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
                <td class="bold"><i class="fa fa-info-circle" aria-hidden="true"></i></td>
                <td colspan="2"><p>The variable "exists" returns null, this means that the information supplied (hotel_id) is not correct.</p></td>
            </tr>
        </tbody>
    </table>
    <h2 id="close_checkin">CLOSE GUEST CHECK IN</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">http://api-dev.mynuvola.net/api/v1/close-guest-checkin</td>
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
                <td colspan="2"><code>?hotel_id={ numeric | required }&guest_id={ numeric | required }</code></td>
            </tr>
            <tr>
                <td class="bold">Response</td>
                <td colspan="2"><per id="json-close_guest_checkin-response"></per></td>
            </tr>
        </tbody>
    </table>
@endsection

@section('script')
    $('#json-guest_get_list-response').jsonViewer({
        current_page: "numeric",
        data: [{
            guest_id: "numeric",
            firstname: "string",
            lastname: "string",
            email_address: "string",
            phone_no: "string"
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
    $('#json-new_guest_1-data').jsonViewer({
        guest_registration: {
            hotel_id: "numeric | required",
            firstname: "string | required",
            lastname: "string | required",
            email_address: "string | optional",
            phone_no: "string | optional"
        },
        guest_checkin_details: {
            room_no: "numeric | required",
            check_in: "Y-m-d H:i:s (example: 1993-12-09 16:05:05) | required",
            check_out: "Y-m-d H:i:s (example: 1993-12-09 16:05:05) | required"
        }
    },config);
    $('#json-new_guest_2-data').jsonViewer({
        guest_registration: {
            hotel_id: "numeric | required",
            firstname: "string | required",
            lastname: "string | required",
            email_address: "string | optional",
            phone_no: "string | optional"
        },
        guest_checkin_details: {
            room: "string | required",
            check_in: "Y-m-d H:i:s (example: 1993-12-09 16:05:05) | required",
            check_out: "Y-m-d H:i:s (example: 1993-12-09 16:05:05) | required"
        }
    },config);
    $('#json-new_guest-response').jsonViewer({
        create: "boolean",
        guest_id: "number",
        message: "string",
        description: "Array<object>"
    },config);
    $('#json-guest_update_1-data').jsonViewer({
        guest_registration: {
            firstname: "string | optional",
            lastname: "string | optional",
            email_address: "string | optional",
            phone_no: "string | optional"
        },
        guest_checkin_details: {
            room_no: "numeric | optional",
            check_in: "Y-m-d H:i:s (example: 1993-12-09 16:05:05) | optional",
            check_out: "Y-m-d H:i:s (example: 1993-12-09 16:05:05) | optional"
        }
    },config);
    $('#json-guest_update_2-data').jsonViewer({
        guest_registration: {
            firstname: "string | optional",
            lastname: "string | optional",
            email_address: "string | optional",
            phone_no: "string | optional"
        },
        guest_checkin_details: {
            room: "string | optional",
            check_in: "Y-m-d H:i:s (example: 1993-12-09 16:05:05) | optional",
            check_out: "Y-m-d H:i:s (example: 1993-12-09 16:05:05) | optional"
        }
    },config);
    $('#json-guest_update-response').jsonViewer({
        update: "boolean",
        message: "string",
        description: "Array<object>"
    },config);
    $('#json-guest_delete-response').jsonViewer({
        delete: "boolean",
        message: "string"
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