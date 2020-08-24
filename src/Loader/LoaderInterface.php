<?php

namespace App\Loader;

interface LoaderInterface
{
    public function countPosts(): ?int;
    public function countPages(): ?int;
    public function getPosts(): iterable;
    public function getPages(): iterable;
    public function mapToPostObject($post, $postType);
}
