<?php

namespace App\Writer;

use App\Event\Images\EndImportEvent;
use App\Event\Images\ImageImportedEvent;
use App\Event\Images\StartImportEvent;
use App\Exception\ImportException;
use App\Post;
use Symfony\Component\HttpClient\HttpClient;

require_once('wordpress/wp-load.php');
require_once('wordpress/wp-admin/includes/image.php');

class WordPressFunctionsWriter extends AbstractWriter implements WriterInterface
{
    protected function init()
    {
        add_filter(
            'wp_kses_allowed_html',
            function ($allowed, $context) {
                // Allow forms
                $allowed['form'] = [
                    'action' => true,
                    'method' => true,
                    'target' => true,
                ];

                $allowed['input'] = [
                    'alt' => true,
                    'name' => true,
                    'type' => true,
                    'value' => true,
                    'src' => true,
                ];

                $allowed['select'] = [
                    'name' => true,
                ];

                $allowed['option'] = [
                    'value' => true,
                ];

                // Remove old tags
                unset($allowed['font']);

                return $allowed;
            },
            10,
            2
        );
    }

    public function mapPost($post): array
    {
        $postData = [
            'post_title'   => $post->title,
            'post_content' => $post->content,
            'post_name'    => $post->slug,
            'post_status'  => $this->getWordPressStatus($post),
            'post_date'    => $post->created_at,
            'tags_input'   => $post->tags,
            'post_type'    => $post->type,
        ];

        $existingPostId = $this->getPostId($post);

        if ($existingPostId !== null) {
            $postData['ID'] = $existingPostId;
        }

        return $postData;
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

    public function savePost(Post $post): Post
    {
        $this->preSaveActions($post);

        $postData = $this->mapPost($post);

        add_filter(
            'wp_insert_post_data',
            function ($data) use ($post) {
                // Use created_at instead of modified_at as OverBlog displays list
                // of articles sorted by date of modification
                $data['post_modified'] = $post->created_at;

                $modifiedAtDate = new \DateTime($post->created_at);
                $modifiedAtDate->setTimezone(new \DateTimeZone('GMT'));
                $data['post_modified_gmt'] = $modifiedAtDate->format('c');

                return $data;
            },
            99,
            2
        );

        $post->id = wp_insert_post($postData, true);

        if (isset($post->data['firstImageId'])) {
            set_post_thumbnail($post->id, $post->data['firstImageId']);
        }

        return $post;
    }

    public function saveComment(Post $post, $comment, $parentComment = null)
    {
        $commentData = $this->mapComment($post, $comment, $parentComment);
        $commentId = wp_insert_comment($commentData);

        if ($commentId === false) {
            throw new ImportException('Comment could not be saved');
        }

        $comment->id = $commentId;

        return $comment;
    }

    public function importImages(Post $post): Post
    {
        // Super simple way to get images, as we are not sure the HTML code is valid
        preg_match_all(':<img [^>]*src="([^"]+)":', $post->content, $imgMatches);

        $event = new StartImportEvent(count($imgMatches[1]));
        $this->dispatcher->dispatch($event);

        $imageCount = 0;

        foreach ($imgMatches[1] as $imgUrl) {
            // Fix weird domain name used for images
            $newImgUrl = str_replace('resize.over-blog-prod_internal.com', 'resize.over-blog.com', $imgUrl);
            $newImgUrl = str_replace('?idata.', '?http://idata.', $newImgUrl);

            // Only import images from the OverBlog domain names
            if (!preg_match('/over-blog(-kiwi)?\.com/', $newImgUrl)) {
                continue;
            }

            $imagePost = $this->createWordPressAttachment($newImgUrl);

            if (!$imagePost) {
                continue;
            }

            $post->content = str_replace($imgUrl, wp_get_attachment_url($imagePost->ID), $post->content);

            if ($imageCount === 0) {
                // Keep first image in post data to set it as the post thumbnail later on
                if (!isset($post->data)) {
                    $post->data = [];
                }

                $post->data['firstImageId'] = $imagePost->ID;
            }

            $this->dispatcher->dispatch(new ImageImportedEvent());

            $imageCount++;
        }

        $this->dispatcher->dispatch(new EndImportEvent());

        return $post;
    }

    public function importUploadedFiles(Post $post): Post
    {
        preg_match_all('|<a [^>]*href="(https://data\.over-blog-kiwi\.com[^"]+\.pdf)"|', $post->content, $files);

        $event = new StartImportEvent(count($files[1]));
        $this->dispatcher->dispatch($event);

        foreach ($files[1] as $fileUrl) {
            $imagePost = $this->createWordPressAttachment($fileUrl);

            if (!$imagePost) {
                continue;
            }

            $post->content = str_replace($fileUrl, wp_get_attachment_url($imagePost->ID), $post->content);
        }

        $this->dispatcher->dispatch(new EndImportEvent());

        return $post;
    }

    protected function createWordPressAttachment(string $fileUrl)
    {
        $filename = $this->getFileNameFromUrl($fileUrl);
        $uploadedFilePath = $this->uploadFileToWordPress($fileUrl, $filename);

        if ($uploadedFilePath === null) {
            return null;
        }

        $fileType = wp_check_filetype(basename($filename), null);

        $attachment = [
            'post_mime_type' => $fileType['type'],
            'post_title'     => $filename,
            'post_content'   => '',
            'post_status'    => 'inherit'
        ];

        $existingAttachment = get_page_by_title($filename, OBJECT, 'attachment');

        if ($existingAttachment) {
            return $existingAttachment;
        }

        $attachId = wp_insert_attachment($attachment, $uploadedFilePath);

        $imagePost = get_post($attachId);
        $fullSizePath = get_attached_file($imagePost->ID);
        $attachData = wp_generate_attachment_metadata($imagePost->ID, $fullSizePath);
        wp_update_attachment_metadata($attachId, $attachData);

        return $imagePost;
    }

    protected function uploadFileToWordPress(string $url, string $filename): ?string
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

        try {
            $response = $httpClient->request('GET', $url, [
                // 'buffer' => false,
            ]);
            $response->getHeaders();
        } catch (\Throwable $th) {
            return null;
        }

        $targetFileHandler = fopen($uploadedFilePath, 'w');
        foreach ($httpClient->stream($response) as $chunk) {
            fwrite($targetFileHandler, $chunk->getContent());
        }
        fclose($targetFileHandler);

        return $uploadedFilePath;
    }

