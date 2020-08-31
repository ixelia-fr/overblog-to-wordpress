<?php

namespace App\Loader;

use App\Post;

interface LoaderInterface
{
    public function countPosts(): ?int;
    public function countPages(): ?int;
    public function getPosts(): iterable;
    public function getPages(): iterable;
    public function mapToPostObject($post, $postType): Post;
}
