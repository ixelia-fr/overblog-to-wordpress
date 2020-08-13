<?php

namespace App\Event\Images;

use Symfony\Contracts\EventDispatcher\Event;

class StartImportEvent extends Event
{
    protected $total;

    public function __construct(int $total)
    {
        $this->total = $total;
    }

    public function getTotal(): int
    {
        return $this->total;
    }
}
