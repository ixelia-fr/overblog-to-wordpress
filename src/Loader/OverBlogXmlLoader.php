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

    public function mapToPostObject($post)
    {
        $postObject = new \stdClass();

        $postObject->title = $post->title->__toString();
        $postObject->content = $post->content->__toString();
        $postObject->slug = $post->slug->__toString();
        $postObject->status = $post->status->__toString();
        $postObject->modified_at = $post->modified_at->__toString();
        $postObject->created_at = $post->created_at->__toString();
        $postObject->tags = explode(',', $post->tags);
        $postObject->comments = $post->comments->comment;

        return $postObject;
    }
}
