@extends('doc.layouts.app')

@section('content')
    <h1 id="dept">Departments & Tags</h1>
    <div class="separator max-700 mb-4"></div>
    <h2 id="dept_list">Get Department & Tgas List</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">{{$url}}/department</td>
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
                <td colspan="2"><code>?hotel_id={ numeric | required }&<br>type={ ['ANGEL','DESKTOP','ALL'] | optional }&<br>get_tags={ ['TRUE','FALSE'] | optional }&<br>paginate={ numeric | optional }</code></td>
            </tr>
            <tr>
                <td class="bold">Response</td>
                <td colspan="2"><per id="json-get_dept-response"></per></td>
            </tr>
            <tr class="note">
                <td class="bold"><i class="material-icons"> info </i></td>
                <td colspan="2"><p>The information provided by this endpoint is paged, the parameter "paginate" is optional, its default value is 100, this means that it divides the total content among 100.</p></td>
            </tr>
        </tbody>
    </table>
    <h2 id="dept_by_id">Get Department by ID</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">{{$url}}/department/<code>{ dept_id }</code></td>
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
                <td colspan="2"><code>?hotel_id={ numeric | required }</code></td>
            </tr>
            <tr>
                <td class="bold">Response</td>
                <td colspan="2"><per id="json-get_dept_by_id-response"></per></td>
            </tr>
        </tbody>
    </table>
@endsection

@section('script')

    $('#json-get_dept-response').jsonViewer({
        current_page: "numeric",
        data: [{
            dept_id: "numeric",
            dept_name: "string",
            short_name: "string",
            "tags <code style='color:#e83e8c'><small>(if get_tags=TRUE)</small></code>": [
                {
                    tag_id: "numeric",
                    tag_name: "string"
                },
                {
                    tag_id: "numeric",
                    tag_name: "string"
                },
                {
                    tag_id: "numeric",
                    tag_name: "string"
                }
            ]
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

    $('#json-get_dept_by_id-response').jsonViewer({
        
        dept_id: "numeric",
        dept_name: "string",
        short_name: "string",
        "tags": [
            {
                tag_id: "numeric",
                tag_name: "string"
            },
            {
                tag_id: "numeric",
                tag_name: "string"
            },
            {
                tag_id: "numeric",
                tag_name: "string"
            }
        ]
        
    },config);

@endsection