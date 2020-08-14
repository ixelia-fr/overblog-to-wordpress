<?php

namespace App;

use App\Event\PostImportedEvent;
use App\Loader\LoaderInterface;
use App\Transformer\EmptyParagraphCleanup;
use App\Transformer\FontFamilyRemover;
use App\Transformer\TransformerInterface;
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

    /**
     * @var TransformerInterface[]
     */
    protected $transformers;

    public function __construct(
        EventDispatcherInterface $dispatcher,
        LoaderInterface $loader,
        WriterInterface $writer
    ) {
        $this->dispatcher = $dispatcher;
        $this->loader = $loader;
        $this->writer = $writer;

        $this->transformers = [
            new EmptyParagraphCleanup(),
            new FontFamilyRemover(),
        ];
    }

    public function import(array $options = [])
    {
        $this->importPosts($options);
    }

    public function importPosts(array $options)
    {
        $posts = $this->loader->getPosts();
        $nbImported = 0;

        foreach ($posts as $post) {
            $this->applyTransformers($post);

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
            $nbImported++;

            if ($options['limit'] !== null && $nbImported >= $options['limit']) {
                break;
            }
        }
    }

    private function importComments($post, $comments, $parentComment = null)
    {
        foreach ($comments as $comment) {
            $this->writer->saveComment($post, $comment, $parentComment);

            if (!empty($comment->replies->comment)) {
                $this->importComments($post, $comment->replies->comment, $comment);
            }
        }
    }

    private function applyTransformers($post)
    {
        foreach ($this->transformers as $transformer) {
            $post->content = $transformer->transform($post->content);
        }
    }
}
