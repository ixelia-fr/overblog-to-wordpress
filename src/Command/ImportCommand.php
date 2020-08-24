<?php

namespace App\Command;

use App\Event\Images\EndImportEvent;
use App\Event\Images\ImageImportedEvent;
use App\Event\Images\StartImportEvent;
use App\Event\PostImportedEvent;
use App\Importer;
use App\Loader\LoaderInterface;
use App\Loader\OverBlogXmlLoader;
use App\Writer\WordPressFunctionsWriter;
use App\Writer\WriterInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class ImportCommand extends Command
{
    protected static $defaultName = 'wp:import-overblog';

    /**
     * @var ProgressBar
     */
    protected $progressBar;

    /**
     * @var ProgressBar
     */
    protected $imageProgressBar;

    protected function configure()
    {
        $this
            ->setDescription('Imports XML OverBlog file to WordPress.')
            ->addArgument('file', InputArgument::REQUIRED, 'XML file to import')
            ->addOption('ignore-images', null, InputOption::VALUE_NONE, 'Flag to disable image import')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Max number of posts to import')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dispatcher = $this->getDispatcher($output);
        $loader = new OverBlogXmlLoader($input->getArgument('file'));
        $writer = new WordPressFunctionsWriter($dispatcher);

        $options = [
            'ignore-images' => $input->getOption('ignore-images'),
            'limit'         => $input->getOption('limit'),
        ];

        $importer = new Importer($this->getDispatcher($output), $loader, $writer);
        $this->importPosts($output, $loader, $importer, $options);
        $this->importPages($output, $loader, $importer, $options);

        return Command::SUCCESS;
    }

    protected function importPosts(
        OutputInterface $output,
        LoaderInterface $loader,
        Importer $importer,
        array $options = []
    ) {
        $output->writeln('Importing posts...');
        $countPosts = $loader->countPosts();

        if ($options['limit'] !== null) {
            $countPosts = min($countPosts, $options['limit']);
        }

        $this->progressBar = new ProgressBar($output, $countPosts ?? 0);
        $this->progressBar->start();
        $importer->importPosts($options);
        $this->progressBar->finish();
    }

    protected function importPages(
        OutputInterface $output,
        LoaderInterface $loader,
        Importer $importer,
        array $options = []
    ) {
        $output->writeln('Importing pages...');
        $countPages = $loader->countPages();

        if ($options['limit'] !== null) {
            $countPages = min($countPages, $options['limit']);
        }

        $this->progressBar = new ProgressBar($output, $countPages ?? 0);
        $this->progressBar->start();
        $importer->importPages($options);
        $this->progressBar->finish();
    }

    protected function getDispatcher(OutputInterface $output): EventDispatcherInterface
    {
        $dispatcher = new EventDispatcher();

        $dispatcher->addListener(PostImportedEvent::class, function () {
            $this->progressBar->advance();
        });

        $dispatcher->addListener(StartImportEvent::class, function (StartImportEvent $event) use ($output) {
            $this->imagesImportProgressBar = new ProgressBar($output, $event->getTotal());
            $this->imagesImportProgressBar->start();
        });

        $dispatcher->addListener(ImageImportedEvent::class, function () {
            $this->imagesImportProgressBar->advance();
        });

        $dispatcher->addListener(EndImportEvent::class, function () {
            $this->imagesImportProgressBar->finish();
        });

        return $dispatcher;
    }
}
