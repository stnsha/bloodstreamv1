<?php

namespace App\Console\Commands;

use App\Http\Requests\InnoquestResultRequest;
use App\Jobs\Testing\ProcessPanelResultsTesting;
use App\Models\DeliveryFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ReplayDeliveryFiles extends Command
{
    protected $signature = 'testing:replay-delivery-files
        {--limit=100 : Number of delivery files to process}
        {--offset=0 : Skip the first N records}
        {--lab-id= : Filter by lab_id}
        {--sync : Run jobs synchronously instead of dispatching to queue}';

    protected $description = 'Replay delivery files through the ProcessPanelResults testing pipeline';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $offset = (int) $this->option('offset');
        $labId = $this->option('lab-id');
        $sync = $this->option('sync');

        Log::info('ReplayDeliveryFiles: Starting replay', [
            'limit' => $limit,
            'offset' => $offset,
            'lab_id' => $labId,
            'sync' => $sync,
        ]);

        $this->info("Starting delivery file replay (limit={$limit}, offset={$offset}, sync=" . ($sync ? 'yes' : 'no') . ')');

        $query = DeliveryFile::where('status', DeliveryFile::compl);

        if ($labId !== null) {
            $query->where('lab_id', (int) $labId);
            $this->info("Filtering by lab_id={$labId}");
        }

        $deliveryFiles = $query->orderBy('id')
            ->skip($offset)
            ->take($limit)
            ->get();

        $total = $deliveryFiles->count();

        if ($total === 0) {
            $this->warn('No delivery files found matching criteria.');
            Log::info('ReplayDeliveryFiles: No records found', [
                'limit' => $limit,
                'offset' => $offset,
                'lab_id' => $labId,
            ]);

            return self::SUCCESS;
        }

        $this->info("Found {$total} delivery files to replay.");

        $validationRules = (new InnoquestResultRequest())->rules();

        $dispatched = 0;
        $validationFailed = 0;
        $decodeFailed = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($deliveryFiles as $df) {
            $data = json_decode($df->json_content, true);

            if ($data === null) {
                $decodeFailed++;
                Log::warning('ReplayDeliveryFiles: Failed to decode JSON', [
                    'delivery_file_id' => $df->id,
                    'batch_id' => $df->batch_id,
                    'json_error' => json_last_error_msg(),
                ]);
                $bar->advance();

                continue;
            }

            $validator = Validator::make($data, $validationRules);

            if ($validator->fails()) {
                $validationFailed++;
                Log::warning('ReplayDeliveryFiles: Validation failed', [
                    'delivery_file_id' => $df->id,
                    'batch_id' => $df->batch_id,
                    'errors' => $validator->errors()->toArray(),
                ]);
                $bar->advance();

                continue;
            }

            $validated = $validator->validated();
            $requestId = 'replay-' . Str::uuid()->toString();

            if ($sync) {
                ProcessPanelResultsTesting::dispatchSync($validated, $requestId, $df->lab_id);
            } else {
                ProcessPanelResultsTesting::dispatch($validated, $requestId, $df->lab_id);
            }

            $dispatched++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Replay complete.');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total records', $total],
                ['Dispatched', $dispatched],
                ['JSON decode failed', $decodeFailed],
                ['Validation failed', $validationFailed],
            ]
        );

        Log::info('ReplayDeliveryFiles: Replay completed', [
            'total' => $total,
            'dispatched' => $dispatched,
            'decode_failed' => $decodeFailed,
            'validation_failed' => $validationFailed,
            'sync' => $sync,
        ]);

        return self::SUCCESS;
    }
}
