<?php

namespace Pixelarbeit\Webware;

use Exception;
use Pixelarbeit\Webware\Classes\ServicePass;
use Pixelarbeit\Webware\Exceptions\InvalidResponseException;
use Pixelarbeit\Webware\Exceptions\ConnectionException;



class Api
{

    private $host;
    private $requestId = 1;
    private $servicePass = null;
    private $sessionToken = null;

    public $debug = false;



    public function __construct($host)
    {
        $this->host = $host;
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

        $response = $this->sendRequest($url);

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

        $response = $this->sendRequest($url, $headers);

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

        $response = $this->sendRequest($url, $headers);

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

        $response = $this->sendRequest($url, $headers);

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
        return $this->getResults($name . '.GET', $params);
    }



    /**
     * Send a put/update request
     * @param  string $name   Resource/table name
     * @param  array  $params Params for request
     * @return array          Result
     */
    public function put($name, $params = [])
    {
        return $this->getResults($name . '.PUT', $params);
    }



    /**
     * Send an insert request
     * @param  string $name   Resource/table name
     * @param  array  $params Params for request
     * @return array          Result
     */
    public function insert($name, $params = [])
    {
        return $this->getResults($name . '.INSERT', $params);
    }


    /**
     * Send a delete request
     * @param  string $name   Resource/table name
     * @param  array  $params Params for request
     * @return array          Result
     */
    public function delete($name, $params = [])
    {
        return $this->getResults($name . '.DELETE', $params);
    }



    /**
     * Returns results from given node
     * @param  string $nodeName Name of node/data table
     * @return array/object       List of entries
     */
    public function getResults($name, $params = [])
    {
        $url = $this->host . '/WWSVC/EXECJSON';
        $data = $this->createAuthArray();

        $data['WWSVC_FUNCTION'] = [
            'FUNCTIONNAME' => $name,
            'PARAMETER' => $params
        ];

        $json = json_encode($data);
        $headers = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json)
        ];


        return $this->sendRequest($url, $headers, $json, 'PUT');
    }



    /**
     * Creates WWSVC headers for service authentication
     * @return array Headers
     */
    private function createAuthHeaders()
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
    private function createAuthArray()
    {
        $this->requireServicePass();

        $time = time();

        return [
            'WWSVC_PASSINFO' => [
                'SERVICEPASS' => $this->servicePass->id,
                'APPHASH' => $this->createRequestHash($time),
                'TIMESTAMP' => strval($time),
                'REQUESTID' => strval($this->currentRequestId()),
                'GET_RESULT_MAX_LINES' => 10000
            ]
        ];
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



    /**
     * Sending request to given url and parsing JSON response.
     * Throwing exception on unvalid response
     * @param  string $url Webservice request url
     * @return object      response as JSON object
     */
    private function sendRequest($url, $headers = [], $data = null, $method = 'GET')
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLINFO_HEADER_OUT , $this->debug);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        //

        $response = curl_exec($ch);

        if ($this->debug == true) {
            echo '<pre>';
            echo "<strong>SENT HEADERS:</strong><br>";
            print(curl_getinfo($ch)['request_header']) . '<br>';
            echo "<strong>SENT PAYLOAD:</strong><br>";
            print($data) . '<br><br>';
            echo "<strong>RESPONSE:</strong><br>";
            print($response) . '<br><br>';
            echo '</pre>';
        }

        if (curl_errno($ch)) {
            throw new ConnectionException(curl_error($ch), curl_errno($ch));
        }

        $json = json_decode($response);
        if ($json === null) {
            throw new InvalidResponseException("No valid JSON", 1);
        }

        return $json;
    }



    /*    SETTERS & GETTERS */
    public function setServicePass($servicePass) {
        $this->servicePass = $servicePass;
    }
}
