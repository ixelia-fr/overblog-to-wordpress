<?php

namespace App;

use App\Event\PostImportedEvent;
use App\Loader\LoaderInterface;
use App\Writer\WriterInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

class Importer
{
    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @var LoaderInterface
     */
    protected $loader;

    /**
     * @var WriterInterface
     */
    protected $writer;

    public function __construct(
        EventDispatcherInterface $dispatcher,
        LoaderInterface $loader,
        WriterInterface $writer
    ) {
        $this->dispatcher = $dispatcher;
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
            if (empty($options['ignore-images'])) {
                $post = $this->writer->importImages($post);
            }

            $this->writer->savePost($post);
            $comments = $this->loader->getComments($post);

            if ($comments) {
                $this->importComments($post, $comments);
            }

            $event = new PostImportedEvent();
            $this->dispatcher->dispatch($event, PostImportedEvent::NAME);
        }
    }

    private function importComments($post, $comments)
    {
        foreach ($comments as $comment) {
            $this->writer->saveComment($post, $comment);
        }
    }
}
