<?php

namespace App\Modules\Fleet\Models\Compensations;

use App\Modules\Fleet\Models\DriverCompensation;
use Parental\HasParent;

class FixedPerFreightCompensation extends DriverCompensation
{
    use HasParent;
}
