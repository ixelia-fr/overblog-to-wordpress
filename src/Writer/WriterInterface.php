<?php

namespace App\Writer;

use App\Post;

interface WriterInterface
{
    const WP_POST_STATUS_DRAFT = 'draft';
    const WP_POST_STATUS_PUBLISH = 'publish';

    public function savePost(Post $post);
    public function saveComment(Post $post, $comment, $parentComment = null);
    public function importImages(Post $post): Post;
    public function importUploadedFiles(Post $post): Post;
}
