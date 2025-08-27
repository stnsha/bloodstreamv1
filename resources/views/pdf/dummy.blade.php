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

        sup {
            font-size: 8px;
            vertical-align: super;
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
                    <p style="font-size:11px;font-style:normal;margin-bottom:23px;margin-top:40px;">*copy*</p>
                    <p style="font-size:11px;font-weight:bold;">
                        <span style="padding-right:40px;">Patient Details</span>
                        <span style="font-weight:normal;">UR</span>
                    </p>
                    <p style="font-size:11px;font-weight:normal;margin-top:5px;">{{ $result['patient_info']['name'] }}</p>
                    <p style="font-size:11px;font-weight:normal;margin-top:5px;">
                        <span>Ref</span>
                        <span>:</span>
                    </p>
                    <table style="font-size:11px; font-weight:bold; margin-top:35px; border-collapse:collapse;">
                        <tr>
                            <td style="padding-right:30px;">
                                <span style="display:inline-block;width:45px;">DOB&nbsp;&nbsp;&nbsp;:</span>
                                <span style="display:inline-block;width:45px;">{{ $result['patient_info']['dob'] }}</span>
                            </td>
                            <td style="padding-top:5px;padding-left:15px;">
                                <span style="display:inline-block;">Sex:</span>
                                <span style="display:inline-block;">{{ $result['patient_info']['gender'] }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding-top:3px; margin-right:50px;">
                                <span style="display:inline-block;width:50px;">ID NO.:</span>
                                <span style="display:inline-block;margin-left:-5px;">{{ $result['patient_info']['icno'] }}</span>
                            </td>
                            <td style="padding-top:3px;padding-left:15px;">
                                <span style="display:inline-block;">Age:</span>
                                <span style="display:inline-block;">{{ $result['patient_info']['age'] }}</span>
                            </td>
                        </tr>
                    </table>
                    <table style="font-size:11px; font-weight:normal;border-collapse:collapse;">
                        <tr>
                            <td style="padding-right:30px;">
                                <span style="display:inline-block;width:55px;">Collected:</span>
                                <span style="display:inline-block;padding-left:7px;">{{ $result['test_dates']['collected_date'] }}</span>
                            </td>
                            <td style="padding-top:3px;">
                                <span style="display:inline-block;margin-left:-7px;">{{ $result['test_dates']['collected_time'] }}</span>
                                <span style="display:inline-block;padding-left:20px;">Ward:</span>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding-top:3px; margin-right:50px;">
                                <span style="display:inline-block;width:55px;">Referred&nbsp;:</span>
                                <span style="display:inline-block;padding-left:7px;">{{ $result['test_dates']['reported_date'] }}</span>
                            </td>
                            <td style="padding-top:3px;">
                                <span style="display:inline-block;margin-left:-7px;">Yr Ref:</span>
                                <span style="display:inline-block;"></span>
                            </td>
                        </tr>
                    </table>

                </td>
                <td style="width:37%;">
                    <table style="font-size:11px;font-weight:bold;border-collapse:collapse;">
                        <tr>
                            <td style="font-size:11px;font-weight:normal;padding-bottom:20px;padding-top:20px;">Courier
                                Run:</td>
                        </tr>
                        <tr>
                            <td
                                style="font-size:11px;font-weight:bold;text-decoration:underline;padding-bottom:20px;padding-top:20px;">
                                Doctor
                                Details
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <span style="display: block; margin-bottom: 5px;">{{ $result['doctor_info']['name'] }}</span>
                                <span style="display: block; margin-bottom: 5px;">{{ $result['doctor_info']['outlet_name'] }}</span>
                                <span style="display: block; margin-bottom: 5px;">{{ $result['doctor_info']['outlet_address'] }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding-top:20px;">Lab No.:{{ $result['lab_info']['labno'] }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        @foreach ($result['resultItems'] as $item)
            <table style="width:100%;padding-bottom:20px;">
                <tr>
                    <td style="padding-top:25px;padding-left:10px;">
                        <span style="font-size:13px;font-weight:bold;">{{ $item['category_name'] }}</span>
                        <span
                            style="font-size:13px;font-weight:bold;margin-left:160px;">{{ $item['category_descr'] }}</span>
                    </td>
                </tr>
            </table>
            @php
                $headerItems = ['Haemoglobin', 'White Cell Count', 'Platelets'];
                $isInSubSection = false;
                $currentHeader = null;
            @endphp
            
            @foreach ($item['items'] as $index => $panel_item)
                @php
                    // Check if this is a header item
                    $itemName = isset($panel_item['base_name']) ? $panel_item['base_name'] : $panel_item['panel_item_name'];
                    $isHeader = in_array($itemName, $headerItems);
                    
                    // Update sub-section status
                    if ($isHeader) {
                        $isInSubSection = true;
                        $currentHeader = $itemName;
                    } elseif ($isInSubSection && $currentHeader === 'Haemoglobin') {
                        // Count items after Haemoglobin header
                        $itemsAfterHeader = 0;
                        for ($i = $index - 1; $i >= 0; $i--) {
                            $prevItem = $item['items'][$i];
                            $prevName = isset($prevItem['base_name']) ? $prevItem['base_name'] : $prevItem['panel_item_name'];
                            if ($prevName === 'Haemoglobin') {
                                break;
                            }
                            $itemsAfterHeader++;
                        }
                        if ($itemsAfterHeader >= 6) {
                            $isInSubSection = false;
                            $currentHeader = null;
                        }
                    } elseif ($isInSubSection && $currentHeader === 'White Cell Count' && $itemName === 'N:L ratio') {
                        // End of White Cell Count sub-section after N:L ratio
                        $isInSubSection = false;
                        $currentHeader = null;
                    }
                    
                    // Determine indentation with &nbsp;
                    // Special case: N:L ratio gets Haemoglobin-style indentation
                    $indentation = (($isInSubSection && !$isHeader) || $itemName === 'N:L ratio') ? '&nbsp;&nbsp;&nbsp;&nbsp;' : '';
                @endphp
                
                @if (isset($panel_item['base_name']))
                    {{-- Grouped item (has both percentage and value) --}}
                    @php
                        $hasAbnormalFlag = ($panel_item['percentage'] && $panel_item['percentage']['flag'] != 'N') || ($panel_item['value'] && $panel_item['value']['flag'] != 'N');
                        $percentageAbnormal = $panel_item['percentage'] && $panel_item['percentage']['flag'] != 'N';
                        $valueAbnormal = $panel_item['value'] && $panel_item['value']['flag'] != 'N';
                        $nameWeight = $isHeader ? 'bold' : ($hasAbnormalFlag ? 'bold' : 'normal');
                        $isNLRatio = $panel_item['base_name'] === 'N:L ratio';
                        $NLRatioAb = $isNLRatio && $panel_item['value']['flag'] != 'N';
                    @endphp
                    <table style="width:100%;table-layout:fixed;">
                        <tr>
                            <td style="width:5%;font-size:13px;font-weight:bold;">
                                @if($hasAbnormalFlag) * @endif
                            </td>
                            <td style="width:43%;font-size:13px;font-weight:{{ $nameWeight }};">{!! $indentation !!}{{ $panel_item['base_name'] }}</td>
                            <td style="width:7%;font-size:13px;font-weight:{{ $percentageAbnormal || $NLRatioAb ? 'bold' : 'normal' }};text-decoration:{{ $percentageAbnormal || $NLRatioAb ? 'underline' : 'none' }};">
                                @if($panel_item['percentage'])
                                    {{ $panel_item['percentage']['result_value'] }}{{ $panel_item['percentage']['unit'] }}
                                @endif
                                @if($isNLRatio)
                                    {{ $panel_item['value']['result_value'] }}
                                @endif
                            </td>
                            <td style="width:7%;font-size:13px;font-weight:{{ $valueAbnormal ? 'bold' : 'normal' }};text-decoration:{{ $valueAbnormal ? 'underline' : 'none' }};">
                                @if($panel_item['value'] && !$isNLRatio)
                                    {{ $panel_item['value']['result_value'] }}
                                @endif
                            </td>
                            <td style="width:15%;font-size:13px;font-weight:normal;">
                                @if($panel_item['value'])
                                    {!! $panel_item['value']['unit'] !!}
                                @endif
                            </td>
                            <td style="width:15%;font-size:13px;font-weight:normal;">
                                @if($panel_item['value'])
                                    {{ $panel_item['value']['ref_range'] }}
                                @endif
                            </td>
                        </tr>
                    </table>
                @endif
            @endforeach
        @endforeach
    </div>

    <div class="footer">

    </div>
</body>

</html>
