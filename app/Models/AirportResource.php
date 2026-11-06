<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AirportResource extends Model
{
    use HasFactory;

    public const TYPE_TERMINAL         = 'TERMINAL';
    public const TYPE_GATE             = 'GATE';
    public const TYPE_RUNWAY           = 'RUNWAY';
    public const TYPE_BAGGAGE_CAROUSEL = 'BAGGAGE_CAROUSEL';
    public const TYPES = ['TERMINAL' => 'Nhà ga', 'GATE' => 'Cửa ra tàu bay', 'STAND' => 'Vị trí đỗ',
        'BAGGAGE_CAROUSEL' => 'Băng chuyền', 'CHECKIN_COUNTER' => 'Quầy làm thủ tục', 'RUNWAY' => 'Đường băng'];
    public const STATUSES = ['AVAILABLE' => 'Khả dụng', 'MAINTENANCE' => 'Tạm ngừng / bảo trì', 'OCCUPIED' => 'Đang bận'];

    public const STATUS_AVAILABLE   = 'AVAILABLE';
    public const STATUS_MAINTENANCE = 'MAINTENANCE';
    public const STATUS_OCCUPIED    = 'OCCUPIED';

    protected $table = 'airport_resources';

    protected $fillable = [
        'airport_code',
        'resource_type',
        'resource_code',
        'status',
    ];

    public function airport(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'airport_code', 'iata_code');
    }
}
