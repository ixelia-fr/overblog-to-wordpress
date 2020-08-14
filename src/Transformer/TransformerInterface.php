<?php

namespace App\Transformer;

interface TransformerInterface
{
    public function transform(string $content): string;
}
