<?php

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

class PostImportedEvent extends Event
{
    public const NAME = 'post.imported';
}
