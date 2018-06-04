<?php

namespace xyz13\InstagramBundle\Instagram;

use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverKeys;

class Instagram implements InstagramInterface
{
    const LIMIT = 50;

    /**
     * @var RemoteWebDriver
     */
    private $webDriver;

    /**
     * Instagram constructor.
     *
     * @param RemoteWebDriver $webDriver
     */
    public function __construct(RemoteWebDriver $webDriver)
    {
        $this->webDriver = $webDriver;
    }

    /**
     * @param string $link
     *
     * @return array
     */
    public function getCommentators(string $link)
    {
        $this->openTab();

        $this->webDriver->navigate()->to($link);

        $this
            ->webDriver
            ->wait()
            ->until(
                WebDriverExpectedCondition::presenceOfElementLocated(
                    WebDriverBy::xpath('//*[@id="react-root"]/section/main/div/div/article/div[2]/div[1]/ul/li')
                )
            );

        $elements = $this->webDriver->findElements(WebDriverBy::xpath(
            '//*[@id="react-root"]/section/main/div/div/article/div[2]/div[1]/ul/li'
        ));

        $i = 0;
        $k = 0;
        $loadMore = null;

        $commentators = [];
        do {
            $element = array_pop($elements);

            try {
                $element->getText();
            } catch (StaleElementReferenceException $e) {
                continue;
            }

            try {

                $comment = $element->findElement(WebDriverBy::cssSelector('span'))->getText();
                $commentator = $element->findElement(WebDriverBy::cssSelector('a'))->getText();

                if ($commentator == sprintf('View all %s comments', $comment)) {
                    $loadMore = $element->findElement(WebDriverBy::cssSelector('a'));
                } else {
                    $commentators[$commentator] = $comment;
                }

            } catch (NoSuchElementException $e) {
                $loadMore = $element->findElement(WebDriverBy::cssSelector('a'));
            }

            if (count($elements) == 0 && $loadMore != null) {
                $this->webDriver->action()->moveToElement($loadMore);
                $point = $loadMore->getLocationOnScreenOnceScrolledIntoView();
                $this->webDriver->getMouse()->mouseMove($loadMore->getCoordinates(), $point->getX(), $point->getY())->click();
                sleep(rand(3, 4));
                $loadMore->click();
                $loadMore = null;
                $k++;

                $newElements = $this->webDriver->findElements(WebDriverBy::xpath('//*[@id="react-root"]/section/main/div/div/article/div[2]/div[1]/ul/li'));
                $elements = array_slice($newElements, 0, -($i-$k*2));

            }

            if ((count($elements) == 0 && $loadMore == null) || $i > self::LIMIT) {
                break;
            }

            $i++;
        } while (true);

        $this->closeTab();

        return $commentators;
    }

    /**
     * @param string $link
     *
     * @return array
     */
    public function getLikers(string $link)
    {
        $this->openTab();

        $this->webDriver->navigate()->to($link);

        $this->webDriver->wait()->until(WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::xpath('//a[contains(@href, "liked_by")]')));
        $element = $this->webDriver->findElement(WebDriverBy::xpath('//a[contains(@href, "liked_by")]'));

//        $this->driver->action()->moveToElement($element);
//        $point = $element->getLocationOnScreenOnceScrolledIntoView();
//        $this->driver->getMouse()->mouseMove($element->getCoordinates(), $point->getX(), $point->getY())->click();
        $element->click();

        $this
            ->webDriver
            ->wait()
            ->until(
                function () {
                    $elements = $this->webDriver->findElements(WebDriverBy::xpath('/html/body//ul/div/li'));

                    return count($elements) > 10;
                }
            );

        $elements = $this->webDriver->findElements(WebDriverBy::xpath('/html/body//ul/div/li'));

