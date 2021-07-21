@foreach ($data as $key => $value)
    <table cellspacing="0" cellpadding="10">
        <tr style="font-size: 20px;background: #444444;color: #fff;">
            <td colspan="4"><b>{{\App\Models\Hotel::find($key)->hotel_name}}</b></td>
        </tr>
        <tr style="background: #777777;color: #fff;">
            <td><b>sno</b></td>
            <td><b>Fullname</b></td>
            <td><b>Reservation Number</b></td>
            <td><b>Message</b></td>
        </tr>
        @foreach ($value as $k => $v)
            <tr>
                <td style="padding: 2px 3px;border-top: 1px solid #444444;{{$v['request_inquiry'] == false ? 'background-color:tomato;' : ($v['request_inquiry'] == true && $v['message'] != 'Synchronized record' ? 'background-color:#24d004' : '')}}">{{$v["sno"]}}</td>
                <td style="padding: 2px 3px;border-top: 1px solid #444444;{{$v['request_inquiry'] == false ? 'background-color:tomato;' : ($v['request_inquiry'] == true && $v['message'] != 'Synchronized record' ? 'background-color:#24d004' : '')}}">{{$v["fullname"]}}</td>
                <td style="padding: 2px 3px;border-top: 1px solid #444444;{{$v['request_inquiry'] == false ? 'background-color:tomato;' : ($v['request_inquiry'] == true && $v['message'] != 'Synchronized record' ? 'background-color:#24d004' : '')}}">{{$v["reservation_number"]}}</td>
                <td style="padding: 2px 3px;border-top: 1px solid #444444;{{$v['request_inquiry'] == false ? 'background-color:tomato;' : ($v['request_inquiry'] == true && $v['message'] != 'Synchronized record' ? 'background-color:#24d004' : '')}}">{{$v["message"]}}</td>
            </tr>
        @endforeach
    </table>
@endforeach