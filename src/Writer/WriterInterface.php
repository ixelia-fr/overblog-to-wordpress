<?php

namespace App\Writer;

interface WriterInterface
{
    public function savePost(array $postData): array;
    public function saveComment(array $postData, array $commentData);
    public function importImages(array $postData): array;
}
