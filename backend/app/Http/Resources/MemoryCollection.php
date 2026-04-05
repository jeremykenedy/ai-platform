<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class MemoryCollection extends ResourceCollection
{
    public $collects = MemoryResource::class;
}
