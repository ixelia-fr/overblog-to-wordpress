<?php

namespace App\Writer;

interface WriterInterface
{
    const WP_POST_STATUS_DRAFT = 'draft';
    const WP_POST_STATUS_PUBLISH = 'publish';

    public function mapPostData($post): array;
    public function savePost($postData): array;
    public function saveComment(array $postData, array $commentData);
    public function importImages(array $postData): array;
}
