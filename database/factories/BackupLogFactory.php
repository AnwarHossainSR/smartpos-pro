<?php

namespace Database\Factories;

use App\Models\BackupLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BackupLogFactory extends Factory
{
    protected $model = BackupLog::class;

    public function definition(): array
    {
        return [
            'filename' => 'backup-'.$this->faker->dateTimeThisYear->format('Y-m-d-His').'-'.$this->faker->bothify('????????').'.sql.gz',
            'disk' => 'r2',
            'path' => 'backups/backup-'.$this->faker->dateTimeThisYear->format('Y-m-d-His').'-'.$this->faker->bothify('????????').'.sql.gz',
            'size_bytes' => $this->faker->numberBetween(1024, 10 * 1024 * 1024),
            'status' => 'completed',
            'error' => null,
            'created_by' => User::factory(),
            'completed_at' => $this->faker->dateTimeThisYear,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
            'completed_at' => null,
            'size_bytes' => 0,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'error' => 'Backup process failed',
            'completed_at' => null,
        ]);
    }
}
