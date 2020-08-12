<?php

namespace App\Writer;

interface WriterInterface
{
    const WP_POST_STATUS_DRAFT = 'draft';
    const WP_POST_STATUS_PUBLISH = 'publish';

    public function savePost($post);
    public function saveComment($post, $comment, $parentComment = null);
    public function importImages(array $post): array;
}
