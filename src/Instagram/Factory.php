<?php

namespace xyz13\InstagramBundle\Instagram;

use Symfony\Component\HttpFoundation\Request;
use xyz13\InstagramBundle\Client\HttpClient;
use Facebook\WebDriver\Exception\UnknownServerException;
use Facebook\WebDriver\Exception\NoSuchWindowException;
use Facebook\WebDriver\Exception\NoSuchDriverException;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use inisire\ReactBundle\EventDispatcher\ThreadedKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Facebook\WebDriver\Remote\DriverCommand;

class Factory
{
    /**
     * @var \Redis
     */
    private $redis;

    /**
     * @var ThreadedKernelInterface
     */
    private $kernel;

    /**
     * @var HttpClient
     */
    private $client;

    /**
     * @var int
     */
    private $processNumber;

    /**
     * InstagramWebDriverFactory constructor.
     *
     * @param \Redis          $redis
     * @param KernelInterface $kernel
     * @param HttpClient      $client
     * @param int             $processNumber
     */
    public function __construct(\Redis $redis, KernelInterface $kernel, HttpClient $client, int $processNumber = 0)
    {
        $this->redis = $redis;
        $this->kernel = $kernel;
        $this->client = $client;
        $this->processNumber = $processNumber;
    }

    /**
     * @return Instagram
     *
     * @throws \Facebook\WebDriver\Exception\NoSuchElementException
     * @throws \Facebook\WebDriver\Exception\TimeOutException
     */
    public function create()
    {
        $session = $this->redis->get($this->getSessionRedisKey());

        if ($session == false) {

            $instagram = $this->createNewSession();

        } else {

            try {
                $webDriver = RemoteWebDriver::createBySessionID(
                    $session,
                    'http://browser:4444/wd/hub'
                );

                $webDriver->getCurrentUrl();

                $instagram = new Instagram($webDriver);
            } catch (UnknownServerException $e) {
                $instagram = $this->createNewSession();
            } catch (NoSuchWindowException $e) {
                $instagram = $this->createNewSession();
            } catch (NoSuchDriverException $e) {
                $instagram = $this->createNewSession();
            }

        }

        $instagram->getWebDriver()->execute(DriverCommand::MAXIMIZE_WINDOW);

        return $instagram;
    }

    /**
     * @return string
     */
    private function getSessionRedisKey()
    {
        return sprintf('thread_%d_session_id', $this->processNumber);
    }

    /**
     * @return Instagram
     *
     * @throws \Facebook\WebDriver\Exception\NoSuchElementException
     * @throws \Facebook\WebDriver\Exception\TimeOutException
     */
    private function createNewSession()
    {
        $webDriver = RemoteWebDriver::create(
            'http://browser:4444/wd/hub',
            DesiredCapabilities::chrome()
        );

        $instagram = new Instagram($webDriver);

        $session = $webDriver->getSessionID();
        $this->redis->set($this->getSessionRedisKey(), $session);

        return $instagram;
    }

    /**
     * @param RemoteWebDriver $webDriver
     *
     * @throws \xyz13\InstagramBundle\Client\HttpClientException
     */
    private function clear(RemoteWebDriver $webDriver)
    {
        $this->client->request(sprintf('http://browser:4444/wd/hub/session/%s/local_storage', $webDriver->getSessionID()), Request::METHOD_DELETE);
        $this->client->request(sprintf('http://browser:4444/wd/hub/session/%s/session_storage', $webDriver->getSessionID()), Request::METHOD_DELETE);
        $webDriver->manage()->deleteAllCookies();
    }

