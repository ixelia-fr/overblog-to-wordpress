<?php

namespace App\Transformer;

use App\Loader\LoaderInterface;
use App\RedirectManager;
use Traversable;

class InternalLinksRewriter implements TransformerInterface
{
    protected array $oldUrls = [];
    protected array $newUrls = [];
    protected LoaderInterface $loader;
    protected RedirectManager $redirectManager;

    public function __construct(LoaderInterface $loader, RedirectManager $redirectManager)
    {
        $this->loader = $loader;
        $this->redirectManager = $redirectManager;
        $this->init();
    }

    protected function init()
    {
        $pages = $this->loader->getPages();
        $posts = $this->loader->getPosts();

        $this->loadMappingForPosts($pages, 'page');
        $this->loadMappingForPosts($posts, 'post');
    }

    protected function loadMappingForPosts(Traversable $posts, string $postType): void
    {
        foreach ($posts as $xmlPost) {
            $post = $this->loader->mapToPostObject($xmlPost, $postType);

            if (empty($post->slug)) {
                continue;
            }

            $redirect = $this->redirectManager->getRedirect($post, true);

            if ($redirect) {
                $this->oldUrls[] = sprintf('http://www.sommelier-vins.com/%s', ltrim($redirect['old-slug'], '/'));
                $this->newUrls[] = sprintf('https://www.sommelier-vins.com/%s', ltrim($redirect['new-slug'], '/'));
            }
        }
    }

    public function transform(string $content): string
    {
        return str_replace($this->oldUrls, $this->newUrls, $content);
    }
}
