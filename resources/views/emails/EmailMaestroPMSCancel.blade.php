<!DOCTYPE html
    PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>Nuvola</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link href="https://fonts.googleapis.com/css?family=Roboto&display=swap" rel="stylesheet" />
</head>

<body style="margin: 0; padding: 0;" bgcolor="#f4f6f9">
    <table align="center" border="0" cellpadding="0" cellspacing="0" width="600">
        <tr>
            <td height="30"></td>
        </tr>
        <tr>
            <td bgcolor="#00729d" style="padding-top:15px;padding-left:15px;padding-right:15px;padding-bottom:15px;">
                <h1 style="color:#ffffff;">
                    Cancel Reservation report | Maestro PMS
                </h1>
            </td>
        </tr>
        <tr>
            <td bgcolor="#ffffff" style="padding-top:15px;padding-left:15px;padding-right:15px;padding-bottom:15px;">
                <!-- MAIN CONTENT - START -->
                <table border="0" cellpadding="0" cellspacing="0" width="100%">

                    <tr>
                        <td>
                            <table class="table-content" align="center" border="0" cellpadding="0" cellspacing="0" width="600">
                                <tr>
                                    <th>

                                    </th>
                                    <th>
                                        Reservation Id
                                    </th>
                                    <th>
                                        CheckIn
                                    </th>
                                    <th>
                                        CheckOut
                                    </th>
                                    <th>
                                        Reservation Number
                                    </th>
                                    <th>
                                        New Reservation Status
                                    </th>
                                </tr>
                                @foreach ($hotels as $key => $value)
                                <tr style="font-family: 'Roboto', sans-serif; color: #363636;">
                                    <td colspan="6" style="padding-left:15px; padding-right: 15px;padding-bottom: 0; padding-top: 15px;" colspan="6">{{$key}}</td>
                                </tr>
                                    @foreach ($value as $item)
                                    <tr style="font-family: 'Roboto', sans-serif;  color: #363636; height: 35px; text-align: center;">
                                        <td style="border-bottom: 1px solid #3e3e3e"></td>
                                        <td style="border-bottom: 1px solid #3e3e3e">{{$item->sno}}</td>
                                        <td style="border-bottom: 1px solid #3e3e3e">{{$item->check_in}}</td>
                                        <td style="border-bottom: 1px solid #3e3e3e">{{$item->check_out}}</td>
                                        <td style="border-bottom: 1px solid #3e3e3e">{{$item->reservation_number}}</td>
                                        @if ($item->reservation_status == 1)
                                            <td style="border-bottom: 1px solid #3e3e3e">CheckIn</td>
                                        @elseif ($item->reservation_status == 2)
                                            <td style="border-bottom: 1px solid #3e3e3e">Cancelled</td>                                                
                                        @elseif($item->reservation_status == 3)
                                            <td style="border-bottom: 1px solid #3e3e3e">CheckOut</td>
                                        @else
                                            <td style="border-bottom: 1px solid #3e3e3e">Reserved</td>
                                        @endif
                                    </tr>
                                    @endforeach
                                @endforeach
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
    </table>
</body>

</html>