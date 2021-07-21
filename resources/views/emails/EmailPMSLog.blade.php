<!DOCTYPE html
    PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>Nuvola</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link href="https://fonts.googleapis.com/css?family=Roboto&display=swap" rel="stylesheet">
    <style>h1,h2,p,ul li{font-family: 'Roboto', sans-serif;}</style>
</head>

<body style="margin: 0; padding: 0;" bgcolor="#f4f6f9">
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td>
                <!-- BASE -->
                <table align="center" border="0" cellpadding="0" cellspacing="0" width="600"
                    style="border-collapse: collapse;">
                    <tr>
                        <td>
                            <!-- MAIN STRUCTURE -->
                            <table align="center" border="0" cellpadding="0" cellspacing="0" width="600">
                                <tr><td height="30"></td></tr>
                                <tr><td height="4" bgcolor="red"></td></tr>
                                <tr>
                                    <td bgcolor="#1287b4"
                                        style="padding-top:15px;padding-left:15px;padding-right:15px;padding-bottom:15px;">
                                        <h1 style="color:#ffffff;">Reporte de integraciones</h1>
                                        <h2 style="color:#ffffff;">Fecha de ultimo mensaje por parte del PMS</h2>
                                    </td>
                                </tr>
                                <tr>
                                    <td bgcolor="#ffffff" style="padding-top:15px;padding-left:15px;padding-right:15px;padding-bottom:15px;">
                                        <!-- MAIN CONTENT - START -->
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                    
                                            <tr>
                                                <td>
                                                    <table  class="table-content"  align="center" border="0" cellpadding="0" cellspacing="0"
                                                        width="600">
                                                        <tr>
                                                            <th colspan="3">
                                                                hotel_id
                                                            </th>
                                                            <th colspan="3">
                                                                hotel_name
                                                            </th>
                                                            <th colspan="3">
                                                                fecha de ultimo registro
                                                            </th>
                                                        </tr>
                                                        @foreach ($data_hotel as $key => $item)
                                                            <tr style="font-family: 'Roboto', sans-serif; color: #363636;">
                                                                <td style="text-align: center; padding-left:15px; padding-right: 15px;padding-bottom: 0; padding-top: 15px;" colspan="3">{{$item["hotel_id"]}}</td>
                                                                <td style="text-align: center; padding-left:15px; padding-right: 15px;padding-bottom: 0; padding-top: 15px;" colspan="3">{{$item["name"]}}</td>
                                                                <td style="text-align: center; padding-left:15px; padding-right: 15px;padding-bottom: 0; padding-top: 15px;" colspan="3">{{$item["last_data"]}}</td>
                                                            </tr>
                                                        @endforeach
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                        <!-- MAIN CONTENT - end -->
                                    </td>
                                </tr>
                                <tr><td height="30"></td></tr>
                            </table>
                            <!-- END MAIN STRUCTURE -->
                        </td>
                    </tr>
                </table>
                <!-- END BASE -->
            </td>
        </tr>
    </table>
</body>

</html>