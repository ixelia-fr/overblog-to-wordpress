<?php

namespace App\Writer;

require_once('wordpress/wp-load.php');

class WordPressFunctionsWriter extends AbstractWriter implements WriterInterface
{
    public function mapPostData($post): array
    {
        return [
            'post_title'   => $post->title->__toString(),
            'post_content' => $post->content->__toString(),
            'post_slug'    => $this->formatSlug($post->slug->__toString()),
            'post_status'  => 'publish',
            'post_date'    => $post->created_at->__toString(),
        ];
    }

    public function savePost(array $postData): array
    {
        $a = wp_insert_post($postData, true);

        return []; // TODO
    }

    public function saveComment(array $postData, array $commentData)
    {

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