    protected function getFileNameFromUrl(string $url): string
    {
        // Manage URLs like https://resize.over-blog.com/9999x9999-z.jpg?https://img.over-blog-kiwi.com/5/04/29/11/20200624/ob_73dba5_img-2989.jpg
        // or http://resize.over-blog.com/170x170.jpg?www.covigneron.com/wp-content/uploads/2019/05/C1-box-te%CC%81le%CC%81chargeable.jpg#width=820&height=600
        $url = preg_replace('#^.+\?(((http)|(www\.)).+)$#', '$1', $url);

        $filename = pathinfo(basename(parse_url($url, PHP_URL_PATH)), PATHINFO_BASENAME);
        $filename = str_replace('ob_', 'wp_', $filename);
        $filename = urldecode($filename);

        return $filename;
    }

    protected function preSaveActions(Post $post)
    {
        $oldSlug = $post->slug;
        $shouldRedirect = $this->improveSlug($post);

        $newUrl = sprintf('/%s', $post->slug);

        if ($post->type === 'page') {
            // Redirect if old slug had a date or if any transformation has been made
            if (preg_match('|^\d{4}/\d{2}|', $oldSlug)) {
                // Slug had a date but pages should not have a date
                $shouldRedirect = true;
            }
        } else {
            if (!preg_match('|^\d{4}/\d{2}|', $oldSlug)) {
                // Slug didn't have a date. We need to redirect to the page with a date
                $createdAt = new \DateTime($post->created_at);
                $newUrl = sprintf(
                    '/%s/%s',
                    $createdAt->format('Y/m'),
                    $post->slug
                );
                $shouldRedirect = true;
            }
        }

        if ($shouldRedirect) {
            $this->addRedirect($oldSlug, $newUrl);
        }
    }

    protected function addRedirect(string $oldUrl, $newUrl)
    {
        if (!class_exists('Red_Item')) {
            throw new ImportException('Redirection plugin is not installed');
        }

        $redirectData = [
            'status' => 'enabled',
            'url' => $oldUrl,
            'action_code' => 301,
            'action_data' => ['url' => $newUrl],
            'action_type' => 'url',
            'match_type' => 'url',
            'regex' => false,
            'group_id' => 1,
        ];

        \Red_Item::create($redirectData);
    }

    protected function improveSlug(Post $post): bool
    {
        $shouldRedirect = false;
        $newSlug = $post->slug;

        // Remove date from slug
        $newSlug = preg_replace('|^\d{4}/\d{2}/|', '', $newSlug);

        // Remove .html from slug. No need for redirect because a general rule is added
        // to redirect all .html URLs to non .html URLs
        $newSlug = preg_replace('/\.html$/', '', $newSlug);

        if (preg_match('/^article-/', $newSlug)) {
            // Use post slug instead of ugly article ID in the URL
            // eg. "article-1234" will become "my-page-title"
            $newSlug = sanitize_title($post->title);
            $shouldRedirect = true;
        }

        $post->slug = $newSlug;

        return $shouldRedirect;
    }

    protected function getPostId(Post $post): ?int
    {
        $args = [
            'name'        => $post->slug,
            'post_type'   => $post->type,
            'numberposts' => 1,
            'post_status' => 'any',
        ];

        $posts = get_posts($args);

        if ($posts) {
            return $posts[0]->ID;
        }

        return null;
    }
}
