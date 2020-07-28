<?php

namespace App\Loader;

class OverBlogXmlLoader implements LoaderInterface
{
    protected $filepath;

    /**
     * @var array
     */
    protected $currentData;

    /**
     * @var \SimpleXMLElement
     */
    protected $root;

    public function __construct(string $filepath)
    {
        $this->filepath = $filepath;
        $this->loadFile($filepath);
    }

    protected function loadFile(string $filepath)
    {
        if (file_exists($filepath)) {
            $this->root = simplexml_load_file($filepath);
        } else {
            throw new \Exception(sprintf('File not found (%s)', $filepath));
        }
    }

    public function countPosts(): ?int
    {
        return count($this->getPosts());
    }

    public function getPosts(): iterable
    {
        return $this->root->posts->post;
    }

    public function getComments($post): ?iterable
    {
        return $post->comments->comment;
    }
}
