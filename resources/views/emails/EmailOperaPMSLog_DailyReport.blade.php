<!DOCTYPE html
    PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>Nuvola</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link href="https://fonts.googleapis.com/css?family=Roboto&display=swap" rel="stylesheet" />
    <style>
        h1,
        h2,
        p,
        ul li {
            font-family: "Roboto", sans-serif;
        }

        .table-content th {
            background: #00729d;
            font-family: "Roboto", sans-serif;
            color: #ffffff;
            border: none;
            padding: 15px 15px;
        }
    </style>
</head>

<body style="margin: 0; padding: 0;" bgcolor="#f4f6f9">
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td>
                <!-- BASE -align="center" border="0" cellpadding="0" cellspacing="0" width="600"
                    style="border-collapse: collapse;">
                    <tr>
                        <td>
                            <!-- MAIN STRUCTURE -->
                            <table align="center" border="0" cellpadding="0" cellspacing="0" width="600">
                                <tr><td height="30"></td></tr>                                
                                <tr>
                                    <td bgcolor="#00729d"->
                <table 
                                        style="padding-top:15px;padding-left:15px;padding-right:15px;padding-bottom:15px;">
                                        <h1 style="color:#ffffff;">
                                            Integration report | Opera PMS
                                        </h1>
                                    </td>
                                </tr>
                                <tr>
                                    <td bgcolor="#ffffff"
                                        style="padding-top:15px;padding-left:15px;padding-right:15px;padding-bottom:15px;">
                                        <!-- MAIN CONTENT - START -->
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                            <tr>
                                                <td>
                                                    <p>
                                                        The following hotels have received information
                                                        from PMS today, {{$date}}
                                                    </p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <table  class="table-content"  align="center" border="0" cellpadding="0" cellspacing="0"
                                                        width="600">
                                                        <tr>
                                                            <th>

                                                            </th>
                                                            <th>
                                                                Reservation
                                                            </th>
                                                            <th>
                                                                Profile
                                                            </th>
                                                            <th>
                                                                Housekeeping
                                                            </th>
                                                            <th>
                                                                Total
                                                            </th>
                                                        </tr>
                                                        @foreach ($QuantityLogs as $key => $value)
                                                            <tr   style="font-family: 'Roboto', sans-serif; 
                                                                color: #363636;">
                                                                <td style="padding-left:15px; padding-right: 15px;padding-bottom: 0; padding-top: 15px;" colspan="6">{{$key}}</td>
                                                            </tr>
                                                            <tr  style="font-family: 'Roboto', sans-serif; 
                                                                color: #363636;
                                                                height: 35px;
                                                                text-align: center;">
                                                                <td style="border-bottom: 1px solid #3e3e3e"></td>
                                                                @foreach ($value as $key2 => $item)
                                                                    <td style="border-bottom: 1px solid #3e3e3e">{{$item}}</td>
                                                                @endforeach
                                                            </tr>
                                                        @endforeach
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- MAIN CONTENT - end -->
                                    </td>
                                </tr>
                                <tr>
                                    <td height="30"></td>
                                </tr>
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