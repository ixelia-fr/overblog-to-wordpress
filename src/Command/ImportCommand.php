<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;

class ImportCommand extends Command
{
    protected static $defaultName = 'wp:import-overblog';

    protected function configure()
    {
        $this
            ->setDescription('Imports XML OverBlog file to WordPress.')
            ->addArgument('file', InputArgument::REQUIRED, 'XML file to import')
            ->addArgument('wordpress_base_uri', InputArgument::REQUIRED, 'WordPress base URI')
            ->addArgument('username', InputArgument::REQUIRED, 'Username')
            ->addArgument('password', InputArgument::REQUIRED, 'Password')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
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
            $data = [
                'title'   => $post->title->__toString(),
                'content' => $post->content->__toString(),
                'slug'    => $this->formatSlug($post->slug->__toString()),
                'status'  => 'publish',
                'date'    => $post->created_at->__toString(),
            ];

            try {
                $response = $client->request(
                    'POST',
                    'posts',
                    [
                        'json' => $data,
                    ]
                );

                $postData = json_decode($response->getContent(), true);

                if ($post->comments->comment) {
                    $this->importComments($client, $postData, $post->comments->comment);
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

    private function importComments($client, array $postData, $comments)
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
                        'query' => ['post' => $postData['id']],
                        'json'  => $data,
                    ]
                );

                $postData = json_decode($response->getContent(), true);
            } catch (\Exception $e) {
                echo $e;
                return Command::FAILURE;
            }
        }
    }
}
