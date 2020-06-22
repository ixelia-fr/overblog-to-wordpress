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
        $fileContent = file_get_contents($input->getArgument('file'));
        $root = new \SimpleXMLElement($fileContent);
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

        // var_dump(count($root->posts));
        // die;

        foreach ($root->posts->post as $post) {
            $data = [
                'title'   => $post->title->__toString(),
                'content' => $post->content->__toString(),
                'slug'    => $this->formatSlug($post->slug->__toString()),
                'status'  => 'publish',
                'date'    => $post->created_at->__toString(),
            ];

            try {
                $client->request(
                    'POST',
                    'posts',
                    [
                        'json' => $data,
                    ]
                );
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
}
