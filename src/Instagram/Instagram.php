<?php

namespace xyz13\InstagramBundle\Instagram;

use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\TimeOutException;
use Facebook\WebDriver\Exception\WebDriverException;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverKeys;
use xyz13\InstagramBundle\Client\HttpClient;
use xyz13\InstagramBundle\Client\HttpClientException;

class Instagram implements InstagramInterface
{
    const LIMIT = 50;

    const PROCESSED_USER_REDIS_KEY_PATTERN = 'user-%s-processed';
    const USERNAME_PATTERN = '$https:\/\/(www.)?instagram\.com\/([0-9a-zA-Z-_.]+).+$';

    /**
     * @var RemoteWebDriver
     */
    private $driver;

    /**
     * @var HttpClient
     */
    private $client;

    /**
     * @var \Redis
     */
    private $redis;

    /**
     * Instagram constructor.
     *
     * @param RemoteWebDriver $webDriver
     * @param HttpClient      $client
     * @param \Redis          $redis
     */
    public function __construct(RemoteWebDriver $webDriver, HttpClient $client, \Redis $redis)
    {
        $this->driver = $webDriver;
        $this->client = $client;
        $this->redis = $redis;
    }

    /**
     * @param string $link
     *
     * @return array
     *
     * @throws NoSuchElementException
     * @throws TimeOutException
     */
    public function getCommentators(string $link)
    {
        $this->openTab();

        $this->driver->navigate()->to($link);

        $this
            ->driver
            ->wait(8)
            ->until(
                WebDriverExpectedCondition::presenceOfElementLocated(
                    WebDriverBy::xpath('//*[@id="react-root"]/section/main/div/div/article/div[2]/div[1]/ul/li')
                )
            );

        $elements = $this->driver->findElements(WebDriverBy::xpath(
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
                $this->driver->action()->moveToElement($loadMore);
                $point = $loadMore->getLocationOnScreenOnceScrolledIntoView();
                $this->driver->getMouse()->mouseMove($loadMore->getCoordinates(), $point->getX(), $point->getY())->click();
                sleep(rand(3, 4));
                $loadMore->click();
                $loadMore = null;
                $k++;

                $newElements = $this->driver->findElements(WebDriverBy::xpath('//*[@id="react-root"]/section/main/div/div/article/div[2]/div[1]/ul/li'));
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
     * @throws NoSuchElementException
     * @throws TimeOutException
     */
    public function getLikers(string $link)
    {
        $this->openTab();

        $this->driver->navigate()->to($link);

        $this->driver->wait(8)->until(WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::xpath('//a[contains(@href, "liked_by")]')));
        $element = $this->driver->findElement(WebDriverBy::xpath('//a[contains(@href, "liked_by")]'));

//        $this->driver->action()->moveToElement($element);
//        $point = $element->getLocationOnScreenOnceScrolledIntoView();
//        $this->driver->getMouse()->mouseMove($element->getCoordinates(), $point->getX(), $point->getY())->click();
        $element->click();

        $this
            ->driver
            ->wait(8)
            ->until(
                function () {
                    $elements = $this->driver->findElements(WebDriverBy::xpath('/html/body//ul/div/li'));

                    return count($elements) > 10;
                }
            );

        $elements = $this->driver->findElements(WebDriverBy::xpath('/html/body//ul/div/li'));

        $i = 0;
        $likers = [];
        do {
            $element = array_shift($elements);

            try {
                $this->driver->wait(8)->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('a:last-child')));
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
                $this->driver->action()->moveToElement(end($elements));
                $point = end($elements)->getLocationOnScreenOnceScrolledIntoView();
                $this->driver->getMouse()->mouseMove(end($elements)->getCoordinates(), $point->getX(), $point->getY());

                sleep(rand(5, 7));
                $newElements = $this->driver->findElements(WebDriverBy::xpath('/html/body//ul/div/li'));

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
     * @throws TimeOutException
     */
    public function isLikerExist(string $link, string $likerIsLookingFor)
    {
        var_dump('isLikerExist: try open tab');
        $this->openTab();
        var_dump('isLikerExist: tab is opened');

        var_dump('isLikerExist: navigate');
        $this->driver->navigate()->to($link);
        var_dump('isLikerExist: current url = ' . $this->driver->getCurrentURL());

        try {
            var_dump('isLikerExist: wait until liked by');

            $this->driver->wait(5)->until(
                WebDriverExpectedCondition::elementToBeClickable(
                    WebDriverBy::xpath(
                        '//a[contains(@href, "liked_by")]'
                    )
                )
            );
            var_dump('isLikerExist: liked_by founded');
            $element = $this->driver->findElement(WebDriverBy::xpath('//a[contains(@href, "liked_by")]'));
            $element->click();
            var_dump('isLikerExist: click on liked_by and wait until likers list');
            $this
                ->driver
                ->wait(8)
                ->until(
                    WebDriverExpectedCondition::presenceOfElementLocated(
                        WebDriverBy::xpath('/html/body//ul/div/li')
                    )
                );
            var_dump('isLikerExist: found likers list');
            $elements = $this->driver->findElements(WebDriverBy::xpath('/html/body//ul/div/li'));

            $i = 0;
            do {
                $element = array_shift($elements);

                try {
                    var_dump('isLikerExist: wait until a:last-child');
                    $this->driver->wait(8)->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('a:last-child')));
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
                    $this->driver->action()->moveToElement($element);
                    $point = $element->getLocationOnScreenOnceScrolledIntoView();
                    $this->driver->getMouse()->mouseMove($element->getCoordinates(), $point->getX(), $point->getY());

                    sleep(rand(5, 6));
                    $newElements = $this->driver->findElements(WebDriverBy::xpath('/html/body//ul/div/li'));

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
                $this->driver->findElement(WebDriverBy::xpath('//a[contains(text(), "' . $likerIsLookingFor . '")]'));
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
     * @param int    $limit
     *
     * @return bool
     *
     * @throws HttpClientException
     */
    public function findCommentByCommentator(string $link, string $commentatorIsLookingFor, int $limit = self::LIMIT)
    {
        preg_match('/https:\/\/(www.)?((instagram|ig).(com|me)\/(p\/)?[0-9a-zA-Z-_]+)/', $link, $matches);

        list($code, $response) = $this->client->request('https://www.' . $matches[2] . '/?__a=1', 'POST');

        $comments = array_reverse($response['graphql']['shortcode_media']['edge_media_to_parent_comment']['edges']);

        if ($code !== 200) {
            return false;
        }

        foreach ($comments as $edge) {
            if ($commentatorIsLookingFor == $edge['node']['owner']['username']) {
                return $edge['node']['text'];
            }
        }

        return null;
    }

    /**
     * @param string $link
     * @param string $commentatorIsLookingFor
     * @param int    $limit
     *
     * @return bool
     * @throws TimeOutException
     */
    public function findCommentByCommentatorByWebDriver(string $link, string $commentatorIsLookingFor, int $limit = self::LIMIT)
    {
        $this->openTab();
        $this->driver->navigate()->to($link);

        try {
            $this
                ->driver
                ->wait(8)
                ->until(
                    WebDriverExpectedCondition::presenceOfElementLocated(
                        WebDriverBy::xpath('//*[@id="react-root"]/section/main/div/div/article/div[2]/div[1]/ul/li')
                    )
                );

            $elements = $this->driver->findElements(WebDriverBy::xpath(
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

                $this->driver->action()->moveToElement($loadMore);
                $point = $loadMore->getLocationOnScreenOnceScrolledIntoView();
                $this->driver->getMouse()->mouseMove($loadMore->getCoordinates(), $point->getX(), $point->getY())->click();
                sleep(rand(3, 4));
                $loadMore->click();
                $loadMore = null;
                $k++;

                $newElements = $this
                    ->driver
                    ->findElements(
                        WebDriverBy::xpath(
                            '//*[@id="react-root"]/section/main/div/div/article/div[2]/div[1]/ul/li'
                        )
                    );

                $elements = array_slice($newElements, 0, -($i-$k*2));

            }

            if ((count($elements) == 0 && $loadMore == null) || $i > $limit) {
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
     * @param int    $limit
     *
     * @return bool
     * @throws TimeOutException
     */
    public function findCommentByWebDriver(string $link, string $commentatorIsLookingFor, string $commentIsLookingFor, int $limit = self::LIMIT)
    {
        $this->openTab();
        $this->driver->navigate()->to($link);

        try {
            $this
                ->driver
                ->wait(8)
                ->until(
                    WebDriverExpectedCondition::presenceOfElementLocated(
                        WebDriverBy::xpath('//*[@id="react-root"]/section/main/div/div/article/div[2]/div[1]/ul/li')
                    )
                );

            $elements = $this->driver->findElements(WebDriverBy::xpath(
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

                $this->driver->action()->moveToElement($loadMore);
                $point = $loadMore->getLocationOnScreenOnceScrolledIntoView();
                $this->driver->getMouse()->mouseMove($loadMore->getCoordinates(), $point->getX(), $point->getY())->click();
                sleep(rand(3, 4));
                $loadMore->click();
                $loadMore = null;
                $k++;

                $newElements = $this
                    ->driver
                    ->findElements(
                        WebDriverBy::xpath(
                            '//*[@id="react-root"]/section/main/div/div/article/div[2]/div[1]/ul/li'
                        )
                    );

                $elements = array_slice($newElements, 0, -($i-$k*2));

            }

            if ((count($elements) == 0 && $loadMore == null) || $i > $limit) {
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
     * @param int    $limit
     *
     * @return bool
     *
     * @throws HttpClientException
     */
    public function findComment(string $link, string $commentatorIsLookingFor, string $commentIsLookingFor, int $limit = self::LIMIT)
    {
        preg_match('/https:\/\/(www.)?((instagram|ig).(com|me)\/(p\/)?[0-9a-zA-Z-_]+)/', $link, $matches);

        list($code, $response) = $this->client->request('https://www.' . $matches[2] . '/?__a=1', 'POST');

        $comments = array_reverse($response['graphql']['shortcode_media']['edge_media_to_parent_comment']['edges']);

        if ($code !== 200) {
            return false;
        }

        foreach ($comments as $edge) {
            $comment = \Normalizer::normalize($edge['node']['text']);
            if ($commentatorIsLookingFor == $edge['node']['owner']['username'] && $commentIsLookingFor == $comment) {
                return $edge['node']['text'];
            }
        }

        return null;
    }

    /**
     * @param string $link
     *
     * @return array
     *
     * @throws HttpClientException
     */
    public function getPostInfo(string $link)
    {
        list($code, $response) = $this->client->request($link . '/?__a=1', 'POST');

        return $response;
    }

    private function closeTab()
    {
        sleep(1);
        $this
            ->driver
            ->wait(5)
            ->until(
                WebDriverExpectedCondition::presenceOfElementLocated(
                    WebDriverBy::tagName('body')
                )
            );

        $body = $this->driver->findElement(WebDriverBy::tagName('body'));
        $this->driver->action()->moveToElement($body);
        $point = $body->getLocationOnScreenOnceScrolledIntoView();
        $this->driver->getMouse()->mouseMove($body->getCoordinates(), $point->getX(), $point->getY())->click();
        $body->sendKeys(WebDriverKeys::CONTROL . '+' . 'w');
        sleep(1);
    }

    private function openTab()
    {
        sleep(1);
        $this
            ->driver
            ->wait(5)
            ->until(
                WebDriverExpectedCondition::presenceOfElementLocated(
                    WebDriverBy::tagName('body')
                )
            );

        $body = $this->driver->findElement(WebDriverBy::tagName('body'));
        $this->driver->action()->moveToElement($body);
        $point = $body->getLocationOnScreenOnceScrolledIntoView();
        $this->driver->getMouse()->mouseMove($body->getCoordinates(), $point->getX(), $point->getY())->click();
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
     * @param string $link
     *
     * @return bool
     * @throws HttpClientException
     */
    public function isCommentable(string $link)
    {
        preg_match('/https:\/\/(www.)?((instagram|ig).(com|me)\/(p\/)?[0-9a-zA-Z-_]+)/', $link, $matches);

        list($code, $response) = $this->client->request('https://www.' . $matches[2] . '/?__a=1', 'POST');

        $isPostAvailable = $code !== 404;
        $isCommentsDisabled = (bool) $response['graphql']['shortcode_media']['comments_disabled'];
        $isProfileClosed = (bool) $response['graphql']['shortcode_media']['owner']['is_private'];

        return !$isCommentsDisabled && !$isProfileClosed && $isPostAvailable;
    }

    /**
     * @param string $link
     *
     * @return bool
     *
     * @throws TimeOutException
     */
    public function isPostDeleted(string $link)
    {
        $this->openTab();
        $this->driver->navigate()->to($link);

        try {
            $xpath = '//*[contains(text(), "Sorry, this page isn\'t available")]';

            $this
                ->driver
                ->wait(5)
                ->until(
                    WebDriverExpectedCondition::presenceOfElementLocated(
                        WebDriverBy::xpath($xpath)
                    )
                );

            $this->driver->findElement($xpath);

            $this->closeTab();

            return true;
        } catch (NoSuchElementException $e) {
            $this->closeTab();

            return false;
        }
    }

    /**
     * @return RemoteWebDriver
     */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * @param string $username
     *
     * @return array
     *
     * @throws HttpClientException
     * @throws \Exception
     */
    public function getUserInfo(string $username)
    {
        list($code, $response) = $this->client->request('https://www.instagram.com/' . $username . '/', 'GET', [], false);

        if ($code !== 200) {
            throw new \Exception();
        }

        return $response;
    }

    public function mass(string $url, $looking = false, $liking = false, $commenting = false, $following = false)
    {
        preg_match(self::USERNAME_PATTERN, $url, $matches);
        $username = $matches[2];

        if ($this->isUserProcessed($username)) {
            return false;
        }

        if ($this->driver->getCurrentURL() != $url) {
            $this->driver->get($url);
        }

        if ($following) {
            $this->follow();
        }

        if ($looking && $this->isStoriesExist()) {
            $this->watchStories();
        }

        if ($liking) {
            $this->like();
        }

        if ($commenting) {
            $this->comment();
        }

        $this->setUserProcessed($username);

        return true;
    }

    /**
     * @param string $username
     *
     * @return string
     */
    private function getRedisKey(string $username)
    {
        return sprintf(self::PROCESSED_USER_REDIS_KEY_PATTERN, $username);
    }

    /**
     * @param string $username
     *
     * @return bool
     */
    private function isUserProcessed(string $username)
    {
        return (bool) $this->redis->exists($this->getRedisKey($username));
    }

    /**
     * @param string $username
     *
     * @return bool
     */
    private function setUserProcessed(string $username)
    {
        return $this->redis->set($this->getRedisKey($username), true, 86400);
    }

    /**
     * @return bool
     */
    private function isStoriesExist()
    {
        sleep(1);

        $this
            ->driver
            ->executeScript("
                if (document.getElementsByTagName('canvas').item(0).getContext('2d').strokeStyle instanceof CanvasGradient) {
                    document.getElementsByTagName('html').item(0).setAttribute('stories-exist', true);
                } else {
                    document.getElementsByTagName('html').item(0).setAttribute('stories-exist', false);
                };
        ");

        sleep(1);

        return $this->driver->findElement(WebDriverBy::cssSelector('html'))->getAttribute('stories-exist') == "true";
    }

    private function watchStories()
    {
        // Открываем Stories
        $this->click(WebDriverBy::xpath('//span[contains(@role, "link")]'));

        // Ждем пока карусель сториз откроется
        $this->driver->wait(30)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector(
                '.carul'
            ))
        );

        return true;
    }

    private function like()
    {
        $this->click(WebDriverBy::xpath('//span[@aria-label="Like"]'));

        sleep(rand(5, 10));

        return true;
    }

    private function comment($text = '❤️')
    {
        $element = $this->click(WebDriverBy::cssSelector('textarea[autocorrect="off"]'));

        sleep(rand(5, 10));

        $element->sendKeys(str_repeat($text, rand(3, 10)));

        sleep(rand(1, 3));

        $element->sendKeys(WebDriverKeys::RETURN_KEY);

        return true;
    }

    private function follow()
    {
        return true;
    }

    /**
     * @param WebDriverBy $webDriverBy
     * @param int         $wait
     *
     * @return RemoteWebElement
     *
     * @throws NoSuchElementException
     * @throws TimeOutException
     */
    private function click(WebDriverBy $webDriverBy, int $wait = 30)
    {
        $this->driver->wait($wait)->until(WebDriverExpectedCondition::presenceOfElementLocated($webDriverBy));

        $element = $this->driver->findElement($webDriverBy);

        sleep(rand(1, 2));

        $element->getLocationOnScreenOnceScrolledIntoView();

        sleep(rand(1, 2));

        $this->driver->getMouse()->click($element->getCoordinates());

        sleep(rand(1, 2));

        return $element;
    }
}
