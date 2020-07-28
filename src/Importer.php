<?php

namespace App;

use App\Loader\LoaderInterface;
use App\Writer\WriterInterface;

class Importer
{
    /**
     * @var LoaderInterface
     */
    protected $loader;

    /**
     * @var WriterInterface
     */
    protected $writer;

    public function __construct(LoaderInterface $loader, WriterInterface $writer)
    {
        $this->loader = $loader;
        $this->writer = $writer;
    }

    public function import(array $options = [])
    {
        $this->importPosts($options);
    }

    public function importPosts(array $options)
    {
        $posts = $this->loader->getPosts();

        foreach ($posts as $post) {
            $postData = [
                'title'   => $post->title->__toString(),
                'content' => $post->content->__toString(),
                'slug'    => $this->formatSlug($post->slug->__toString()),
                'status'  => 'publish',
                'date'    => $post->created_at->__toString(),
            ];

            if (!empty($options['ignore-images'])) {
                $postData = $this->writer->importImages($postData);
            }

            $wpPostData = $this->writer->savePost($postData);
            $comments = $this->loader->getComments($post);

            if ($comments) {
                $this->importComments($wpPostData, $comments);
            }
        }
    }

    private function importComments(array $wpPostData, $comments)
    {
        foreach ($comments as $comment) {
            $data = [
                'author_name'  => $comment->author_name->__toString(),
                'author_email' => $comment->author_email->__toString(),
                'author_url'   => $comment->author_url->__toString(),
                'content'      => $comment->content->__toString(),
                'date'         => $comment->published_at->__toString(),
                'status'       => 'approve',
            ];

            $this->writer->saveComment($wpPostData, $data);
        }
    }

    private function formatSlug(string $slug): string
    {
        $slug = preg_replace(':^\d{4}/\d{2}/:', '', $slug);

        return $slug;
    }
}
