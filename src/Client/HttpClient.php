<?php

namespace xyz13\InstagramBundle\Client;

use Symfony\Component\HttpFoundation\Request;

class HttpClient
{
    /**
     * @param string $url
     * @param string $method
     * @param array  $data
     * @param bool   $decode
     *
     * @return array
     *
     * @throws HttpClientException
     */
    public function request(string $url, $method = Request::METHOD_GET, array $data = [], $decode = true)
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

        if ($decode) {
            $response = json_decode($message, true);
        } else {
            $response = $message;
        }

        return [$code, $response];
    }
}