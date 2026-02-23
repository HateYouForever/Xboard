<?php

namespace App\Jobs;

use App\Models\StatUserServerMonth;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StatUserServerMonthJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $data;
    protected array $server;
    protected string $protocol;

    public $tries = 3;
    public $timeout = 60;
    public $maxExceptions = 3;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [1, 5, 10];
    }

    /**
     * Create a new job instance.
     */
    public function __construct(array $server, array $data, string $protocol)
    {
        $this->onQueue('stat');
        $this->data = $data;
        $this->server = $server;
        $this->protocol = $protocol;
    }

    public function handle(): void
    {
        $recordAt = strtotime(date('Y-m-01'));

        foreach ($this->data as $uid => $v) {
            try {
                $this->processUserServerMonthlyStat($uid, $v, $recordAt);
            } catch (\Exception $e) {
                Log::error('StatUserServerMonthJob failed for user ' . $uid . ': ' . $e->getMessage());
                throw $e;
            }
        }
    }

    protected function processUserServerMonthlyStat(int $uid, array $v, int $recordAt): void
    {
        $driver = config('database.default');
        if ($driver === 'sqlite') {
            $this->processForSqlite($uid, $v, $recordAt);
        } elseif ($driver === 'pgsql') {
            $this->processForPostgres($uid, $v, $recordAt);
        } else {
            $this->processForOtherDatabases($uid, $v, $recordAt);
        }
    }

    protected function processForSqlite(int $uid, array $v, int $recordAt): void
    {
        DB::transaction(function () use ($uid, $v, $recordAt) {
            $existingRecord = StatUserServerMonth::where([
                'user_id' => $uid,
                'server_id' => $this->server['id'],
                'record_at' => $recordAt,
            ])->first();

            $u = $v[0] * $this->server['rate'];
            $d = $v[1] * $this->server['rate'];

            if ($existingRecord) {
                $existingRecord->update([
                    'u' => $existingRecord->u + $u,
                    'd' => $existingRecord->d + $d,
                    'updated_at' => time(),
                ]);
            } else {
                StatUserServerMonth::create([
                    'user_id' => $uid,
                    'server_id' => $this->server['id'],
                    'server_type' => $this->protocol,
                    'record_at' => $recordAt,
                    'u' => $u,
                    'd' => $d,
                    'created_at' => time(),
                    'updated_at' => time(),
                ]);
            }
        }, 3);
    }

    protected function processForOtherDatabases(int $uid, array $v, int $recordAt): void
    {
        $u = $v[0] * $this->server['rate'];
        $d = $v[1] * $this->server['rate'];

        StatUserServerMonth::upsert(
            [
                'user_id' => $uid,
                'server_id' => $this->server['id'],
                'server_type' => $this->protocol,
                'record_at' => $recordAt,
                'u' => $u,
                'd' => $d,
                'created_at' => time(),
                'updated_at' => time(),
            ],
            ['user_id', 'server_id', 'record_at'],
            [
                'u' => DB::raw("u + VALUES(u)"),
                'd' => DB::raw("d + VALUES(d)"),
                'updated_at' => time(),
            ]
        );
    }

    /**
     * PostgreSQL upsert with arithmetic increments using ON CONFLICT ... DO UPDATE
     */
    protected function processForPostgres(int $uid, array $v, int $recordAt): void
    {
        $table = (new StatUserServerMonth())->getTable();
        $now = time();
        $u = $v[0] * $this->server['rate'];
        $d = $v[1] * $this->server['rate'];

        $sql = "INSERT INTO {$table} (user_id, server_id, server_type, record_at, u, d, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT (user_id, server_id, record_at)
                DO UPDATE SET
                    u = {$table}.u + EXCLUDED.u,
                    d = {$table}.d + EXCLUDED.d,
                    updated_at = EXCLUDED.updated_at";

        DB::statement($sql, [
            $uid,
            $this->server['id'],
            $this->protocol,
            $recordAt,
            $u,
            $d,
            $now,
            $now,
        ]);
    }
}
