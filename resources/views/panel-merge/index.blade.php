<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Panel Merge Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .output-box {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .ansi-yellow { color: #b58900; }
        .ansi-green { color: #859900; }
        .ansi-red { color: #dc322f; }
        .ansi-blue { color: #268bd2; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-7xl">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">Panel Merge Management</h1>

        <!-- Commands Section -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-700 mb-4">Available Commands</h2>

            <div class="space-y-6">
                @foreach($commands as $command => $config)
                <div class="border border-gray-200 rounded-lg p-4" id="command-{{ Str::slug($command) }}">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <h3 class="text-lg font-medium text-gray-800">{{ $config['name'] }}</h3>
                            <p class="text-sm text-gray-600">{{ $config['description'] }}</p>
                            <code class="text-xs bg-gray-100 px-2 py-1 rounded mt-1 inline-block">{{ $command }}</code>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3 mb-4">
                        @foreach($config['options'] as $option => $description)
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox"
                                   class="command-option rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                   data-command="{{ $command }}"
                                   data-option="{{ $option }}"
                                   {{ $option === 'detailed' ? 'checked' : '' }}>
                            <span class="text-gray-700">--{{ $option }}</span>
                            <span class="text-gray-500">({{ $description }})</span>
                        </label>
                        @endforeach
                    </div>

                    <div class="flex gap-2">
                        <button type="button"
                                class="run-command-btn px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-md text-sm font-medium transition-colors"
                                data-command="{{ $command }}"
                                data-mode="dry-run">
                            Preview (Dry Run)
                        </button>
                        <button type="button"
                                class="run-command-btn px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md text-sm font-medium transition-colors"
                                data-command="{{ $command }}"
                                data-mode="execute">
                            Execute
                        </button>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <!-- Output Section -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8" id="output-section" style="display: none;">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-gray-700">Command Output</h2>
                <button type="button"
                        class="text-gray-500 hover:text-gray-700"
                        onclick="document.getElementById('output-section').style.display='none'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="flex items-center gap-2 mb-3" id="output-status">
                <span id="status-badge" class="px-2 py-1 text-xs font-medium rounded"></span>
                <span id="status-command" class="text-sm text-gray-600"></span>
            </div>
            <div class="bg-gray-900 text-gray-100 rounded-lg p-4 max-h-96 overflow-y-auto">
                <pre class="output-box" id="output-content"></pre>
            </div>
        </div>

        <!-- Details Section -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8" id="details-section" style="display: none;">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-gray-700">Change Details</h2>
                <button type="button"
                        class="text-gray-500 hover:text-gray-700"
                        onclick="document.getElementById('details-section').style.display='none'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Summary -->
            <div class="mb-4" id="details-summary"></div>

            <!-- Filters -->
            <div class="flex gap-4 mb-4">
                <select id="filter-action" class="rounded border-gray-300 text-sm">
                    <option value="">All Actions</option>
                    <option value="created">Created</option>
                    <option value="updated">Updated</option>
                    <option value="deleted">Deleted</option>
                    <option value="merged">Merged</option>
                    <option value="repointed">Repointed</option>
                </select>
                <select id="filter-entity" class="rounded border-gray-300 text-sm">
                    <option value="">All Entity Types</option>
                    <option value="MasterPanel">MasterPanel</option>
                    <option value="MasterPanelItem">MasterPanelItem</option>
                    <option value="Panel">Panel</option>
                    <option value="PanelItem">PanelItem</option>
                </select>
                <button type="button" id="apply-filters" class="px-3 py-1 bg-blue-500 text-white rounded text-sm">
                    Apply Filters
                </button>
            </div>

            <!-- Details Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Entity Type</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Entity ID</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Target</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="details-body">
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="flex justify-between items-center mt-4" id="details-pagination">
            </div>
        </div>

        <!-- History Section -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-gray-700">Execution History</h2>
                <button type="button"
                        class="text-blue-600 hover:text-blue-800 text-sm font-medium"
                        onclick="loadHistory()">
                    Refresh
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Command</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mode</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stats</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="history-body">
                        @forelse($logs as $log)
                        <tr class="hover:bg-gray-50" data-log-id="{{ $log->id }}">
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                {{ $log->created_at->format('Y-m-d H:i:s') }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                {{ $log->command_display_name }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                @if($log->is_dry_run)
                                    <span class="px-2 py-1 text-xs font-medium rounded bg-yellow-100 text-yellow-800">Dry Run</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-medium rounded bg-blue-100 text-blue-800">Execute</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <span class="px-2 py-1 text-xs font-medium rounded {{ $log->status_badge_class }}">
                                    {{ ucfirst($log->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                @if($log->stats)
                                    @php
                                        $displayStats = collect($log->stats)->filter(fn($v) => $v > 0)->take(3);
                                    @endphp
                                    @foreach($displayStats as $key => $value)
                                        <span class="inline-block bg-gray-100 rounded px-1 text-xs mr-1">
                                            {{ Str::headline($key) }}: {{ $value }}
                                        </span>
                                    @endforeach
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                {{ $log->duration ?? '-' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm space-x-2">
                                <button type="button"
                                        class="view-log-btn text-blue-600 hover:text-blue-800"
                                        data-log-id="{{ $log->id }}">
                                    Output
                                </button>
                                @if(!$log->is_dry_run)
                                <button type="button"
                                        class="view-details-btn text-purple-600 hover:text-purple-800"
                                        data-log-id="{{ $log->id }}">
                                    Details
                                </button>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                No execution history yet.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const baseUrl = '{{ url("panel-merge") }}';
        let currentDetailsLogId = null;

        // Action badge classes
        const actionBadgeClasses = {
            'created': 'bg-green-100 text-green-800',
            'updated': 'bg-blue-100 text-blue-800',
            'deleted': 'bg-red-100 text-red-800',
            'merged': 'bg-purple-100 text-purple-800',
            'repointed': 'bg-yellow-100 text-yellow-800',
        };

        // Convert ANSI color codes to HTML
        function convertAnsiToHtml(text) {
            return text
                .replace(/\[33m/g, '<span class="ansi-yellow">')
                .replace(/\[32m/g, '<span class="ansi-green">')
                .replace(/\[31m/g, '<span class="ansi-red">')
                .replace(/\[34m/g, '<span class="ansi-blue">')
                .replace(/\[39m/g, '</span>')
                .replace(/\[37;44m/g, '<span class="ansi-blue">')
                .replace(/\[39;49m/g, '</span>')
                .replace(/\[1m/g, '<strong>')
                .replace(/\[22m/g, '</strong>');
        }

        // Get selected options for a command
        function getSelectedOptions(command) {
            const options = [];
            document.querySelectorAll(`.command-option[data-command="${command}"]:checked`).forEach(checkbox => {
                options.push(checkbox.dataset.option);
            });
            return options;
        }

        // Show output section
        function showOutput(status, command, output, isError = false) {
            const section = document.getElementById('output-section');
            const badge = document.getElementById('status-badge');
            const commandSpan = document.getElementById('status-command');
            const content = document.getElementById('output-content');

            section.style.display = 'block';

            badge.textContent = status;
            badge.className = 'px-2 py-1 text-xs font-medium rounded ' +
                (status === 'Running' ? 'bg-blue-100 text-blue-800' :
                 status === 'Completed' ? 'bg-green-100 text-green-800' :
                 status === 'Failed' ? 'bg-red-100 text-red-800' :
                 'bg-gray-100 text-gray-800');

            commandSpan.textContent = command;
            content.innerHTML = isError ? `<span class="text-red-400">${output}</span>` : convertAnsiToHtml(output);

            section.scrollIntoView({ behavior: 'smooth' });
        }

        // Show details section
        async function showDetails(logId, page = 1) {
            currentDetailsLogId = logId;
            const action = document.getElementById('filter-action').value;
            const entityType = document.getElementById('filter-entity').value;

            let url = `${baseUrl}/${logId}/details?page=${page}`;
            if (action) url += `&action=${action}`;
            if (entityType) url += `&entity_type=${entityType}`;

            try {
                const response = await fetch(url, {
                    headers: { 'Accept': 'application/json' }
                });

                const data = await response.json();

                // Show summary
                const summaryHtml = Object.entries(data.summary || {}).map(([action, entities]) => {
                    const entityCounts = Object.entries(entities).map(([entity, count]) =>
                        `<span class="text-gray-600">${entity}: ${count}</span>`
                    ).join(', ');
                    return `<span class="inline-block mr-4 px-2 py-1 text-xs font-medium rounded ${actionBadgeClasses[action] || 'bg-gray-100 text-gray-800'}">${action.charAt(0).toUpperCase() + action.slice(1)}</span> ${entityCounts}`;
                }).join('<br>');

                document.getElementById('details-summary').innerHTML = summaryHtml || '<span class="text-gray-500">No changes recorded</span>';

                // Show table
                const tbody = document.getElementById('details-body');
                const pagination = data.details || {};
                const details = pagination.data || [];

                if (details.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No detailed changes recorded for this execution.</td></tr>';
                } else {
                    tbody.innerHTML = details.map(d => `
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 text-sm">
                                <span class="px-2 py-1 text-xs font-medium rounded ${actionBadgeClasses[d.action] || 'bg-gray-100 text-gray-800'}">
                                    ${d.action}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-sm text-gray-900">${d.entity_type}</td>
                            <td class="px-3 py-2 text-sm text-gray-600">#${d.entity_id}</td>
                            <td class="px-3 py-2 text-sm text-gray-900">${d.entity_name || '-'}</td>
                            <td class="px-3 py-2 text-sm text-gray-600">${d.entity_unit || '-'}</td>
                            <td class="px-3 py-2 text-sm text-gray-600">${d.target_id ? '#' + d.target_id + (d.target_name ? ': ' + d.target_name : '') : '-'}</td>
                            <td class="px-3 py-2 text-sm text-gray-600 max-w-xs truncate" title="${d.description || ''}">${d.description || '-'}</td>
                        </tr>
                    `).join('');
                }

                // Show pagination
                const paginationHtml = pagination.total > 0 ? `
                    <span class="text-sm text-gray-600">
                        Showing ${pagination.from || 0} to ${pagination.to || 0} of ${pagination.total || 0} entries
                    </span>
                    <div class="flex gap-2">
                        ${pagination.current_page > 1 ? `<button onclick="showDetails(${logId}, ${pagination.current_page - 1})" class="px-3 py-1 bg-gray-200 rounded text-sm">Previous</button>` : ''}
                        ${pagination.current_page < pagination.last_page ? `<button onclick="showDetails(${logId}, ${pagination.current_page + 1})" class="px-3 py-1 bg-gray-200 rounded text-sm">Next</button>` : ''}
                    </div>
                ` : '<span class="text-sm text-gray-500">No entries</span>';
                document.getElementById('details-pagination').innerHTML = paginationHtml;

                document.getElementById('details-section').style.display = 'block';
                document.getElementById('details-section').scrollIntoView({ behavior: 'smooth' });

            } catch (error) {
                alert('Failed to load details: ' + error.message);
            }
        }

        // Apply filters
        document.getElementById('apply-filters').addEventListener('click', function() {
            if (currentDetailsLogId) {
                showDetails(currentDetailsLogId, 1);
            }
        });

        // Run command
        document.querySelectorAll('.run-command-btn').forEach(btn => {
            btn.addEventListener('click', async function() {
                const command = this.dataset.command;
                const mode = this.dataset.mode;
                let options = getSelectedOptions(command);

                // Handle mode-specific options
                if (mode === 'dry-run') {
                    if (!options.includes('dry-run')) {
                        options.push('dry-run');
                    }
                    // Remove 'fix' option for dry-run
                    options = options.filter(o => o !== 'fix');
                } else {
                    // For execute mode, remove dry-run and add fix if applicable
                    options = options.filter(o => o !== 'dry-run');
                    if (command === 'panel:fix-mismatched-references' && !options.includes('fix')) {
                        options.push('fix');
                    }
                }

                // Confirm execution (not dry-run)
                if (mode === 'execute') {
                    if (!confirm('Are you sure you want to execute this command? This will make changes to the database.')) {
                        return;
                    }
                }

                // Disable all buttons
                document.querySelectorAll('.run-command-btn').forEach(b => b.disabled = true);

                showOutput('Running', command, 'Executing command, please wait...');

                try {
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 300000); // 5 minute timeout

                    const response = await fetch(`${baseUrl}/run`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ command, options }),
                        signal: controller.signal
                    });

                    clearTimeout(timeoutId);

                    // Check if response is OK
                    if (!response.ok) {
                        const text = await response.text();
                        console.error('Server error:', response.status, text);
                        showOutput('Failed', command, `Server error (${response.status}): ${text.substring(0, 500)}`, true);
                        return;
                    }

                    const data = await response.json();

                    if (data.success) {
                        showOutput('Completed', command, data.output || 'Command completed successfully.');
                    } else {
                        showOutput('Failed', command, data.error || data.output || 'Command failed with no output.', true);
                    }

                    // Reload history
                    loadHistory();

                } catch (error) {
                    console.error('Fetch error:', error);
                    showOutput('Failed', command, 'Request failed: ' + error.message, true);
                } finally {
                    document.querySelectorAll('.run-command-btn').forEach(b => b.disabled = false);
                }
            });
        });

        // View log output
        document.addEventListener('click', async function(e) {
            if (e.target.classList.contains('view-log-btn')) {
                const logId = e.target.dataset.logId;

                try {
                    const response = await fetch(`${baseUrl}/${logId}`, {
                        headers: {
                            'Accept': 'application/json',
                        }
                    });

                    const data = await response.json();
                    const log = data.log;

                    const output = log.output || log.error || 'No output available';
                    showOutput(
                        log.status.charAt(0).toUpperCase() + log.status.slice(1),
                        data.command_name,
                        output,
                        !!log.error && !log.output
                    );

                } catch (error) {
                    alert('Failed to load log: ' + error.message);
                }
            }

            // View details
            if (e.target.classList.contains('view-details-btn')) {
                const logId = e.target.dataset.logId;
                showDetails(logId);
            }
        });

        // Load history
        async function loadHistory() {
            try {
                const response = await fetch(`${baseUrl}/history`, {
                    headers: {
                        'Accept': 'application/json',
                    }
                });

                const data = await response.json();
                const tbody = document.getElementById('history-body');

                if (data.logs.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                No execution history yet.
                            </td>
                        </tr>
                    `;
                    return;
                }

                tbody.innerHTML = data.logs.map(log => `
                    <tr class="hover:bg-gray-50" data-log-id="${log.id}">
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                            ${log.created_at}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                            ${log.command_name}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                            ${log.is_dry_run
                                ? '<span class="px-2 py-1 text-xs font-medium rounded bg-yellow-100 text-yellow-800">Dry Run</span>'
                                : '<span class="px-2 py-1 text-xs font-medium rounded bg-blue-100 text-blue-800">Execute</span>'
                            }
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                            <span class="px-2 py-1 text-xs font-medium rounded ${log.status_badge_class}">
                                ${log.status.charAt(0).toUpperCase() + log.status.slice(1)}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            ${log.stats ? Object.entries(log.stats)
                                .filter(([k, v]) => v > 0)
                                .slice(0, 3)
                                .map(([k, v]) => `<span class="inline-block bg-gray-100 rounded px-1 text-xs mr-1">${k.replace(/_/g, ' ')}: ${v}</span>`)
                                .join('') : '-'}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                            ${log.duration || '-'}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm space-x-2">
                            <button type="button"
                                    class="view-log-btn text-blue-600 hover:text-blue-800"
                                    data-log-id="${log.id}">
                                Output
                            </button>
                            ${!log.is_dry_run ? `
                            <button type="button"
                                    class="view-details-btn text-purple-600 hover:text-purple-800"
                                    data-log-id="${log.id}">
                                Details
                            </button>
                            ` : ''}
                        </td>
                    </tr>
                `).join('');

            } catch (error) {
                console.error('Failed to load history:', error);
            }
        }
    </script>
</body>
</html>
