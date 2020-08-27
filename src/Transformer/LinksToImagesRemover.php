<?php

namespace App\Transformer;

class LinksToImagesRemover implements TransformerInterface
{
    public function transform(string $content): string
    {
        return preg_replace(
            '|<a[^>]*class="ob-link-img"[^>]*>(.+)</a>|m',
            '$1',
            $content
        );

        return $content;
    }
}
