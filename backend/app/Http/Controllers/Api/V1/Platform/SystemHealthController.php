<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SystemHealthController extends Controller
{
    public function index()
    {
        return response()->json([
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->queueStatus(),
            'storage' => $this->storageStatus(),
            'app' => [
                'environment' => app()->environment(),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'debug_mode' => (bool) config('app.debug'),
                'mailer' => config('mail.default'),
                'queue_connection' => config('queue.default'),
            ],
        ]);
    }

    private function checkDatabase(): array
    {
        $start = microtime(true);

        try {
            DB::select('select 1');

            return ['status' => 'ok', 'response_ms' => round((microtime(true) - $start) * 1000, 1)];
        } catch (Throwable $e) {
            return ['status' => 'down', 'message' => $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        try {
            $key = 'health-check-'.Str::random(8);
            Cache::put($key, true, 5);
            $ok = Cache::get($key) === true;
            Cache::forget($key);

            return ['status' => $ok ? 'ok' : 'down', 'driver' => config('cache.default')];
        } catch (Throwable $e) {
            return ['status' => 'down', 'message' => $e->getMessage()];
        }
    }

    private function queueStatus(): array
    {
        try {
            return [
                'status' => 'ok',
                'pending_jobs' => DB::table('jobs')->count(),
                'failed_jobs' => DB::table('failed_jobs')->count(),
            ];
        } catch (Throwable $e) {
            return ['status' => 'unknown', 'message' => $e->getMessage()];
        }
    }

    private function storageStatus(): array
    {
        $path = storage_path();
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);

        if ($total === false || $free === false) {
            return ['status' => 'unknown'];
        }

        $usedPercent = round((($total - $free) / $total) * 100, 1);

        return [
            'status' => $usedPercent >= 90 ? 'warning' : 'ok',
            'total_gb' => round($total / 1073741824, 1),
            'free_gb' => round($free / 1073741824, 1),
            'used_percent' => $usedPercent,
        ];
    }
}
