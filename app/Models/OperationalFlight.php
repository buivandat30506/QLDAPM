<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationalFlight extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_SCHEDULED = 'SCHEDULED';
    public const STATUS_BOARDING  = 'BOARDING';
    public const STATUS_DEPARTED  = 'DEPARTED';
    public const STATUS_ARRIVED   = 'ARRIVED';
    public const STATUS_DELAYED   = 'DELAYED';
    public const STATUS_CANCELLED = 'CANCELLED';

    protected $table = 'operational_flights';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'revision', 'estimated_dep_utc', 'estimated_arr_utc', 'latest_event_at_utc', 'checkin_mode', 'closed_at_utc', 'closed_by',
        'service_date',
        'aircraft_configuration_id', 'seat_capacity_snapshot',
        'schedule_id',
        'flight_number',
        'flight_date',
        'route_id',
        'aircraft_tail',
        'scheduled_dep_utc',
        'scheduled_arr_utc',
        'actual_dep_utc',
        'actual_arr_utc',
        'flight_status',
        'gate_number',
        'delay_reason',
        'resource_id',
    ];

    protected function casts(): array
    {
        return [
            'revision' => 'integer', 'estimated_dep_utc' => 'datetime', 'estimated_arr_utc' => 'datetime',
            'latest_event_at_utc' => 'datetime', 'closed_at_utc' => 'datetime',
            'flight_date'       => 'date:Y-m-d',
            'seat_capacity_snapshot' => 'integer',
            'scheduled_dep_utc' => 'datetime',
            'scheduled_arr_utc' => 'datetime',
            'actual_dep_utc'    => 'datetime',
            'actual_arr_utc'    => 'datetime',
        ];
    }

    /**
     * Lịch bay gốc (Master schedule template)
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(FlightSchedule::class, 'schedule_id');
    }

    /**
     * Tuyến bay (origin -> dest)
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class, 'route_id');
    }

    /**
     * Tàu bay khai thác
     */
    public function aircraft(): BelongsTo
    {
        return $this->belongsTo(Aircraft::class, 'aircraft_tail', 'tail_number');
    }

    public function configuration(): BelongsTo { return $this->belongsTo(AircraftConfiguration::class, 'aircraft_configuration_id'); }

    /**
     * Phân công tổ bay cho chuyến bay (UC-16, UC-17)
     */
    public function crewAssignments(): HasMany
    {
        return $this->hasMany(FlightCrewAssignment::class, 'flight_id');
    }

    /**
     * Danh sách nhân sự tổ bay
     */
    public function crews(): BelongsToMany
    {
        return $this->belongsToMany(CrewMember::class, 'flight_crew_assignments', 'flight_id', 'crew_id')
                    ->withPivot('assigned_position', 'duty_start_utc', 'duty_end_utc', 'status', 'notes')
                    ->withTimestamps();
    }

    /**
     * Đơn hàng đã đặt chỗ trên chuyến bay
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'flight_id');
    }

    /**
     * Danh sách check-in
     */
    public function checkinRecords(): HasMany
    {
        return $this->hasMany(CheckinRecord::class, 'flight_id');
    }

    /**
     * Thẻ hành lý ký gửi
     */
    public function baggageTags(): HasMany
    {
        return $this->hasMany(BaggageTag::class, 'flight_id');
    }

    /**
     * Scope lọc chuyến bay theo trạng thái SCHEDULED
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('flight_status', self::STATUS_SCHEDULED);
    }

    /**
     * Scope theo ngày bay
     */
    public function scopeOnDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('flight_date', $date);
    }

    /**
     * Scope tìm chuyến bay theo cặp sân bay
     */
    public function scopeByAirports(Builder $query, string $origin, string $dest): Builder
    {
        return $query->whereHas('route', function ($q) use ($origin, $dest) {
            $q->where('origin_airport_code', $origin)
              ->where('dest_airport_code', $dest);
        });
    }

    /**
     * Kiểm tra thời gian mở check-in trực tuyến (24h trước giờ cất cánh đến 45 phút trước)
     */
    public function isCheckInOpen(): bool
    {
        if ($this->checkin_mode === 'CLOSED' || $this->closed_at_utc || !in_array($this->flight_status, ['SCHEDULED', 'BOARDING', 'DELAYED'])) { return false; }
        $now = Carbon::now('UTC');
        $dep = Carbon::parse($this->scheduled_dep_utc);

        return $now->between($dep->copy()->subHours(24), $dep->copy()->subMinutes(45));
    }
}
