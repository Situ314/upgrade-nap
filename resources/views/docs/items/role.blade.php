@extends('layouts.app')

@section('content')
    <h1 id="roles">ROLES</h1>
    <h2 id="roles_get_list">GET ROLES LIST</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">http://api-dev.mynuvola.net/api/v1/role</td>
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
                <td colspan="2"><per id="json-get_roles-response"></per></td>
            </tr>
        </tbody>
    </table>
@endsection

@section('script')
    $('#json-get_roles-response').jsonViewer([{
        role_id: "numeric",
        role_name: "string"
    }], config);
@endsection