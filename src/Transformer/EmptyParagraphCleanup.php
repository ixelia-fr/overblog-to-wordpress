<?php

namespace App\Transformer;

class EmptyParagraphCleanup implements TransformerInterface
{
    public function transform(string $content): string
    {
        return preg_replace('|<p[^>]*> *&nbsp; *</p>|', '', $content);
    }
}
