<?php

namespace Pixelarbeit\Webware;

use Exception;
use Pixelarbeit\Webware\Utils\Cursor;
use Pixelarbeit\Webware\Utils\ServicePass;
use Pixelarbeit\Webware\Utils\HttpJsonClient;
use Pixelarbeit\Webware\Exceptions\InvalidResponseException;
use Pixelarbeit\Webware\Exceptions\ConnectionException;



class Api
{

    private $host;
    private $requestId = 1;
    private $servicePass = null;
    private $sessionToken = null;

    public $debug = false;
    public $limit = 500;



    public function __construct($host)
    {
        $this->host = $host;
        $this->httpClient = new HttpJsonClient();
    }



    /**
     * Registers a servicepass
     * @param  string $makerId  Maker-Hash
     * @param  string $appId    App-Hash
     * @param  int    $accessId Access-ID
     * @return Classes\ServicePass           ServicePass
     */
    public function register($makerId, $appId, $accessId)
    {
        $url = $this->host . '/WWSVC/WWSERVICE/REGISTER/'
                    . $makerId . '/' . $appId . '/' . $accessId;

        $response = $this->httpClient->get($url);

        if (in_array($response->COMRESULT->STATUS, [200, 202]) === false) {
            throw new Exception("Error registering service pass: " . $response->COMRESULT->INFO, 1);
        }

        $this->servicePass = ServicePass::createFromObject($response->SERVICEPASS);
        return $this->servicePass;
    }



    /**
     * Validates the given service pass
     * @param  Classes\ServicePass $servicePass ServicePass to check
     * @return boolean                          true if pass is ready, false else
     */
    public function validate(Classes\ServicePass $servicePass)
    {
        $url = $this->host . '/WWSVC/WWSERVICE/VALIDATE/' . $servicePass->id;
        $headers = $this->createAuthHeaders();

        $response = $this->httpClient->get($url, $headers);

        if ($response->COMRESULT->STATUS !== 200) {
            return false;
        }

        return true;
    }



    /**
     * Start a webservice session with given credentials
     * @param  string $user     Username
     * @param  string $password Password
     * @return string           Session Token
     */
    public function connect($user, $password)
    {
        $url = $this->host . '/WWSVC/WWSERVICE/CONNECT/' . $this->servicePass->id
                . '/' . $user . '/' . $password;

        $headers = $this->createAuthHeaders();

        $response = $this->httpClient->get($url, $headers);

        if ($response->COMRESULT->STATUS === 401) {
            throw new InvalidArgumentException("Invalid credentials" . $response->COMRESULT->INFO, 1);
        }

        if ($response->COMRESULT->STATUS === 406) {
            throw new InvalidArgumentException("Invalid SessionPass: " . $response->COMRESULT->INFO, 1);
        }

        $this->sessionToken = $response->SESSIONTOKEN->WWSVC_SESSION_TOKEN;

        return $this->sessionToken;
    }



    /**
     * Start a webservice session with given credentials
     * @param  string $user     Username
     * @param  string $password Password
     * @return string           Session Token
     */
    public function close($user, $password)
    {
        $url = $this->host . '/WWSVC/WWSERVICE/CLOSE/' . $this->servicePass->id;
        $headers = $this->createAuthHeaders();

        $response = $this->httpClient->get($url, $headers);

        if ($response->COMRESULT->STATUS === 406) {
            throw new Exception("Invalid SessionPass: " . $response->COMRESULT->INFO, 1);
        }

        $this->sessionToken = null;

        return true;
    }



    /**
     * Send a get request
     * @param  string $name   Resource/table name
     * @param  array  $params Params for request
     * @return array          Result
     */
    public function get($name, $params = [])
    {
        return $this->execJson($name . '.GET', $params);
    }



    /**
     * Send a put/update request
     * @param  string $name   Resource/table name
     * @param  array  $params Params for request
     * @return array          Result
     */
    public function put($name, $params = [])
    {
        return $this->execJson($name . '.PUT', $params);
    }



    /**
     * Send an insert request
     * @param  string $name   Resource/table name
     * @param  array  $params Params for request
     * @return array          Result
     */
    public function insert($name, $params = [])
    {
        return $this->execJson($name . '.INSERT', $params);
    }


    /**
     * Send a delete request
     * @param  string $name   Resource/table name
     * @param  array  $params Params for request
     * @return array          Result
     */
    public function delete($name, $params = [])
    {
        return $this->execJson($name . '.DELETE', $params);
    }



