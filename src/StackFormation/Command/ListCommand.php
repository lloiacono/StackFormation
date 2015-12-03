<?php

namespace StackFormation\Command;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends AbstractCommand
{

    protected function configure()
    {
        $this
            ->setName('stack:list')
            ->setDescription('List live stacks');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stacks = $this->stackManager->getStacksFromApi();

        $rows = [];
        foreach ($stacks as $stackName => $details) {
            $rows[] = [$stackName, $details['Status']];
        }

        $table = new Table($output);
        $table
            ->setHeaders(['Name', 'Status'])
            ->setRows($rows);
        $table->render();
    }
}
