<?php

namespace App\Writer;

abstract class AbstractWriter
{
    protected function formatSlug(string $slug): string
    {
        $slug = preg_replace(':^\d{4}/\d{2}/:', '', $slug);

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
