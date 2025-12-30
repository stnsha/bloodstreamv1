<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MonitorQueueDepth extends Command
{
    protected $signature = 'queue:monitor-depth';
    protected $description = 'Monitor queue depth and log metrics';

    public function handle()
    {
        $queueStats = DB::table('jobs')
            ->select('queue', DB::raw('COUNT(*) as count'))
            ->groupBy('queue')
            ->get()
            ->pluck('count', 'queue');

        $dbConnections = DB::select("SHOW STATUS WHERE variable_name = 'Threads_connected'");
        $connections = $dbConnections[0]->Value ?? 0;

        $failedToday = DB::table('failed_jobs')
            ->whereDate('failed_at', today())
            ->count();

        Log::channel('performance')->info('Queue depth snapshot', [
            'queues' => $queueStats->toArray(),
            'total_jobs' => $queueStats->sum(),
            'db_connections' => $connections,
            'failed_jobs_today' => $failedToday,
        ]);

        $this->info('Queue monitoring logged successfully');

        return 0;
    }
}
