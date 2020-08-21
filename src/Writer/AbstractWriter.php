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

    protected function formatSlug(string $slug): string
    {
        $slug = preg_replace(':^\d{4}/\d{2}/:', '', $slug);

        // Remove .html from slug
        $slug = preg_replace('/\.html$/', '', $slug);

        return $slug;
    }

    protected function getWordPressStatus($post)
    {
        if ((int) $post->status === 1) {
            return WriterInterface::WP_POST_STATUS_DRAFT;
        }

        return WriterInterface::WP_POST_STATUS_PUBLISH;
    }
}
