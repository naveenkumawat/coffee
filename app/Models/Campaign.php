<?php

namespace App\Models;

use App\Enums\CampaignCtaType;
use App\Enums\CampaignFrequencyPolicy;
use App\Enums\CampaignStatus;
use App\Enums\CampaignSurface;
use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Campaign extends AbstractModel
{
    /** @use HasFactory<CampaignFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'name',
        'internal_label',
        'status',
        'surface',
        'title',
        'message',
        'image_path',
        'cta_label',
        'cta_type',
        'cta_product_id',
        'cta_category_id',
        'cta_promotion_id',
        'cta_internal_path',
        'priority',
        'starts_at',
        'ends_at',
        'frequency_policy',
        'cooldown_hours',
        'max_impressions',
        'placement_rules',
        'targeting_rules',
        'trigger_rules',
        'attribution_key',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'surface' => CampaignSurface::class,
            'cta_type' => CampaignCtaType::class,
            'frequency_policy' => CampaignFrequencyPolicy::class,
            'priority' => 'integer',
            'cooldown_hours' => 'integer',
            'max_impressions' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'placement_rules' => 'array',
            'targeting_rules' => 'array',
            'trigger_rules' => 'array',
        ];
    }

    public function impressions(): HasMany
    {
        return $this->hasMany(CampaignImpression::class);
    }

    public function ctaProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'cta_product_id');
    }

    public function ctaCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'cta_category_id');
    }

    public function ctaPromotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class, 'cta_promotion_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isSchedulableNow(?Carbon $now = null): bool
    {
        $now ??= now();

        if ($this->starts_at !== null && $this->starts_at->gt($now)) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->lt($now)) {
            return false;
        }

        return true;
    }

    public function placementSpecificity(): int
    {
        $rules = is_array($this->placement_rules) ? $this->placement_rules : [];
        $score = 0;

        if (! empty($rules['product_ids'])) {
            $score += 40;
        }

        if (! empty($rules['category_ids'])) {
            $score += 25;
        }

        if (! empty($rules['product_tag_ids'])) {
            $score += 15;
        }

        $placements = $rules['placements'] ?? [];

        if (is_array($placements) && $placements !== [] && ! in_array('global', $placements, true)) {
            $score += 10;
        }

        return $score;
    }
}
