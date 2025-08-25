<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Dummy PDF Report</title>
    <style>
        @page {
            size: A4;
            margin-left: 64px;
            margin-top: 23px;
            margin-right: 20px;
            margin-bottom: 0px;
            /* Set equal margins on all sides */
        }

        body,
        p {
            margin: 0;
            padding: 0;
        }

        .content {
            margin-left: 70px;
        }
    </style>
</head>

<body>
    <div class="header">
        <img src="img/innoquest.png" style="width:100%;" />
    </div>

    <div class="content">
        <table style="width:100%;">
            <tr>
                <td style="width:63%;">
                    <p style="font-size:11px;font-weight:normal;margin-bottom:20px;margin-top:40px;">*copy*</p>
                    <p style="font-size:11px;font-weight:bold;">
                        <span style="padding-right:30px;">Patient Details</span>
                        <span>UR:</span>
                    </p>
                    <p style="font-size:11px;font-weight:bold;margin-top:5px;">SANJEV TESTING</p>
                    <p style="font-size:11px;font-weight:bold;margin-top:5px;">
                        <span style="padding-right:3px;">Ref</span>
                        <span>:</span>
                    </p>
                    <table style="font-size:11px; font-weight:bold; margin-top:25px; border-collapse:collapse;">
                        <tr>
                            <td style="padding-right:30px;">
                                <span style="display:inline-block;width:40px;">DOB</span>
                                <span style="display:inline-block;width:5px;">:</span>
                                <span>25/07/04</span>
                            </td>
                            <td>
                                <span style="display:inline-block;width:40px;">Sex</span>
                                <span style="display:inline-block;width:5px;">:</span>
                                <span>Female</span>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding-top:5px; padding-right:30px;">
                                <span style="display:inline-block;width:40px;">ID NO.</span>
                                <span style="display:inline-block;width:5px;">:</span>
                                <span>971202055188</span>
                            </td>
                            <td style="padding-top:5px;">
                                <span style="display:inline-block;width:40px;">Age</span>
                                <span style="display:inline-block;width:5px;">:</span>
                                <span>20 Years</span>
                            </td>
                        </tr>
                    </table>

                </td>
                <td style="width:37%;">
                    <p style="font-size:11px;font-weight:normal;margin-top:-60px;">Courier Run:</p>
                    <p style="font-size:11px;font-weight:bold;text-decoration:underline;margin-top:-60px;">Doctor
                        Details
                    </p>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">

    </div>
</body>

</html>
