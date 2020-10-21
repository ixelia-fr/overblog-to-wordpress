<?php

namespace App\Writer;

use App\RedirectManager;
use Psr\EventDispatcher\EventDispatcherInterface;

abstract class AbstractWriter
{
    protected EventDispatcherInterface $dispatcher;
    protected RedirectManager $redirectManager;

    public function __construct(EventDispatcherInterface $dispatcher, RedirectManager $redirectManager)
    {
        $this->dispatcher = $dispatcher;
        $this->redirectManager = $redirectManager;

        $this->init();
    }

    protected function getWordPressStatus($post)
    {
        if ((int) $post->status === 1) {
            return WriterInterface::WP_POST_STATUS_DRAFT;
        }

        return WriterInterface::WP_POST_STATUS_PUBLISH;
    }

    protected function init()
    {
    }
}
