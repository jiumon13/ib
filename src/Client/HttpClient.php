<?php

namespace xyz13\InstagramBundle\Client;

use Symfony\Component\HttpFoundation\Request;

class HttpClient
{
    /**
     * @param        $url
     * @param string $method
     * @param array  $data
     *
     * @return array
     *
     * @throws HttpClientException
     * @throws \Exception
     */
    public function request($url, $method = Request::METHOD_GET, array $data = [])
    {
        $query = http_build_query($data);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 5);

        switch ($method) {
            case Request::METHOD_GET:
                curl_setopt($curl, CURLOPT_URL, sprintf('%s?%s', $url, $query));
                break;

            case Request::METHOD_POST:
                curl_setopt($curl, CURLOPT_URL, sprintf('%s', $url));
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $query);
                break;

            case Request::METHOD_DELETE:
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, Request::METHOD_DELETE);
                break;

            default:
                throw new HttpClientException('Bad request method');
        }

        $message = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        $response = json_decode($message, true);

        return [$code, $response];
    }
}