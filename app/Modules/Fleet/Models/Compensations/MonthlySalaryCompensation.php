<?php

namespace App\Modules\Fleet\Models\Compensations;

use App\Modules\Fleet\Models\DriverCompensation;
use Parental\HasParent;

class MonthlySalaryCompensation extends DriverCompensation
{
    use HasParent;
}
