<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'rider_id',
        'type',
        'title',
        'body',
        'data',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'is_read' => 'boolean',
        ];
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    /**
     * Create an in-app rider notification unless an identical unread one
     * already exists for the same rider, type and delivery.
     *
     * Dedupe guard: retried form posts, double taps and repeated polling
     * must never stack identical unread rows. A new row is still created
     * once the previous one is read (genuinely new information then).
     * Returns the created model, or null when skipped as duplicate.
     */
    public static function notifyOnce(
        int $riderId,
        string $type,
        array $data,
        string $title,
        string $body,
    ): ?self {
        $query = static::where('rider_id', $riderId)
            ->where('type', $type)
            ->where('is_read', false);

        if (isset($data['delivery_id'])) {
            $query->where('data->delivery_id', $data['delivery_id']);
        }

        if ($query->exists()) {
            return null;
        }

        return static::create([
            'rider_id' => $riderId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }
}