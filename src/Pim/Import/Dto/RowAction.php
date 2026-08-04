<?php

declare(strict_types=1);

namespace App\Pim\Import\Dto;

enum RowAction
{
    case Created;
    case Updated;
    case Failed;
}
