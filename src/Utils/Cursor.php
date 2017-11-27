<?php

namespace Pixelarbeit\Webware\Utils;



class Cursor
{
    private $api;
    private $endpoint;
    private $params;

    private $token = 'CREATE';



    public function __construct($api, $endpoint, $params)
    {
        $this->api = $api;
        $this->endpoint = $endpoint;
        $this->params = $params;
    }



    /**
     * Get next cursor response.
     * Returns false when done
     */
    public function next()
    {
        if ($this->token === 'CLOSED') {
            return false;
        }

        return $this->request();
    }



    /**
     * Execute cursor request
     */
    private function request()
    {
        $url = $this->api->getHost() . '/WWSVC/EXECJSON';
        $params = $this->api->createParamsArray($this->params);

        $data = $this->api->createAuthArray();
        $data['WWSVC_PASSINFO']['GET_WWSVC_CURSOR'] = $this->token;
        $data['WWSVC_FUNCTION'] = [
            'FUNCTIONNAME' => strtoupper($this->endpoint),
            'PARAMETER' => $params
        ];

        list($headers, $json) = $this->api->httpClient->request('PUT', $url, $data);

        if (isset($headers['WWSVC-CURSOR'])) {
            $this->token = $headers['WWSVC-CURSOR'];
        }

        // Handles buggy behaviour if first request is under limit.
        // No CLOSED statement is returned.
        if ($json->COMRESULT->STATUS == 501 && $json->COMRESULT->INFO3 == 'CLOSED') {
            return false;
        }

        return $json;
    }
}
