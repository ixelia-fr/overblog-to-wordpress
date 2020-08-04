<?php

namespace App\Writer;

abstract class AbstractWriter
{
    protected function formatSlug(string $slug): string
    {
        $slug = preg_replace(':^\d{4}/\d{2}/:', '', $slug);

        return $slug;
    }
}
