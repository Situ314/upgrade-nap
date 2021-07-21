@extends('doc.layouts.app')

@section('content')
    <h1 id="staff">STAFF</h1>
    <div class="separator max-700 mb-4"></div>
    <h2 id="staff_list">GET STAFF LIST</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">{{$url}}/staff</td>
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
                <td colspan="2"><per id="json-staff_get_list-response"></per></td>
            </tr>
            <tr class="note">
                <td class="bold"><i class="material-icons"> info </i></td>
                <td colspan="2"><p>The information provided by this endpoint is paged, the parameter "paginate" is optional, its default value is 100, this means that it divides the total content among 100.</p></td>
            </tr>
        </tbody>
    </table>
    <h2 id="staff_new">NEW STAFF</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">{{$url}}/staff</td>
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
                <td colspan="2"><per id="json-new_staff-data"></per></td>
            </tr>
            <tr>
                <td class="bold">Response</td>
                <td colspan="2"><per id="json-new_staff-response"></per></td>
            </tr>
        </tbody>
    </table>
    <h2 id="staff_info">GET STAFF INFORMATION</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">http://api-dev.mynuvola.net/api/user?hotel_id=<code>{ hotel_id }</code></td>
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
                <td colspan="2"><per id="json-get_user-response"></per></td>
            </tr>
        </tbody>
    </table>
@endsection

@section('script')
$('#json-staff_get_list-response').jsonViewer({
        current_page: "numeric",
        data: [{
            staff_id: "numeric",
            username: "string",
            firstname: "string",
            lastname: "string",
            email: "string"
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
    
    $('#json-new_staff-data').jsonViewer({
        staff: {
            username: "string | required",
            firstname: "string | required",
            lastname: "string | required",
            password: "string | required",
            access_code: "numeric | required | min:4,max:6",
            phone_number: "string | required",
            email: "string"
        },
        staff_hotels: {
            hotel_id: "numeric | required",
            role_id: "numeric | required"
        }
    }, config);
    $('#json-new_staff-response').jsonViewer({
        create: "boolean",
        staff_id: "numeric",
        message: "string",
        description: "Array<object>"
    },config);
    $('#json-get_user-response').jsonViewer({
        staff_id: "numeric",
        firstname: "string",
        lastname: "string",
        username: "string",
        email: "string",
        description: "string",
        staff_hotels: {
            sno: "numeric",
            role: {
                role_id: "numeric",
                role_name: "string"
            }
        }
    }, config);
@endsection