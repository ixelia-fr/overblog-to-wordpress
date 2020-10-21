<?php

namespace App\Transformer;

class TypoCleanup implements TransformerInterface
{
    public function transform(string $content): string
    {
        return preg_replace('|&amp;amp;|', '&amp;', $content);
    }
}
