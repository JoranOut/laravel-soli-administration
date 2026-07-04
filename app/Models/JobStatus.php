<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobStatus extends Model
{
    protected $table = 'soli_job_statuses';

    protected $fillable = [
        'name',
        'display_name',
        'status',
        'last_run_at',
        'last_completed_at',
        'last_error',
        'metadata',
    ];

    protected $casts = [
        'last_run_at' => 'datetime',
        'last_completed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public static function markRunning(string $name, string $displayName): self
    {
        return self::updateOrCreate(
            ['name' => $name],
            [
                'display_name' => $displayName,
                'status' => 'running',
                'last_run_at' => now(),
                'last_error' => null,
            ],
        );
    }

    public function markCompleted(array $metadata = []): void
    {
        $this->update([
            'status' => 'completed',
            'last_completed_at' => now(),
            'last_error' => null,
            'metadata' => $metadata ?: null,
        ]);
    }

    public function markCompletedWithErrors(string $error, array $metadata = []): void
    {
        $this->update([
            'status' => 'completed_with_errors',
            'last_completed_at' => now(),
            'last_error' => $error,
            'metadata' => $metadata ?: null,
        ]);
    }

    public function markFailed(string $error, array $metadata = []): void
    {
        $this->update([
            'status' => 'failed',
            'last_error' => $error,
            'metadata' => $metadata ?: null,
        ]);
    }
}
