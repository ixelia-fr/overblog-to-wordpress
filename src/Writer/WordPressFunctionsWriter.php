<?php

namespace App\Writer;

use App\Event\Images\EndImportEvent;
use App\Event\Images\ImageImportedEvent;
use App\Event\Images\StartImportEvent;
use App\Exception\ImportException;
use Symfony\Component\HttpClient\HttpClient;

require_once('wordpress/wp-load.php');
require_once('wordpress/wp-admin/includes/image.php');

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

    public function importImages($post)
    {
        // Super simple way to get images, as we are not sure the HTML code is valid
        $postContent = $post->content->__toString();

        preg_match_all(':<img [^>]*src="([^"]+)":', $postContent, $imgMatches);

        $event = new StartImportEvent(count($imgMatches[1]));
        $this->dispatcher->dispatch($event);

        foreach ($imgMatches[1] as $imgUrl) {
            $filename = $this->getImageNameFromImageUrl($imgUrl, $post->slug->__toString());
            $uploadedFilePath = $this->uploadFileToWordPress($imgUrl, $filename);
            $fileType = wp_check_filetype(basename($filename), null);

            $attachment = [
                'post_mime_type' => $fileType['type'],
                'post_title'     => $filename,
                'post_content'   => '',
                'post_status'    => 'inherit'
            ];

            $attachId = wp_insert_attachment($attachment, $uploadedFilePath);

            $imagePost = get_post($attachId);
            $fullSizePath = get_attached_file($imagePost->ID);
            $attachData = wp_generate_attachment_metadata($attachId, $fullSizePath);
            wp_update_attachment_metadata($attachId, $attachData);

            $post->content = str_replace($imgUrl, wp_get_attachment_url($imagePost->ID), $post->content);

            $this->dispatcher->dispatch(new ImageImportedEvent());
        }

        preg_match_all(':<img [^>]*src="([^"]+)":', $post->content, $imgMatches);

        $this->dispatcher->dispatch(new EndImportEvent());

        return $post;
    }

    protected function uploadFileToWordPress(string $url, string $filename): string
    {
        // Do not keep folder hierarchy
        $filename = str_replace('/', '-', $filename);

        $uploadDir = wp_upload_dir();
        $uploadedFilePath = $uploadDir['path'] . '/' . $filename;

        if (file_exists($uploadedFilePath)) {
            return $uploadedFilePath;
        }

        $httpClient = HttpClient::create(['headers' => [
            'User-Agent' => 'PHP console app',
        ]]);

        $response = $httpClient->request('GET', $url, [
            // 'buffer' => false,
        ]);

        $targetFileHandler = fopen($uploadedFilePath, 'w');
        foreach ($httpClient->stream($response) as $chunk) {
            fwrite($targetFileHandler, $chunk->getContent());
        }
        fclose($targetFileHandler);

        return $uploadedFilePath;
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

        // Generate unique name using the current file name
        $filename = sprintf(
            '%s-%s.%s',
            $prefix,
            substr(md5($url), 0, 10),
            $imgExtension
        );

        return $filename;
    }
}
