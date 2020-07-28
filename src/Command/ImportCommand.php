<?php

namespace App\Command;

use App\Importer;
use App\Loader\OverBlogXmlLoader;
use App\Writer\WordPressApiWriter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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
            ->addOption('ignore-images', null, InputOption::VALUE_NONE, 'Import images into WordPress')
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
        ];

        $importer = new Importer($loader, $writer);
        $countPosts = $loader->countPosts();

        $progressBar = new ProgressBar($output, $countPosts ?? 0);
        $progressBar->start();

        $importer->import($options);

        $progressBar->finish();

        return Command::SUCCESS;
    }
}
