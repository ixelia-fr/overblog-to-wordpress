<?php

namespace App\Writer;

use App\Exception\ImportException;

require_once('wordpress/wp-load.php');

class WordPressFunctionsWriter extends AbstractWriter implements WriterInterface
{
    public function mapPost($post): array
    {
        return [
            'post_title'   => $post->title->__toString(),
            'post_content' => $post->content->__toString(),
            'post_slug'    => $this->formatSlug($post->slug->__toString()),
            'post_status'  => $this->getWordPressStatus($post),
            'post_date'    => $post->created_at->__toString(),
        ];
    }

    public function mapComment($post, $comment, $parentComment = null): array
    {
        $commentDate = $comment->published_at->__toString();
        $commentDateGmt = new \DateTime($commentDate);
        $commentDateGmt->setTimezone(new \DateTimeZone('GMT'));

        $commentData = [
            'comment_author'       => $comment->author_name->__toString(),
            'comment_author_email' => $comment->author_email->__toString(),
            'comment_author_url'   => $comment->author_url->__toString(),
            'comment_content'      => $comment->content->__toString(),
            'comment_date'         => $commentDate,
            'comment_date_gmt'     => $commentDateGmt->format('c'),
            'comment_approved'     => 1,
            'comment_post_ID'      => $post->id,
        ];

        if ($parentComment) {
            $commentData['comment_parent'] = $parentComment->id;
        }

        return $commentData;
    }

    public function savePost($post)
    {
        $postData = $this->mapPost($post);

        add_filter(
            'wp_insert_post_data',
            function ($data) use ($post) {
                $data['post_modified'] = $post->modified_at->__toString();

                $modifiedAtDate = new \DateTime($post->modified_at);
                $modifiedAtDate->setTimezone(new \DateTimeZone('GMT'));
                $data['post_modified_gmt'] = $modifiedAtDate->format('c');

                return $data;
            },
            99,
            2
        );

        $post->id = wp_insert_post($postData, true);

        return $post;
    }

    public function saveComment($post, $comment, $parentComment = null)
    {
        $commentData = $this->mapComment($post, $comment, $parentComment);
        $commentId = wp_insert_comment($commentData);

        if ($commentId === false) {
            throw new ImportException('Comment could not be saved');
        }

        $comment->id = $commentId;

        return $comment;
    }

    public function importImages(array $postData): array
    {
        // // Super simple way to get images, as we are not sure the HTML code is valid
        // preg_match_all(':<img [^>]*src="([^"]+)":', $postData['content'], $imgMatches);
        // // var_dump($imgMatches);
        // // die;
        // $imgHttpClient = HttpClient::create();

        // foreach ($imgMatches[1] as $imgUrl) {
        //     $filename = $this->getImageNameFromImageUrl($imgUrl, $postData['slug']);
        //     $response = $imgHttpClient->request('GET', $imgUrl);
        //     $imgContent = $response->getContent();

        //     $response = $this->client->request('POST', 'media', [
        //         'headers' => [
        //             'Content-Disposition' => "attachment; filename=$filename",
        //             // 'Content-Type' => 'image/jpg',
        //         ],
        //         // 'json' => $data,
        //         'body' => $imgContent,
        //     ]);

        //     $data = $response->toArray();

        //     $postData['content'] = str_replace($imgUrl, $data['guid']['raw'], $postData['content']);
        // }

        // return $postData;
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
