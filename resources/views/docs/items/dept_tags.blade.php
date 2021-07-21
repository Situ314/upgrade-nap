@extends('layouts.app')

@section('content')
    <h1 id="dept_tags">DEPARTMENT AND TAGS</h1>
    <h2 id="dept_tags_get_list">GET DEPARTMENT AND TAGS LIST</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">http://api-dev.mynuvola.net/api/v1/dept-tag</td>
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
                <td colspan="2"><per id="json-get_dept_tags-response"></per></td>
            </tr>
            <tr class="note">
                <td class="bold"><i class="fa fa-info-circle" aria-hidden="true"></i></td>
                <td colspan="2"><p>The information provided by this endpoint is paged, the parameter "paginate" is optional, its default value is 100, this means that it divides the total content among 100.</p></td>
            </tr>
        </tbody>
    </table>
@endsection

@section('script')
    $('#json-get_dept_tags-response').jsonViewer({
        current_page: "numeric",
        data: [{
            dept_tag_id: "numeric",
            dept_id: "numeric",
            departament: {
                dept_id: "numeric",
                dept_name: "string",
                short_name: "string",
                dep_default: "numeric",
                is_active: "numeric",
                tag_type: "numeric",
                color: "string",
                predetermined_target_2: "numeric || NULL",
                predetermined_target_2: "numeric || NULL",
            },
            departament: {
                tag_id: "numeric",
                tag_name: "string",
                tag_default: "numeric",
                tag_image: "string || NULL",
                tag_price: "string || NULL",
                tag_status: "string",
                tag_type: "numeric",
                is_active: "numeric"
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
@endsection