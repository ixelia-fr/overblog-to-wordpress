<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportCommand extends Command
{
    protected static $defaultName = 'wp:import-overblog';

    protected function configure()
    {
        $this
            ->setDescription('Imports XML OverBlog file to WordPress.')
            ->addArgument('file', InputArgument::REQUIRED, 'XML file to import')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $fileContent = file_get_contents($input->getArgument('file'));
        $root = new \SimpleXMLElement($fileContent);

        return Command::SUCCESS;
    }
}
