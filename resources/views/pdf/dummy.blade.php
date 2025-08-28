<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Dummy PDF Report</title>
    <style>
        @page {
            size: A4;
            margin-left: 66px;
            margin-top: 30px;
            margin-right: 38px;
            margin-bottom: 0px;
        }

        header {
            position: fixed;
            text-align: center;
        }

        footer {
            position: fixed;
            bottom: 5px;
            left: 0;
            right: 0;
            text-align: center;
        }

        body,
        p {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            font-stretch: expanded;
        }

        .content {
            margin-top: 92px;
            margin-left: 5px;
        }

        sup {
            font-size: 8px;
            vertical-align: super;
        }

        .pagenum:before {
            content: counter(page);
        }

        .pagecount:before {
            content: counter(pages);
        }

        @page {
            @bottom-right {
                content: "Page " counter(page) " of " counter(pages);
            }
        }
    </style>
</head>

<body>
    <header>
        <img src="img/innoquest.png" style="width:100%;" />
    </header>

    <div class="content">
        <table style="width:100%; border-collapse:collapse; font-size:11px; padding:0;text-align:left;">
            <tr>
                <!-- Patient Details -->
                <td style="vertical-align:top; width:60%;">
                    <table style="width:100%; border-collapse:collapse; font-size:11px; padding:0;">
                        <tr>
                            <td colspan="6" style="font-style:light;font-weight:bold; padding:0;">Patient Details</td>
                        </tr>
                        <tr>
                            <td style="width:63px;padding:0;">Name</td>
                            <td style="width:5px;padding:0;">:</td>
                            <td style="width:120px;padding:0;">{{ $result['patient_info']['name'] }}</td>
                            <td style="width:50px;padding:0;"></td>
                            <td style="width:5px;padding:0;"></td>
                            <td style="padding:0;"></td>
                        </tr>
                        <tr>
                            <td style="padding:0;">UR</td>
                            <td style="padding:0;">:</td>
                            <td style="padding:0;"></td>
                            <td style="padding:0;"></td>
                        </tr>
                        <tr>
                            <td style="padding:0;">Ref</td>
                            <td style="padding:0;">:</td>
                            <td style="padding:0;">{{ $result['lab_info']['refid'] }}</td>
                            <td style="padding:0;"></td>
                        </tr>
                        <tr>
                            <td style="padding:10px 0 0 0;">DOB</td>
                            <td style="padding:10px 0 0 0;">:</td>
                            <td style="padding:10px 0 0 0;">{{ $result['patient_info']['dob'] }}</td>
                            <td style="padding:10px 0 0 0;">Sex</td>
                            <td style="padding:10px 0 0 0;">:</td>
                            <td style="padding:10px 0 0 0;">{{ $result['patient_info']['gender'] }}</td>
                        </tr>
                        <tr>
                            <td style="padding:0;">IC NO.</td>
                            <td style="padding:0;">:</td>
                            <td style="padding:0;">{{ $result['patient_info']['icno'] }}</td>
                            <td style="padding:0;">Age</td>
                            <td style="padding:0;">:</td>
                            <td style="padding:0;">{{ $result['patient_info']['age'] }}</td>
                        </tr>
                        <tr>
                            <td style="padding:0;">Collected</td>
                            <td style="padding:0;">:</td>
                            <td style="padding:0;">{{ $result['test_dates']['collected_date'] }}
                                {{ $result['test_dates']['collected_time'] }}</td>
                            <td style="padding:0;">Ward</td>
                            <td style="padding:0;">:</td>
                            <td style="padding:0;"></td>
                        </tr>
                        <tr>
                            <td style="padding:0;">Referred</td>
                            <td style="padding:0;">:</td>
                            <td style="padding:0;">{{ $result['test_dates']['reported_date'] }}</td>
                            <td style="padding:0;">Yr Ref.</td>
                            <td style="padding:0;">:</td>
                            <td style="padding:0;"></td>
                        </tr>
                    </table>
                </td>

                <!-- Doctor Details -->
                <td style="vertical-align:top; width:45%; padding-left:10px;">
                    <table style="width:70%; border-collapse:collapse; font-size:11px; padding:0;">
                        <tr>
                            <td colspan="3" style="font-style:light;font-weight:bold; padding:0;">Doctor Details</td>
                        </tr>
                        <tr>
                            <td colspan="3" style="padding:10px 0px 0px 0px;text-transform:uppercase;">
                                {{ $result['doctor_info']['name'] }}<br>
                                {{ $result['doctor_info']['outlet_name'] }}
                            </td>
                        </tr>
                        <tr>
                            <td colspan="3"
                                style="padding:0; word-wrap:break-word; max-width:80px;text-transform:uppercase;">
                                {{ $result['doctor_info']['outlet_address'] }}</td>
                        </tr>
                        <tr>
                            <td colspan="3" style="padding:0px 0px 3px 0px;"></td>
                        </tr>
                        <tr>
                            <td style="width:80px; padding:0px 0px 3px 0px;">Lab No.</td>
                            <td style="width:5px; padding:0px 0px 3px 0px;">:</td>
                            <td style="padding:0px 0px 3px 0px;">{{ $result['lab_info']['labno'] }}</td>
                        </tr>
                        <tr>
                            <td style="padding:0px 0px 3px 0px;">Courier Run</td>
                            <td style="padding:0px 0px 3px 0px;">:</td>
                            <td style="padding:0px 0px 3px 0px;"></td>
                        </tr>
                        <tr>
                            <td style="padding:0px 0px 3px 0px;">Report Printed</td>
                            <td style="padding:0px 0px 3px 0px;">:</td>
                            <td style="padding:0px 0px 3px 0px;"></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        <table style="width: 100%; border-collapse: collapse; font-size:11.5px; margin-top:15px; text-align:left;">
            <thead>
                <tr style="border-top:1px solid #000; border-bottom:1px solid #000;">
                    <th style="padding:0px 0px 0px 15px; text-align:left; text-transform:uppercase;width:386px;">
                        Analytes</th>
                    <th style="padding:0px 0px 3px 0px; text-align:left; text-transform:uppercase;width:60px;">Results
                    </th>
                    <th style="padding:0px 0px 3px 0px; text-align:left; text-transform:uppercase;">Units</th>
                    <th style="padding:0px 0px 3px 0px; text-align:left; text-transform:uppercase;">Ref. Ranges</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="4"
                        style="padding: 5px 0px;font-style:light;font-weight:bold;text-decoration:underline;">
                        {{ $result['profile_name'] }}</td> <!-- Profile Name -->
                </tr>
                @foreach ($result['resultItems'] as $panel)
                    <tr>
                        <td
                            style="padding: 5px 0px 0px 0px;text-transform:uppercase;font-style:light;font-weight:bold;">
                            {{ $panel['category_name'] ?? $panel['name'] }}</td>
                        <!-- Panel Category Name or Panel Name -->
                    </tr>
                    @foreach ($panel['items'] as $item)
                        @if ($item['base_name'] === 'Blood Film')
                            <tr>
                                <td colspan="2" style="padding:0px 0px 3px 0px;">
                                    <table
                                        style="border-collapse:collapse; width:100%;font-family:'Courier New', Courier, monospace;line-spacing:1.5;font-size:11.5px;">
                                        <tr>
                                            <td style="width:90%; padding:5px 5px 5px 10px;">FILM:
                                                {{ $item['value']['result_value'] ?? '' }}</td>
                                            <!-- Display as FILM -->
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        @else
                            <tr>
                                <td
                                    style="padding:0px 0px 3px 0px; @if (in_array($item['base_name'], ['White Cell Count', 'Platelets'])) padding:10px 0px; @endif">
                                    <table style="border-collapse:collapse; width:100%;">
                                        <tr>
                                            <td style="width:3%; padding:0;font-style:light;font-weight:bold;">
                                                {{ $item['value']['flag'] != 'N' ? '*' : '' }}</td> <!-- Flag -->
                                            <td
                                                style="width:50%; padding:0; @if (in_array($item['base_name'], ['Haemoglobin', 'White Cell Count', 'Platelets'])) font-style:light;font-weight:bold; @endif">
                                                {{ $item['base_name'] ?? '' }}</td> <!-- Base name -->
                                            <td style="width:40%; padding:0;"></td>
                                            <!-- Chinese character (leave blank) -->
                                            <td style="width:15%; padding:0;">
                                                {{ isset($item['percentage']) ? $item['percentage']['result_value'] . '%' : '' }}
                                            </td> <!-- Percentage -->
                                        </tr>
                                    </table>
                                </td>
                                <td
                                    style="padding:0px 0px 3px 0px; @if ($item['value']['flag'] != 'N') font-style:light;font-weight:bold; text-decoration:underline; @endif">
                                    {{ $item['value']['result_value'] ?? '' }}</td> <!-- Result value -->
                                <td style="padding:0px 0px 3px 0px;">{!! $item['value']['unit'] ?? '' !!}</td> <!-- Unit -->
                                <td style="padding:0px 0px 3px 0px;">{{ $item['value']['ref_range'] ?? '' }}</td>
                                <!-- Ref. Ranges -->
                            </tr>
                        @endif
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>

    <footer>
        <table style="width:100%">
            <tr>
                <td style="width:450px;">
                    <table style="border-collapse:collapse; width:100%;">
                        <!-- First row: Logo + Text (1 column) + QR beside -->
                        <tr>
                            <!-- Left column: Logo + Text -->
                            <td style="width:80px; text-align:center; vertical-align:middle; padding-right:15px;">
                                <img src="img/smm.png" style="width:70px; display:block; margin:0 auto;">
                                <div style="font-size:8px; margin-top:2px;">SAMM MT 319</div>
                            </td>

                            <!-- Right column: QR Code -->
                            <td style="text-align:left; vertical-align:middle;">
                                <img src="img/qrleft.png" style="width:55px;">
                            </td>
                        </tr>

                        <!-- Second row: Bold accreditation text -->
                        <tr>
                            <td colspan="2" style="font-size:9px; font-weight:bold; padding-top:6px;">
                                Innoquest Pathology Sdn. Bhd. is a full scope CAP and ISO15189 accredited laboratory.
                            </td>
                        </tr>

                        <!-- Third row: Lighter subtext -->
                        <tr>
                            <td colspan="2"
                                style="font-family: Arial, sans-serif; font-weight: 400;font-size:9px;color:#555;">
                                Few assays may be pending ISO15189 accreditation due to their recent launch. Scan our QR
                                to see the full list.
                            </td>
                        </tr>
                    </table>
                </td>

                <td style="vertical-align:top;">
                    <table style="border-collapse:collapse; text-align:center; width:100%;">
                        <tr>
                            <td style="vertical-align:top; text-align:left;">
                                <img src="img/qrright.png" style="width:55px; display:block;">
                            </td>
                            <td style="vertical-align:middle; text-align:center; padding-left:10px;">
                                <div style="font-size:14px; font-weight:normal;">Page 1 of 1</div>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="font-size:9.5px; padding-top:9px; text-align:left;font-weight: 400;">
                                Please scan here to view test methodology or contact our customer care line for
                                assistance.
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

        </table>
    </footer>
</body>

</html>
