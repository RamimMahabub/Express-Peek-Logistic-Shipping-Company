<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'tracking_number',
        'sender_id',
        'receiver_name',
        'receiver_email',
        'receiver_phone',
        'receiver_address',
        'receiver_city',
        'receiver_country',
        'weight',
        'dimensions',
        'description',
        'status',
        'agent_id',
        'estimated_delivery',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'estimated_delivery' => 'date',
        ];
    }

    // Status constants
    const STATUS_PENDING    = 'pending';
    const STATUS_PICKED_UP  = 'picked_up';
    const STATUS_IN_TRANSIT = 'in_transit';
    const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';
    const STATUS_DELIVERED  = 'delivered';
    const STATUS_FAILED     = 'failed';
    const STATUS_RETURNED   = 'returned';

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING           => 'Pending',
            self::STATUS_PICKED_UP         => 'Picked Up',
            self::STATUS_IN_TRANSIT        => 'In Transit',
            self::STATUS_OUT_FOR_DELIVERY  => 'Out for Delivery',
            self::STATUS_DELIVERED         => 'Delivered',
            self::STATUS_FAILED            => 'Failed Delivery',
            self::STATUS_RETURNED          => 'Returned',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statuses()[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING           => 'yellow',
            self::STATUS_PICKED_UP         => 'blue',
            self::STATUS_IN_TRANSIT        => 'indigo',
            self::STATUS_OUT_FOR_DELIVERY  => 'purple',
            self::STATUS_DELIVERED         => 'green',
            self::STATUS_FAILED            => 'red',
            self::STATUS_RETURNED          => 'orange',
            default                        => 'gray',
        };
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function trackingEvents()
    {
        return $this->hasMany(TrackingEvent::class)->orderByDesc('occurred_at');
    }

    public function latestEvent()
    {
        return $this->hasOne(TrackingEvent::class)->latestOfMany('occurred_at');
    }
}
