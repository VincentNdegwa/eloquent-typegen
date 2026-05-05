<?php

declare(strict_types=1);

namespace Based\EloquentTypegen\Tests\Fixtures\Enums;

enum StringStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