    private function authorize()
    {
//        $webDriver->navigate()->refresh();
//        $webDriver->get('https://instagram.com');
//        $this->driver->execute(DriverCommand::MAXIMIZE_WINDOW);
//
//        try {
//
//            $webDriver
//                ->wait(10)
//                ->until(
//                    WebDriverExpectedCondition::presenceOfElementLocated(
//                        WebDriverBy::xpath('//a[contains(text(), "' . $login . '")]')
//                    )
//                );
//
//        } catch (NoSuchElementException $e) {
//            $webDriver->wait()->until(
//                function () use ($webDriver) {
//                    $elements = $webDriver->findElements(WebDriverBy::xpath(
//                        '//*[@id="react-root"]/section/main/article/div[2]/div[2]/p/a'
//                    ));
//
//                    return count($elements) > 0;
//                }
//            );
//
//            $element = $webDriver->findElement(WebDriverBy::xpath(
//                '//*[@id="react-root"]/section/main/article/div[2]/div[2]/p/a'
//            ));
//
//            $element->click();
//            $webDriver->wait()->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector(
//                'input[name=username]'
//            )));
//
//            $webDriver->findElement(WebDriverBy::cssSelector('input[name=username]'))->sendKeys($login);
//            sleep(rand(2, 7));
//            $webDriver->findElement(WebDriverBy::cssSelector('input[name=password]'))->sendKeys($password);
//
//            $submit = $webDriver->findElement(WebDriverBy::xpath(
//                '//*[@id="react-root"]/section/main/article/div[2]/div[1]/div/form/span/button'
//            ));
//
//            $webDriver->action()->moveToElement($submit);
//            $point = $submit->getLocationOnScreenOnceScrolledIntoView();
//            $webDriver->getMouse()->mouseMove($submit->getCoordinates(), $point->getX(), $point->getY())->click();
//            sleep(1);
//
//            $submit->click();
//            sleep(rand(2, 7));
//
//            try {
//
//                $webDriver
//                    ->wait(10)
//                    ->until(
//                        WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::xpath(
//                            '//a[contains(text(), "' . $login . '")]'
//                        ))
//                    );
//
//            } catch (NoSuchElementException $e) {
//
//                $webDriver
//                    ->wait()
//                    ->until(
//                        WebDriverExpectedCondition::presenceOfElementLocated(
//                            WebDriverBy::xpath('//button[text() = \'Send Security Code\']')
//                        )
//                    );
//
//                $button = $webDriver->findElement(WebDriverBy::xpath('//button[text() = \'Send Security Code\']'));
//
//                $webDriver->action()->moveToElement($button);
//                $point = $button->getLocationOnScreenOnceScrolledIntoView();
//                $webDriver->getMouse()->mouseMove($button->getCoordinates(), $point->getX(), $point->getY())->click();
//                $button->click();
//
//                sleep(60);
//
//                $mailDriver = RemoteWebDriver::create(
//                    'http://browser:4444/wd/hub',
//                    DesiredCapabilities::chrome()
//                );
//
//                $mailDriver->get('https://mail.ru');
//
//                $mailDriver->wait()->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::xpath('//*[@id="mailbox:login"]')));
//                $mailDriver->findElement(WebDriverBy::xpath('//*[@id="mailbox:login"]'))->sendKeys('tes');
//                $mailDriver->findElement(WebDriverBy::xpath('//*[@id="mailbox:password"]'))->sendKeys('tes');
//                $mailDriver->findElement(WebDriverBy::xpath('//*[@id="mailbox:submit"]'))->click();
//
//                $mailDriver->wait(1000)->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::xpath(
//                    '//div[contains(@class, "b-datalist__item_unread")]//div[text()="Instagram"]'
//                )));
//
//                $elements = $mailDriver->findElements(WebDriverBy::xpath(
//                    '//div[contains(@class, "b-datalist__item_unread")]//div[text()="Instagram"]'
//                ));
//
//                $elements[0]->click();
//
//                $mailDriver->wait()->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::xpath(
//                    '//*[@id="email_content_mailru_css_attribute_postfix"]//font[@size=6]'
//                )));
//
//                $code = $mailDriver->findElement(WebDriverBy::xpath(
//                    '//*[@id="email_content_mailru_css_attribute_postfix"]//font[@size=6]'
//                ))->getText();
//
//                $mailDriver->quit();
//
//                sleep(2);
//
//                $webDriver->wait()->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector(
//                    '#security_code'
//                )));
//
//                $webDriver->findElement(WebDriverBy::cssSelector('#security_code'))->sendKeys($code);
//
//                $webDriver->findElement(WebDriverBy::xpath('//button[text() = \'Submit\']'))->click();
//
//                $webDriver
//                    ->wait()
//                    ->until(
//                        WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::xpath(
//                            '//a[contains(text(), "' . $login . '")]'
//                        ))
//                    );
//            }
//        }
    }
}
