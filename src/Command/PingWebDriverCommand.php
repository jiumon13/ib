<?php

namespace xyz13\InstagramBundle\Command;

use App\Provider\TaskProvider;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
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
            ->setName('webdriver:ping')
            ->setDescription('Ping web driver');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $redis = $this->getContainer()->get('redis');

        if ($redis->exists(TaskProvider::LAST_SESSION_ID_KEY)) {

            $webDriver = RemoteWebDriver::createBySessionID(
                $redis->get(TaskProvider::LAST_SESSION_ID_KEY),
                'http://browser:4444/wd/hub'
            );

            $this
                ->getContainer()
                ->get('instagram.web_driver.logger')
                ->debug('Exist WEB DRIVER. URL: ' . $webDriver->getCurrentURL());

        } else {

            $webDriver = RemoteWebDriver::create(
                'http://browser:4444/wd/hub',
                DesiredCapabilities::firefox()
            );

            $redis->set(TaskProvider::LAST_SESSION_ID_KEY, $webDriver->getSessionID());

            $this
                ->getContainer()
                ->get('instagram.web_driver.logger')
                ->debug('New WEB DRIVER. URL: ' . $webDriver->getCurrentURL());

        }
    }
}
