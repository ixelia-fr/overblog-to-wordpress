<?php

namespace App\Writer;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WordPressApiWriter extends AbstractWriter implements WriterInterface
{
    /**
     * @var HttpClientInterface
     */
    protected $client;

    public function __construct(string $baseUri, string $username, string $password)
    {
        $base64 = base64_encode(sprintf('%s:%s', $username, $password));
        $this->client = HttpClient::createForBaseUri(
            $baseUri . '/wp-json/wp/v2/posts',
            [
                'headers' => [
                    'Authorization' => 'Basic ' . $base64,
                ],
            ],
        );
    }

    public function mapPost($post): array
    {
        return [
            'title'    => $post->title->__toString(),
            'content'  => $post->content->__toString(),
            'slug'     => $this->formatSlug($post->slug->__toString()),
            'status'   => $this->getWordPressStatus($post),
            'date'     => $post->created_at->__toString(),
            'modified' => $post->modified_at->__toString(), // NOT IMPLEMENTED
        ];
    }

    public function savePost($post): array
    {
        $postData = $this->mapPost($post);

        try {
            $response = $this->client->request(
                'POST',
                'posts',
                [
                    'json' => $postData,
                ]
            );

            $response->getContent(true);
        } catch (\Throwable $th) {
            var_dump($postData);
            $res = $th->getResponse();
            $content = $res->getContent(false);

            var_dump($content);
            die;
        }

        $post->id = $response->toArray()['ID'];

        return $post;
    }

    public function saveComment($post, $comment)
    {
        $commentData = $this->mapComment($post, $comment);

        try {
            $this->client->request(
                'POST',
                'comments',
                [
                    'query' => ['post' => $post->id],
                    'json'  => $commentData,
                ]
            );
        } catch (\Throwable $th) {
            var_dump($post, $mappedCommentData);
            $res = $th->getResponse();
            $content = $res->getContent(false);

            var_dump($content);
            die;
        }
    }

    public function mapComment($post, $comment): array
    {
        return [
            'author_name'  => $comment->author_name->__toString(),
            'author_email' => $comment->author_email->__toString(),
            'author_url'   => $comment->author_url->__toString(),
            'content'      => $comment->content->__toString(),
            'date'         => $comment->published_at->__toString(),
            'status'       => 'approve',
        ];
    }

    public function importImages(array $postData): array
    {
        // Super simple way to get images, as we are not sure the HTML code is valid
        preg_match_all(':<img [^>]*src="([^"]+)":', $postData['content'], $imgMatches);
        // var_dump($imgMatches);
        // die;
        $imgHttpClient = HttpClient::create();

        foreach ($imgMatches[1] as $imgUrl) {
            $filename = $this->getImageNameFromImageUrl($imgUrl, $postData['slug']);
            $response = $imgHttpClient->request('GET', $imgUrl);
            $imgContent = $response->getContent();

            $response = $this->client->request('POST', 'media', [
                'headers' => [
                    'Content-Disposition' => "attachment; filename=$filename",
                    // 'Content-Type' => 'image/jpg',
                ],
                // 'json' => $data,
                'body' => $imgContent,
            ]);

            $data = $response->toArray();

            $postData['content'] = str_replace($imgUrl, $data['guid']['raw'], $postData['content']);
        }

        return $postData;
    }

    protected function getImageNameFromImageUrl(string $url, string $slug): string
    {
        $prefix = strstr($slug, '.', true);

        if (!$prefix) {
            $prefix = $slug;
        }

        $pathInfo = pathinfo(basename(parse_url($url, PHP_URL_PATH)));
        $imgExtension = $pathInfo['extension'];

        if (!$imgExtension) {
            $imgExtension = 'jpg';
        }

        $filename = sprintf(
            '%s-%s.%s',
            $prefix,
            substr(md5($url), 0, 10),
            $imgExtension
        );

        return $filename;
    }
}
