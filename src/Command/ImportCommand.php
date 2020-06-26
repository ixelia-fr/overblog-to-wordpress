<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\HttpClient;

class ImportCommand extends Command
{
    protected static $defaultName = 'wp:import-overblog';
    // protected $dryRun = false;

    protected function configure()
    {
        $this
            ->setDescription('Imports XML OverBlog file to WordPress.')
            ->addArgument('file', InputArgument::REQUIRED, 'XML file to import')
            ->addArgument('wordpress_base_uri', InputArgument::REQUIRED, 'WordPress base URI')
            ->addArgument('username', InputArgument::REQUIRED, 'Username')
            ->addArgument('password', InputArgument::REQUIRED, 'Password')
            ->addOption('ignore-images', null, InputOption::VALUE_NONE, 'Import images into WordPress', false)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ignoreImages = $input->getOption('ignore-images');
        $root = simplexml_load_file($input->getArgument('file'));

        $base64 = base64_encode(sprintf('%s:%s', $input->getArgument('username'), $input->getArgument('password')));
        $client = HttpClient::createForBaseUri(
            $input->getArgument('wordpress_base_uri') . '/wp-json/wp/v2/posts',
            [
                'headers' => [
                    'Authorization' => 'Basic ' . $base64,
                ],
            ],
        );

        $progressBar = new ProgressBar($output, count($root->posts->post));
        $progressBar->start();

        foreach ($root->posts->post as $post) {
            $postData = [
                'title'   => $post->title->__toString(),
                'content' => $post->content->__toString(),
                'slug'    => $this->formatSlug($post->slug->__toString()),
                'status'  => 'publish',
                'date'    => $post->created_at->__toString(),
            ];

            if (!$ignoreImages) {
                $postData = $this->importImages($client, $postData);
            }

            try {
                $response = $client->request(
                    'POST',
                    'posts',
                    [
                        'json' => $postData,
                    ]
                );

                $wpPostData = $response->toArray();

                if ($post->comments->comment) {
                    $this->importComments($client, $wpPostData, $post->comments->comment);
                }
            } catch (\Exception $e) {
                echo $e;
                return Command::FAILURE;
            }

            $progressBar->advance();
        }

        $progressBar->finish();

        return Command::SUCCESS;
    }

    private function formatSlug(string $slug): string
    {
        $slug = preg_replace(':^\d{4}/\d{2}/:', '', $slug);

        return $slug;
    }

    private function importComments($client, array $wpPostData, $comments)
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

            try {
                $response = $client->request(
                    'POST',
                    'comments',
                    [
                        'query' => ['post' => $wpPostData['id']],
                        'json'  => $data,
                    ]
                );
            } catch (\Exception $e) {
                echo $e;
                return Command::FAILURE;
            }
        }
    }

    private function importImages($client, array $postData): array
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

            try {
                $response = $client->request('POST', 'media', [
                    'headers' => [
                        'Content-Disposition' => "attachment; filename=$filename",
                        // 'Content-Type' => 'image/jpg',
                    ],
                    // 'json' => $data,
                    'body' => $imgContent,
                ]);

                $data = $response->toArray();

                $postData['content'] = str_replace($imgUrl, $data['guid']['raw'], $postData['content']);
            } catch (ClientException $th) {
                $res = $th->getResponse();
                $content = $res->getContent(false);

                var_dump($content);
                die;
            }
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
