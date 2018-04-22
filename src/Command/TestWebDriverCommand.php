<?php

namespace xyz13\InstagramBundle\Command;

use App\Instagram\WebDriver;
use App\Instagram\InstagramWebDriverFactory;
use Doctrine\DBAL\LockMode;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestWebDriverCommand extends ContainerAwareCommand
{
    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this
            ->setName('webdriver:test')
            ->setDescription('Ping web driver');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');

        $em->beginTransaction();

        try {
            $s = $em->getRepository('App:Task')->findOneBy([]);

            $t = $em->find('App:Task', $s->getId(), LockMode::PESSIMISTIC_WRITE);

            $t->setStatus(1);

            $e = $em->getRepository('App:Task')->find($s->getId());

            $e->setStatus(2);

            $em->commit();
            $em->flush();
        } catch (\Exception $e) {
            $em->rollback();
            throw $e;
        }

        exit;

        $driver = RemoteWebDriver::create(
            'http://browser:4444/wd/hub',
            DesiredCapabilities::firefox()
        );

        $driver->get('https://instagram.com');

        $driver->wait()->until(
            function () use ($driver) {
                $elements = $driver->findElements(WebDriverBy::xpath(
                    '//*[@id="react-root"]/section/main/article/div[2]/div[2]/p/a'
                ));

                return count($elements) > 0;
            }
        );

        $element = $driver->findElement(WebDriverBy::xpath(
            '//*[@id="react-root"]/section/main/article/div[2]/div[2]/p/a'
        ));

        $element->click();
        $driver->wait()->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector(
            'input[name=username]'
        )));

        $driver->findElement(WebDriverBy::cssSelector('input[name=username]'))->sendKeys('test');

        $this
            ->getContainer()
            ->get('instagram.web_driver.logger')
            ->debug('New WEB DRIVER. URL: ' . $driver->getCurrentURL());
    }
}
