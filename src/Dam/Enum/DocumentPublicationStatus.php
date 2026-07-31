<?php

declare(strict_types=1);

namespace App\Dam\Enum;

enum DocumentPublicationStatus: string
{
    case Private = 'private';
    case Publishing = 'publishing';
    case Published = 'published';
    case Unpublishing = 'unpublishing';
    case Archived = 'archived';
}
