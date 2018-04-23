<?php

namespace xyz13\InstagramBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PingWebDriverCommand extends ContainerAwareCommand
{
    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this
            ->setName('instagram:webdriver:ping')
            ->setDescription('Ping web driver');
    }

    /**
     * @inheritDoc
     *
     * @throws \CommonBundle\Exception\HttpClientException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $webDriver = $this->getContainer()->get('instagram.web_driver.factory')->get()->getWebDriver();
        $logger = $this->getContainer()->get('instagram.web_driver.logger');

        $logger->debug('CURRENT URL: ' . $webDriver->getCurrentURL());
    }
}