    /**
     * Returns results from given node
     * @param  string $nodeName Name of node/data table
     * @return array/object       List of entries
     */
    public function execJson($name, $params = [])
    {
        $url = $this->host . '/WWSVC/EXECJSON';
        $params = $this->createParamsArray($params);

        $data = $this->createAuthArray();

        $data['WWSVC_FUNCTION'] = [
            'FUNCTIONNAME' => strtoupper($name),
            'PARAMETER' => $params
        ];

        list($headers, $json) = $this->httpClient->request('PUT', $url, $data);
        return $json;
    }



    /**
     * Creates and returns a cursor.
     * @param  string $name   name of endpoint
     * @param  array  $params params for request
     * @return Cursor         cursor
     */
    public function createCursor($name, $params = [])
    {
        return new Cursor($this, $name, $params);
    }



    /**
     * Send a bulk get request
     * @param  string $name   Resource/table name
     * @param  array  $items  Items
     * @return array          Result
     */
    public function bulkGet($name, $items = [])
    {
        return $this->bulkExecJson($name . '.GET', $items);
    }



    /**
     * Send a bulk update/put request
     * @param  string $name   Resource/table name
     * @param  array  $items  Items
     * @return array          Result
     */
    public function bulkPut($name, $items = [])
    {
        return $this->bulkExecJson($name . '.PUT', $items);
    }



    /**
     * Send a bulk insert request
     * @param  string $name   Resource/table name
     * @param  array  $items  Items
     * @return array          Result
     */
    public function bulkInsert($name, $items = [])
    {
        return $this->bulkExecJson($name . '.INSERT', $items);
    }



    /**
     * Send a bulk delete request
     * @param  string $name   Resource/table name
     * @param  array  $items  Items
     * @return array          Result
     */
    public function bulkDelete($name, $items = [])
    {
        return $this->bulkExecJson($name . '.DELETE', $items);
    }



    /**
     * Send multiple request in parallel to handle bulk action.
     * @param  String $name  Endpoint
     * @param  array  $items Items to send
     * @return array        Array with responses for each request
     */
    public function bulkExecJson($name, $items = [])
    {
        $url = $this->host . '/WWSVC/EXECJSON';

        $data = $this->createAuthArray();
        $data['WWSVC_FUNCTION']['FUNCTIONNAME'] = strtoupper($name);

        $this->httpClient->initBulkRequest();

        foreach ($items as $item) {
            $params = $this->createParamsArray($item);
            $data['WWSVC_FUNCTION']['PARAMETER'] = $params;

            $this->httpClient->addBulkRequest('PUT', $url, $data);
        }

        return $this->httpClient->executeBulkRequest();
    }



    /**
     * Creates WWSVC headers for service authentication
     * @return array Headers
     */
    public function createAuthHeaders()
    {
        $this->requireServicePass();

        $time = time();

        return [
            'WWSVC-HASH: ' . $this->createRequestHash($time),
            'WWSVC-REQID: ' . $this->currentRequestId(),
            'WWSVC-TS: ' . $time,
            'WWSVC-SESSION-TOKEN: ' . $this->sessionToken
        ];
    }



    /**
     * Creates WWSVC headers for service authentication
     * @return array Headers
     */
    public function createAuthArray()
    {
        $this->requireServicePass();

        $time = time();

        return [
            'WWSVC_PASSINFO' => [
                'SERVICEPASS' => $this->servicePass->id,
                'APPHASH' => $this->createRequestHash($time),
                'TIMESTAMP' => strval($time),
                'REQUESTID' => strval($this->currentRequestId()),
                'GET_RESULT_MAX_LINES' => $this->limit
            ]
        ];
    }



    /**
     * Maps key-value params to webware param arrays
     * @return array Headers
     */
    public function createParamsArray($params)
    {
        $result = [];
        $i = 1;

        foreach ($params as $key => $value) {
            $result[] = [
                'PNAME' => strtoupper($key),
                'PCONTENT' => $value,
                'POSITION' => $i++
            ];
        }

        return $result;
    }



    /**
     * Checks whether ServicePass is set and throws exception if not
     */
    private function requireServicePass()
    {
        if ($this->servicePass == null) {
            throw new IllegalStateException("No ServicePass was set", 1);
        }
    }



    /**
     * Creates authentication hash for requests
     * @param  int $timestamp timestamp of request
     * @return string            md5 hash
     */
    private function createRequestHash($timestamp)
    {
        return md5($this->servicePass->appSecret . $timestamp);
    }



    /**
     * Returns an incrementing request ID
     * @return int Request ID
     */
    private function currentRequestId()
    {
        return $this->requestId++;
    }



    /*    SETTERS & GETTERS */
    public function setServicePass($servicePass) {
        $this->servicePass = $servicePass;
    }

    public function getHost()
    {
        return $this->host;
    }
}
