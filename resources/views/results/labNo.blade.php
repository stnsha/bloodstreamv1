<!DOCTYPE html>
<html>
<head>
    <title>Lab Number Matching Results</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .summary {
            background-color: #f9f9f9;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
        }
        .exist-yes {
            background-color: #d4edda;
            color: #155724;
        }
        .exist-no {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <h1>Lab Number Matching Results</h1>
    
    @if(isset($error))
        <div class="summary" style="background-color: #f8d7da; color: #721c24; border-color: #f5c6cb;">
            <h3>Error</h3>
            <p>{{ $error }}</p>
        </div>
    @else
        <div class="summary">
            <h3>Summary</h3>
            <p><strong>Total Lab Numbers:</strong> {{ $combined_stats['summary']['total_lab_numbers'] ?? 0 }}</p>
            <p><strong>Exist in Database:</strong> {{ $combined_stats['summary']['exist_in_db'] ?? 0 }}</p>
            <p><strong>Not Exist in Database:</strong> {{ $combined_stats['summary']['not_exist_in_db'] ?? 0 }}</p>
            <p><strong>Total Sheets Processed:</strong> {{ $combined_stats['total_sheets'] ?? 0 }}</p>
            <p><strong>Processing Time:</strong> {{ $processing_time ?? 'N/A' }}s</p>
            @if(isset($filename))
                <p><strong>File:</strong> {{ $filename }}</p>
            @endif
        </div>
    @endif

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Lab Number (from Excel)</th>
                <th>Found in Database</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($match_results as $index => $result)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $result['from_excel'] }}</td>
                    <td>{{ $result['in_db'] ?: 'Not Found' }}</td>
                    <td class="{{ $result['is_exist'] ? 'exist-yes' : 'exist-no' }}">
                        {{ $result['is_exist'] ? 'EXISTS' : 'NOT FOUND' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center;">No results found</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    
    <p style="margin-top: 20px; font-size: 12px; color: #666;">
        Generated on: {{ date('Y-m-d H:i:s') }}
    </p>
</body>
</html>