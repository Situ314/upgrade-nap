@extends('doc.layouts.app')

@section('content')
    <h1 id="package">Packages</h1>
    <div class="separator max-700 mb-4"></div>
    <h2 id="package_list">Get Packages List</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">{{$url}}/package</td>
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
                <td colspan="2"><per id="json-package-response"></per></td>
            </tr>
            <tr class="note">
                <td class="bold"><i class="material-icons"> info </i></td>
                <td colspan="2"><p>The information provided by this endpoint is paged, the parameter "paginate" is optional, its default value is 100, this means that it divides the total content among 100.</p></td>
            </tr>
        </tbody>
    </table>
    <h2 id="package_new">New Package</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">{{$url}}/package</td>
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
                <td colspan="2"><per id="json-package_new_1-data"></per></td>
            </tr>
            <tr>
                <td class="bold">Data_option_2</td>
                <td colspan="2"><per id="json-package_new_2-data"></per></td>
            </tr>
            <tr>
                <td class="bold">Response</td>
                <td colspan="2"><per id="json-package_new-response"></per></td>
            </tr>
        </tbody>
    </table>
    <h2 id="package_update">Update Package</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">{{$url}}/package/<code>{ pkg_no }</code></td>
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
                <td colspan="2"><per id="json-package_update-data"></per></td>
            </tr>
            <tr>
                <td class="bold">Response</td>
                <td colspan="2"><per id="json-package_update-response"></per></td>
            </tr>
        </tbody>
    </table>
    <h2 id="package_delete">Delete Package</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">{{$url}}/package/<code>{ pkg_no }</code></td>
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
                <td colspan="2"><per id="json-package_delete-response"></per></td>
            </tr>
        </tbody>
    </table>
@endsection

@section('script')
    $('#json-package-response').jsonViewer({
        current_page: "numeric",
        data: [{
            'pkg_no':'numeric',
            'guest_id': 'numeric',
            'phone_no' : 'string',
            'item_name': 'string',
            'room_id': 'numeric' ,
            'comment': 'string'
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
    $('#json-package_new_1-data').jsonViewer({
        package: {
            hotel_id: "string | required",
            item_name: "string | required",
            guest_id: "numeric | required",
            comment: "string | optional",
            courier: "string | optional"
        }
    }, config);
    $('#json-package_new_2-data').jsonViewer({
        package: {
            hotel_id: "string | required",
            item_name: "string | required",
            name: "string | required",
            comment: "string | optional",
            room_id: "numeric | optional",
            phone_no: "string | optional",
            courier: "string | optional"
        }
    }, config);
    $('#json-package_new-response').jsonViewer({
        create: "boolean",
        pkg_no: "numeric",
        message: "string",
        description: "Array<object>"
    },config);
    $('#json-package_update-data').jsonViewer({
        item_name: 'string | optional',
        guest_id: 'numeric | optional',
        status: '0 -> (inactive) or 1 -> (active) | optional',
    },config);
    $('#json-package_update-response').jsonViewer({
        update: "boolean",
        message: "string",
        description: "Array<object>"
    },config);
    $('#json-package_delete-response').jsonViewer({
        delete: "boolean",
        message: "string"
    },config);
@endsection