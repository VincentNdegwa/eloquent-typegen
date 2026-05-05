<?php

declare(strict_types=1);

namespace VincentNdegwa\EloquentTypegen\Tests\Fixtures\Enums;

enum StringStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
