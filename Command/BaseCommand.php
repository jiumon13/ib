<?php

namespace xyz13\InstagramBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends ContainerAwareCommand
{
    const SECONDS_IN_MINUTE = 60;

    /**
     * @inheritdoc
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        $logger = $this->getContainer()->get('common.command_logger');

        $startTime = new \DateTime();
        parent::run($input, $output);
        $endTime = new \DateTime();

        $logger->info(
            sprintf('Command: %s, START TIME: %s', $this->getName(), $startTime->format('Y-m-d H:i:s'))
        );
        $logger->info(
            sprintf('Command: %s, END TIME: %s', $this->getName(), $endTime->format('Y-m-d H:i:s'))
        );
        $logger->info(
            sprintf(
                'Command: %s, EXECUTION TIME: %s minutes',
                $this->getName(),
                ($endTime->getTimestamp() - $startTime->getTimestamp()) / self::SECONDS_IN_MINUTE
            )
        );
    }
}
