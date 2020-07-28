<?php

namespace App\Loader;

interface LoaderInterface
{
    public function countPosts(): ?int;
    public function getPosts(): iterable;
    public function getComments($post): ?iterable;
}
