<?php

namespace App\Writer;

use Psr\EventDispatcher\EventDispatcherInterface;

abstract class AbstractWriter
{
    protected $dispatcher;

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    protected function getWordPressStatus($post)
    {
        if ((int) $post->status === 1) {
            return WriterInterface::WP_POST_STATUS_DRAFT;
        }

        return WriterInterface::WP_POST_STATUS_PUBLISH;
    }
}
