<?php

namespace App\Models;

use App\Enums\AudienceSegmentActor;
use App\Enums\AudienceSegmentStatus;
use Database\Factories\AudienceSegmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AudienceSegment extends AbstractModel
{
    /** @use HasFactory<AudienceSegmentFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
        'actor_scope',
        'rules',
        'stable_key',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => AudienceSegmentStatus::class,
            'actor_scope' => AudienceSegmentActor::class,
            'rules' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AudienceSegment $segment): void {
            if (blank($segment->slug)) {
                $segment->slug = Str::slug($segment->name).'-'.Str::lower(Str::random(4));
            }

            if (blank($segment->stable_key)) {
                $segment->stable_key = 'seg_'.Str::lower(Str::random(16));
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isActive(): bool
    {
        return $this->status === AudienceSegmentStatus::Active;
    }

    public function ruleSummary(): string
    {
        $rules = is_array($this->rules) ? $this->rules : [];
        $parts = [];

        foreach (['all' => 'ALL', 'any' => 'ANY', 'exclude' => 'EXCLUDE'] as $key => $label) {
            $group = is_array($rules[$key] ?? null) ? $rules[$key] : [];

            if ($group === []) {
                continue;
            }

            $bits = [];

            foreach ($group as $rule) {
                if (! is_array($rule)) {
                    continue;
                }

                $bits[] = sprintf(
                    '%s %s %s',
                    (string) ($rule['type'] ?? '?'),
                    strtoupper((string) ($rule['op'] ?? 'eq')),
                    is_scalar($rule['value'] ?? null) ? (string) $rule['value'] : json_encode($rule['value'] ?? null),
                );
            }

            if ($bits !== []) {
                $parts[] = $label.': '.implode('; ', $bits);
            }
        }

        return $parts === [] ? 'No rules' : implode(' | ', $parts);
    }
}
