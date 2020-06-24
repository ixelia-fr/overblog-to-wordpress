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
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;

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
            // ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry-run')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // $this->dryRun = $input->getOption('dry-run');
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

            $this->importImages($client, $data);

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

    private function importImages($client, array $postData)
    {
        // Super simple way to get images, as we are not sure the HTML code is valid
        preg_match_all(':<img [^>]*src="([^"]+)":', $postData['content'], $matches);
        var_dump($matches);
        die;

        foreach ($matches[1] as $match) {
            $data = [
                "date" => "2015-11-26 10:00:00",
                "date_gmt" => "2015-11-26 09:00:00",
                "modified" => "2015-11-26 10:00:00",
                "modified_gmt" => "2015-11-26 09:00:00",
                "status" => "future",
                "title" => "Titre media",
                "description" => "description media",
                "media_type" => "image",
                // 'source_url' => $match,
                'source_url' => 'http://www.randonavigo.fr/uploads/hike/2018/04/coignieres-montfort/pictures/IMG_9743.jpg',
            ];

            var_dump($data);
            $img = file_get_contents('https://cdn.mgig.fr/2020/06/mg-3e1997fa-6fc0-4bfb-85ef_accroche.jpg');
            // $imgResource = fopen('https://cdn.mgig.fr/2020/06/mg-3e1997fa-6fc0-4bfb-85ef_accroche.jpg', 'r');

            try {
                // $formFields = [
                //     'title' => 'My title',
                //     // 'file' => DataPart::fromPath('/path/to/uploaded/file'),
                //     'file' => new DataPart($imgResource),
                // ];
                // $formData = new FormDataPart($formFields);

                $response = $client->request('POST', 'media', [
                    // 'headers' => $formData->getPreparedHeaders()->toArray(),
                    // 'body' => $formData->bodyToIterable(),

                    'headers' => [
                        'Content-Disposition' => 'attachment; filename=file.jpg',
                        'Content-Type' => 'image/jpg',
                    ],
                    // 'json' => $data,
                    'body' => $img,
                ]);

                $data = $response->getContent(false);
                echo $data;
                die('ooo');

            } catch (ClientException $th) {
                $res = $th->getResponse();
                $content = $res->getContent(false);

                var_dump($content);
                die('ffff');
            }

            die('ggg');


            // var_dump($response->getContent());
            // die;
        }
    }
}
