<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExportUserNodeMonthlyUsage extends Command
{
    protected $signature = 'stat:user-node-month {month? : Month in YYYY-MM format} {--output= : Output JSON file path}';
    protected $description = 'Export per-user node traffic usage for a natural month in JSON format';

    public function handle(): int
    {
        $monthArg = $this->argument('month') ?: Carbon::now()->format('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $monthArg)) {
            $this->error('Invalid month format. Use YYYY-MM.');
            return self::FAILURE;
        }

        try {
            $month = Carbon::createFromFormat('Y-m', $monthArg)->startOfMonth();
        } catch (\Exception $e) {
            $this->error('Invalid month value.');
            return self::FAILURE;
        }

        $recordAt = $month->timestamp;

        $rows = DB::table('v2_stat_user_server_month as s')
            ->join('v2_user as u', 'u.id', '=', 's.user_id')
            ->join('v2_server as sv', 'sv.id', '=', 's.server_id')
            ->where('s.record_at', $recordAt)
            ->select([
                's.user_id',
                'u.email',
                's.server_id',
                'sv.name as server_name',
                's.server_type',
                's.u',
                's.d',
                DB::raw('(s.u + s.d) as total'),
            ])
            ->orderBy('s.user_id')
            ->orderBy('s.server_id')
            ->get();

        $payload = [
            'month' => $month->format('Y-m'),
            'record_at' => $recordAt,
            'items' => $rows,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $this->error('Failed to encode JSON output.');
            return self::FAILURE;
        }

        $output = $this->option('output');
        if ($output) {
            $path = $output;
            if (!preg_match('/^([A-Za-z]:\\\\|\/)/', $path)) {
                $path = storage_path($path);
            }
            file_put_contents($path, $json);
            $this->info('Exported to: ' . $path);
        } else {
            $this->line($json);
        }

        return self::SUCCESS;
    }
}
