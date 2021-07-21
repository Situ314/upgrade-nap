@extends('doc.layouts.app')

@section('content')
    <h1 id="maintenance">Maintenance</h1>
    <div class="separator max-700 mb-4"></div>
    <div class="max-700">
        <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit. Assumenda suscipit, deserunt nostrum odio reprehenderit repudiandae ab! Sed, modi quo? Dignissimos mollitia recusandae eius ipsum, hic dolorum pariatur vel? Totam, asperiores.</p>
        <p>t amet, consectetur adipisicing elit. Assumenda suscipit, deserunt nostrum odio reprehenderit repudiandae ab t amet, consectetur adipisicing elit. Assumenda suscipit, deserunt nostrum odio reprehenderit repudiandae ab</p>
    </div>
    <h2 id="maintenance_list">Get Maintenance List</h2>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td class="bold">URL</td>
                <td colspan="2">{{$url}}/maintenance</td>
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
                        <b>hotel_id</b>={ numeric | required }<br>
                        <b>paginate</b>={ numeric | optional }<br>     
                        <b>room_id</b>={ numeric | optional } OR <b>location</b>={ string | optional }<br>                        
                        <b>start_date</b>={ dd-mm-yyyy | optional | required_without: end_date }<br>
                        <b>end_date</b>={ dd-mm-yyyy | optional | required_without: start_date }
                    </code>
                </td>
            </tr>
            <tr>
                <td class="bold">Response</td>
                <td colspan="2"><per id="json-get_maintenance-response"></per></td>
            </tr>
            <tr class="note">
                <td class="bold"><i class="material-icons"> info </i></td>
                <td colspan="2"><p>The information provided by this endpoint is paged, the parameter "paginate" is optional, its default value is 100, this means that it divides the total content among 100.</p></td>
            </tr>
        </tbody>
    </table>
@endsection

@section('script')
    $('#json-get_maintenance-response').jsonViewer({
        current_page: "numeric",
        data: [{
            id: "numeric",
            type: "string",
            room_id: "numeric",
            start_date: "datetime",
            end_date: "datetime",
            priority: "string: [ low, medium, high, no priority ]",
            assets: [
                {
                    maintenance_record_id: "numeric",
                    item_id: "numeric",
                    name: "string"
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
@endsection