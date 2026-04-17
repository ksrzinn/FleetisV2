<?php

namespace App\Modules\Fleet\Models;

use App\Modules\Fleet\Models\Compensations\FixedPerFreightCompensation;
use App\Modules\Fleet\Models\Compensations\MonthlySalaryCompensation;
use App\Modules\Fleet\Models\Compensations\PercentageCompensation;
use App\Modules\Tenancy\Traits\BelongsToCompany;
use Database\Factories\Fleet\DriverCompensationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Parental\HasChildren;

class DriverCompensation extends Model
{
    use BelongsToCompany, HasChildren, HasFactory;

    protected $table = 'driver_compensations';

    protected $childColumn = 'type';

    /** @var array<string, class-string> */
    protected $childTypes = [
        'percentage'        => PercentageCompensation::class,
        'fixed_per_freight' => FixedPerFreightCompensation::class,
        'monthly_salary'    => MonthlySalaryCompensation::class,
    ];

    protected $fillable = [
        'driver_id', 'type', 'percentage', 'fixed_amount',
        'monthly_salary', 'effective_from', 'effective_to',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to'   => 'date',
    ];

    protected static function newFactory(): DriverCompensationFactory
    {
        return DriverCompensationFactory::new();
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function isActive(): bool
    {
        return $this->effective_to === null;
    }
}
