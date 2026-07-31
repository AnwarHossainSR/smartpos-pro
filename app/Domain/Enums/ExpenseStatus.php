<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum ExpenseStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