        $i = 0;
        $likers = [];
        do {
            $element = array_shift($elements);

            try {
                $this->webDriver->wait()->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('a:last-child')));
                $likers[] = $element->findElement(WebDriverBy::cssSelector('a:last-child'))->getText();
            } catch (\Exception $e) {
                throw new WebDriverException($e->getMessage(), $e->getCode(), $e->getPrevious(), [
                    'i' => $i,
                    'elementText' => $element->getText()
                ]);
            }

            $rand = rand(7, 10);
            $count = count($elements);

            if ($count == 0 || count($likers) > self::LIMIT) {
                break;
            }

            if ($count <= $rand) {
                $this->webDriver->action()->moveToElement(end($elements));
                $point = end($elements)->getLocationOnScreenOnceScrolledIntoView();
                $this->webDriver->getMouse()->mouseMove(end($elements)->getCoordinates(), $point->getX(), $point->getY());

                sleep(rand(5, 7));
                $newElements = $this->webDriver->findElements(WebDriverBy::xpath('/html/body//ul/div/li'));

                $elements = array_slice($newElements, $i+1);
            }

            $i++;
        } while (true);

        $this->closeTab();

        return $likers;
    }

    /**
     * @param string $link
     * @param string $likerIsLookingFor
     *
     * @return bool
     * @throws WebDriverException
     * @throws \Facebook\WebDriver\Exception\TimeOutException
     */
    public function isLikerExist(string $link, string $likerIsLookingFor)
    {
var_dump('isLikerExist: try open tab');
        $this->openTab();
var_dump('isLikerExist: tab is opened');

var_dump('isLikerExist: navigate');
        $this->webDriver->navigate()->to($link);
var_dump('isLikerExist: current url = ' . $this->webDriver->getCurrentURL());

        try {
var_dump('isLikerExist: wait until liked by');

            $this->webDriver->wait(5)->until(
                WebDriverExpectedCondition::elementToBeClickable(
                    WebDriverBy::xpath(
                        '//a[contains(@href, "liked_by")]'
                    )
                )
            );
var_dump('isLikerExist: liked_by founded');
            $element = $this->webDriver->findElement(WebDriverBy::xpath('//a[contains(@href, "liked_by")]'));
            $element->click();
var_dump('isLikerExist: click on liked_by and wait until likers list');
            $this
                ->webDriver
                ->wait()
                ->until(
                    WebDriverExpectedCondition::presenceOfElementLocated(
                        WebDriverBy::xpath('/html/body//ul/div/li')
                    )
                );
var_dump('isLikerExist: found likers list');
            $elements = $this->webDriver->findElements(WebDriverBy::xpath('/html/body//ul/div/li'));

            $i = 0;
            do {
                $element = array_shift($elements);

                try {
var_dump('isLikerExist: wait until a:last-child');
                    $this->webDriver->wait()->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('a:last-child')));
                    $liker = $element->findElement(WebDriverBy::cssSelector('a:last-child'))->getText();
var_dump('isLikerExist: liker - ' . $liker);
var_dump('isLikerExist: liker is looking for - ' . $likerIsLookingFor);
                    if ($liker == $likerIsLookingFor) {
                        $this->closeTab();
                        return true;
                    }

                } catch (\Exception $e) {
                    $this->closeTab();

                    throw new WebDriverException($e->getMessage(), $e->getCode(), $e->getPrevious(), [
                        'i' => $i,
                        'elementText' => $element->getText()
                    ]);
                }

                $rand = rand(7, 9);
                if (count($elements) <= $rand) {
                    $this->webDriver->action()->moveToElement($element);
                    $point = $element->getLocationOnScreenOnceScrolledIntoView();
                    $this->webDriver->getMouse()->mouseMove($element->getCoordinates(), $point->getX(), $point->getY());

                    sleep(rand(5, 6));
                    $newElements = $this->webDriver->findElements(WebDriverBy::xpath('/html/body//ul/div/li'));

                    $elements = array_slice($newElements, $i+1);
                }

                if (count($elements) == 0 || $i > self::LIMIT) {
                    break;
                }

                $i++;
            } while (true);

        } catch (NoSuchElementException $e) {
var_dump('isLikerExist: liked_by not found. Find $likerIsLookingFor');

            try {
                $this->webDriver->findElement(WebDriverBy::xpath('//a[contains(text(), "' . $likerIsLookingFor . '")]'));
var_dump('isLikerExist: $likerIsLookingFor founded');

                $this->closeTab();
                return true;
            } catch (NoSuchElementException $e) {
var_dump('isLikerExist: $likerIsLookingFor not found');

                $this->closeTab();
                return false;
            }

        }

        $this->closeTab();

        return false;
    }

    /**
     * @param string $link
     * @param string $commentatorIsLookingFor
     *
     * @return bool
     */
    public function findCommentByCommentator(string $link, string $commentatorIsLookingFor)
    {
        $this->openTab();
        $this->webDriver->navigate()->to($link);

        try {
            $this
                ->webDriver
                ->wait()
                ->until(
                    WebDriverExpectedCondition::presenceOfElementLocated(
                        WebDriverBy::xpath('//*[@id="react-root"]/section/main/div/div/article/div[2]/div[1]/ul/li')
                    )
                );

            $elements = $this->webDriver->findElements(WebDriverBy::xpath(
                '//*[@id="react-root"]/section/main/div/div/article/div[2]/div[1]/ul/li'
            ));
        } catch (NoSuchElementException $e) {
            return null;
        }

        $i = 0;
        $k = 0;
        $loadMore = null;
        do {
            $element = array_pop($elements);

            try {

                $comment = $element->findElement(WebDriverBy::cssSelector('span'))->getText();
                $commentator = $element->findElement(WebDriverBy::cssSelector('a'))->getText();

                if ($commentator == $commentatorIsLookingFor) {
                    $this->closeTab();
                    return $comment;
                }

                if ($commentator == sprintf('View all %s comments', $comment)) {
                    $loadMore = $element->findElement(WebDriverBy::cssSelector('a'));
                }

            } catch (NoSuchElementException $e) {
                $loadMore = $element->findElement(WebDriverBy::cssSelector('a'));
            }

            if (count($elements) == 0 && $loadMore != null) {

                $this->webDriver->action()->moveToElement($loadMore);
                $point = $loadMore->getLocationOnScreenOnceScrolledIntoView();
                $this->webDriver->getMouse()->mouseMove($loadMore->getCoordinates(), $point->getX(), $point->getY())->click();
                sleep(rand(3, 4));
                $loadMore->click();
                $loadMore = null;
                $k++;

                $newElements = $this
                    ->webDriver
                    ->findElements(
                        WebDriverBy::xpath(
                            '//*[@id="react-root"]/section/main/div/div/article/div[2]/div[1]/ul/li'
                        )
                    );

                $elements = array_slice($newElements, 0, -($i-$k*2));

            }

            if ((count($elements) == 0 && $loadMore == null) || $i > self::LIMIT) {
                break;
            }

            $i++;
        } while (true);

        $this->closeTab();

        return null;
    }

    /**
     * @param string $link
     * @param string $commentatorIsLookingFor
     * @param string $commentIsLookingFor
     *
     * @return bool
     */
    public function findComment(string $link, string $commentatorIsLookingFor, string $commentIsLookingFor)
    {
        $this->openTab();
        $this->webDriver->navigate()->to($link);

        try {
            $this
                ->webDriver
                ->wait()
                ->until(
                    WebDriverExpectedCondition::presenceOfElementLocated(
                        WebDriverBy::xpath('//*[@id="react-root"]/section/main/div/div/article/div[2]/div[1]/ul/li')
                    )
                );

            $elements = $this->webDriver->findElements(WebDriverBy::xpath(
                '//*[@id="react-root"]/section/main/div/div/article/div[2]/div[1]/ul/li'
            ));
        } catch (NoSuchElementException $e) {
            return null;
        }

        $i = 0;
        $k = 0;
        $loadMore = null;
        do {
            $element = array_pop($elements);

            try {

                $comment = $element->findElement(WebDriverBy::cssSelector('span'))->getText();
                $commentator = $element->findElement(WebDriverBy::cssSelector('a'))->getText();

                if ($commentator == $commentatorIsLookingFor && $comment == $commentIsLookingFor) {
                    $this->closeTab();
                    return $comment;
                }

                if ($commentator == sprintf('View all %s comments', $comment)) {
                    $loadMore = $element->findElement(WebDriverBy::cssSelector('a'));
                }

            } catch (NoSuchElementException $e) {
                $loadMore = $element->findElement(WebDriverBy::cssSelector('a'));
            }

            if (count($elements) == 0 && $loadMore != null) {

                $this->webDriver->action()->moveToElement($loadMore);
                $point = $loadMore->getLocationOnScreenOnceScrolledIntoView();
                $this->webDriver->getMouse()->mouseMove($loadMore->getCoordinates(), $point->getX(), $point->getY())->click();
                sleep(rand(3, 4));
                $loadMore->click();
                $loadMore = null;
                $k++;

                $newElements = $this
                    ->webDriver
                    ->findElements(
                        WebDriverBy::xpath(
                            '//*[@id="react-root"]/section/main/div/div/article/div[2]/div[1]/ul/li'
                        )
                    );

                $elements = array_slice($newElements, 0, -($i-$k*2));

            }

            if ((count($elements) == 0 && $loadMore == null) || $i > self::LIMIT) {
                break;
            }

            $i++;
        } while (true);

        $this->closeTab();

        return null;
    }

    private function closeTab()
    {
        sleep(1);
        $this
            ->webDriver
            ->wait()
            ->until(
                WebDriverExpectedCondition::presenceOfElementLocated(
                    WebDriverBy::tagName('body')
                )
            );

        $body = $this->webDriver->findElement(WebDriverBy::tagName('body'));
        $this->webDriver->action()->moveToElement($body);
        $point = $body->getLocationOnScreenOnceScrolledIntoView();
        $this->webDriver->getMouse()->mouseMove($body->getCoordinates(), $point->getX(), $point->getY())->click();
        $body->sendKeys(WebDriverKeys::CONTROL . '+' . 'w');
        sleep(1);
    }

    private function openTab()
    {
        sleep(1);
        $this
            ->webDriver
            ->wait()
            ->until(
                WebDriverExpectedCondition::presenceOfElementLocated(
                    WebDriverBy::tagName('body')
                )
            );

        $body = $this->webDriver->findElement(WebDriverBy::tagName('body'));
        $this->webDriver->action()->moveToElement($body);
        $point = $body->getLocationOnScreenOnceScrolledIntoView();
        $this->webDriver->getMouse()->mouseMove($body->getCoordinates(), $point->getX(), $point->getY())->click();
        $body->sendKeys(WebDriverKeys::CONTROL . '+' . 't');
        sleep(1);
    }

    /**
     * @param string $link
     *
     * @return string
     */
    public static function getMediaOwner(string $link)
    {
        $data = file_get_contents('http://api.instagram.com/oembed?url=' . $link);

        return json_decode($data, true)['author_name'];
    }

    /**
     * @return RemoteWebDriver
     */
    public function getWebDriver()
    {
        return $this->webDriver;
    }
}
