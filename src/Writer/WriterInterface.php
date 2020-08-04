<?php

namespace App\Writer;

interface WriterInterface
{
    public function mapPostData($post): array;
    public function savePost($postData): array;
    public function saveComment(array $postData, array $commentData);
    public function importImages(array $postData): array;
}
