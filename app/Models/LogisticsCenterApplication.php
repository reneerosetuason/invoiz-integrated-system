<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogisticsCenterApplication extends Model
{
    protected $fillable = [
        'business_name',
        'owner_name',
        'email',
        'phone',
        'house_number',
        'street',
        'barangay',
        'municipality',
        'province',
        'address',
        'status',
        'submitted_via',
        'wizard_error_steps',
        'notes',
        'reviewed_at',
        'approved_by',
        'provisioned_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
            'wizard_error_steps' => 'array',
            'reviewed_at' => 'datetime',
            'provisioned_at' => 'datetime',
        ];
    }

    public const WIZARD_STEP_LABELS = [
        1 => 'Center & Owner',
        2 => 'Center Location',
        3 => 'Supporting Documents',
        4 => 'Review & Submit',
    ];

    /**
     * Trusted, 1-based wizard steps recorded during submission. Handles the
     * value arriving as a JSON array, a plain array, a JSON string, or a
     * comma-separated string (legacy/admin-imported rows).
     *
     * @return array<int, int> Sorted 1..4 step numbers.
     */
    public static function normalizeWizardErrorSteps($value): array
    {
        if (is_string($value)) {
            $value = str_starts_with(trim($value), '[')
                ? json_decode($value, true)
                : array_map('trim', explode(',', $value));
        }

        if (! is_array($value)) {
            return [];
        }

        $steps = array_filter($value, static function ($step): bool {
            return is_numeric($step) && $step >= 1 && $step <= 4;
        });

        $steps = array_values(array_unique(array_map('intval', $steps)));
        sort($steps);

        return $steps;
    }

    /**
     * @return array<int, int> Sorted 1..4 step numbers recorded on this row.
     */
    public function wizardErrorSteps(): array
    {
        return static::normalizeWizardErrorSteps($this->wizard_error_steps);
    }

    /**
     * @return array<int, string> Step number => human label for recorded steps.
     */
    public function wizardErrorStepLabels(): array
    {
        return collect(static::WIZARD_STEP_LABELS)
            ->only($this->wizardErrorSteps())
            ->all();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function supportingDocuments(): HasMany
    {
        return $this->hasMany(LogisticsCenterApplicationDocument::class)
            ->orderBy('document_type');
    }

    /**
     * Full address composed from the structured PSGC fields, or the raw
     * address when only that was supplied.
     */
    public function composedAddress(): string
    {
        if ($this->address) {
            return $this->address;
        }

        return trim(collect([
            $this->house_number,
            $this->street,
            $this->barangay,
            $this->municipality,
            $this->province,
        ])->implode(', '), ' ,');
    }
}