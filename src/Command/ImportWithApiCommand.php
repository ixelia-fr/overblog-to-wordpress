<?php

namespace App\Command;

use App\Event\PostImportedEvent;
use App\Importer;
use App\Loader\OverBlogXmlLoader;
use App\Writer\WordPressApiWriter;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class ImportWithApiCommand extends Command
{
    protected static $defaultName = 'wp:import-overblog-with-api';

    /**
     * @var ProgressBar
     */
    protected $progressBar;

    protected function configure()
    {
        $this
            ->setDescription('Imports XML OverBlog file to WordPress using the WordPress API.')
            ->addArgument('file', InputArgument::REQUIRED, 'XML file to import')
            ->addArgument('wordpress_base_uri', InputArgument::REQUIRED, 'WordPress base URI')
            ->addArgument('username', InputArgument::REQUIRED, 'Username')
            ->addArgument('password', InputArgument::REQUIRED, 'Password')
            ->addOption('ignore-images', null, InputOption::VALUE_NONE, 'Flag to disable image import')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Max number of posts to import')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $loader = new OverBlogXmlLoader($input->getArgument('file'));
        $writer = new WordPressApiWriter(
            $input->getArgument('wordpress_base_uri'),
            $input->getArgument('username'),
            $input->getArgument('password'),
        );

        $options = [
            'ignore-images' => $input->getOption('ignore-images'),
            'limit'         => $input->getOption('limit'),
        ];

        $importer = new Importer($this->getDispatcher(), $loader, $writer);
        $countPosts = $loader->countPosts();

        if ($options['limit'] !== null) {
            $countPosts = min($countPosts, $options['limit']);
        }

        $this->progressBar = new ProgressBar($output, $countPosts ?? 0);
        $this->progressBar->start();
        $importer->import($options);
        $this->progressBar->finish();

        return Command::SUCCESS;
    }

    protected function getDispatcher(): EventDispatcherInterface
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(PostImportedEvent::NAME, function () {
            $this->progressBar->advance();
        });

        return $dispatcher;
    }
}
