<?php

namespace Carweb;

use Buzz\Message\RequestInterface;
use Buzz\Message\Response;
use Buzz\Exception\ClientException;
use Carweb\Cache\CacheInterface;
use Carweb\Converter\ConverterInterface;
use Carweb\Converter\DefaultConverter;
use Carweb\Exception\ApiException;
use Carweb\Exception\ValidationException;
use Carweb\Validator\VRM;

class Consumer
{
    /**
     * API path on the endpoint
     */
    const API_PATH = 'CarweBVrrB2Bproxy/carwebVrrWebService.asmx';

    /**
     * Get vehicle by vrm endpoint name
     */
    const API_METHOD_GET_VEHICLE_BY_VRM = 'strB2BGetVehicleByVRM';

    /**
     * Get vehicle by vin endpoint name
     */
    const API_METHOD_GET_VEHICLE_BY_VIN = 'strB2BGetVehicleByVIN';

    /**
     * Error code used in case of missing vehicle data
     */
    const ERROR_CODE_NO_VEHICLES_RETURNED = 1000;

    /**
     * @var array
     */
    protected $api_endpoints = array(
        'https://www2.carwebuk.com',
        'https://www3.carwebuk.com',
        'https://www1.carwebuk.com',
    );

    /**
     * @var \Buzz\Browser
     */
    protected $client;

    /**
     * @var string
     */
    protected $strUserName;

    /**
     * @var string
     */
    protected $strPassword;

    /**
     * @var string
     */
    protected $strKey1;

    /**
     * @var null|\Carweb\Cache\CacheInterface
     */
    private $cache;

    /**
     * @var bool
     */
    private $validate = true;

    /**
     * @var array
     */
    protected $converters = array();

    /**
     * @var bool
     */
    private $failover = true;

    /**
     * Denotes if the response has been taken from cache
     *
     * @var bool
     */
    private $cachedResponse = false;

    /**
     * Constructor
     *
     * @param $client
     * @param string $strUserName
     * @param string $strPassword
     * @param string $strKey1
     * @param string $web_version
     * @param null|\Carweb\Cache\CacheInterface $cache
     * @param bool $validate
     * @param bool $failover
     */
    public function __construct(
        $client,
        $strUserName,
        $strPassword,
        $strKey1,
        $web_version,
        CacheInterface $cache = null,
        $validate = true,
        $failover = true
    ) {
        $this->client = $client;
        $this->strUserName = $strUserName;
        $this->strPassword = $strPassword;
        $this->strKey1 = $strKey1;
        $this->cache = $cache;
        $this->validate = $validate;
        $this->web_version = $web_version;
        $this->failover = $failover;
    }

    /**
     * Proxy method for strB2BGetVehicleByVRM
     *
     * @param string $vrm
     * @param string $strClientRef
     * @param string $strClientDescription
     * @return mixed|void
     */
    public function findByVRM($vrm, $strClientRef = 'default client', $strClientDescription = 'Carweb PHP Library')
    {
        $vrm = strtoupper(preg_replace('/\s+/', '', $vrm));

        $validator = new VRM();
        if( ! $validator->isValid($vrm) && $this->validate)
            throw new ValidationException('Invalid UK VRM');

        $cache_key = sprintf('%s.%s', self::API_METHOD_GET_VEHICLE_BY_VRM, $vrm);

        $converter = $this->getConverter(self::API_METHOD_GET_VEHICLE_BY_VRM);

        if($this->isCached($cache_key))
        {
            $content = $this->getCached($cache_key);
            return $converter->convert($content);
        }

        $input = array(
            'strUserName' => $this->strUserName,
            'strPassword' => $this->strPassword,
            'strKey1' => $this->strKey1,
            'strVersion' => $this->web_version,
            'strVRM' => $vrm,
            'strClientRef' => $strClientRef,
            'strClientDescription' => $strClientDescription
        );

        $content = $this->call(self::API_METHOD_GET_VEHICLE_BY_VRM, RequestInterface::METHOD_GET, $input);

        $this->setCached($cache_key, $content);

        return $converter->convert($content);
    }

    /**
     * Proxy method for strB2BGetVehicleByVRM
     *
     * @param string $vin
     * @param string $strClientRef
     * @param string $strClientDescription
     * @return mixed|void
     */
    public function findByVIN($vin, $strClientRef = 'default client', $strClientDescription = 'Carweb PHP Library')
    {
        $vin = strtoupper(preg_replace('/\s+/', '', $vin));

        $cache_key = sprintf('%s.%s', self::API_METHOD_GET_VEHICLE_BY_VIN, $vin);

        $converter = $this->getConverter(self::API_METHOD_GET_VEHICLE_BY_VIN);

        if($this->isCached($cache_key))
        {
            $content = $this->getCached($cache_key);
            return $converter->convert($content);
        }

        $input = array(
            'strUserName' => $this->strUserName,
            'strPassword' => $this->strPassword,
            'strKey1' => $this->strKey1,
            'strVersion' => $this->web_version,
            'strVIN' => $vin,
            'strClientRef' => $strClientRef,
            'strClientDescription' => $strClientDescription
        );

        $content = $this->call(self::API_METHOD_GET_VEHICLE_BY_VIN, RequestInterface::METHOD_GET, $input);

        $this->setCached($cache_key, $content);

        return $converter->convert($content);
    }

    /**
     * @param string $api_method
     * @param string $http_method
     * @param array $query_string
     * @param array $headers
     * @param string $content
     * @return string
     * @throws Exception\ApiException
     */
    public function call($api_method, $http_method = RequestInterface::METHOD_GET, array $query_string = array(), $headers = array(), $content = '')
    {
        foreach($this->api_endpoints as $endpoint) {
            $url = $this->getUrlFromEndpoint($endpoint, $api_method, $query_string);
            /** @var Response $response */
            try {
                $response = $this->client->call($url, $http_method, $headers, $content);
                if ($response->isSuccessful()) {
                    $this->hasErrors($response->getContent());
                    return $response->getContent();
                }

                // should we try once again?
                if (!$this->failover) {
                    $this->handleException($response);
                }
            } catch(ClientException $e) {
                // should we try once again?
                if (!$this->failover) {
                    throw $e;
                }
            }
        }

        //we give up
        throw new ApiException('Could not connect to CarWeb');
    }

    /**
     * Gets converted obj for given API method
     *
     * @param string $api_method
     * @return \Carweb\Converter\ConverterInterface
     */
    public function getConverter($api_method)
    {
        if (isset($this->converters[$api_method])) {
            return $this->converters[$api_method];
        } else {
            return new DefaultConverter();
        }
    }

    /**
     * Sets converter object for given API method
     *
     * @param $api_method
     * @param ConverterInterface $converter
     * @throws \InvalidArgumentException
     */
    public function setConverter($api_method, $converter)
    {
        if( ! $converter instanceof ConverterInterface)
            throw new \InvalidArgumentException('$converter must be instance of ConverterInterface');

        $this->converters[$api_method] = $converter;
    }

    /**
     * @return bool
     */
    public function isCachedResponse()
    {
        return $this->cachedResponse;
    }

    /**
     * @param Response $response
     * @throws ApiException
     */
    protected function handleException(Response $response)
    {
        throw new ApiException($response->getContent(), $response->getStatusCode());
    }

    /**
     * @param string $xml_string
     * @return bool
     * @throws Exception\ApiException
     */
    protected function hasErrors($xml_string)
    {
        $doc = new \DOMDocument();
        $doc->loadXML($xml_string);

        $xpath = new \DOMXPath($doc);

        $query = '/VRRError/DataArea/Error/Details';

        $entries = $xpath->query($query);

        if($entries->length)
        {
            $error = array();
            foreach($entries as $entry)
                foreach($entry->childNodes as $node)
                    if($node->nodeName != '#text')
                        $error[$node->nodeName] = $node->nodeValue;

            if ((int)$error['ErrorCode'] === self::ERROR_CODE_NO_VEHICLES_RETURNED) {
                return false;
            }

            throw new ApiException($error['ErrorDescription'],$error['ErrorCode']);
        }

        return false;
    }

    /**
     * Cache proxy
     *
     * @param string $key
     * @return bool
     */
    protected function isCached($key)
    {
        if($this->cache) {
            return $this->cache->has($key);
        }
        else {
            return false;
        }
    }

    /**
     * Cache proxy
     *
     * @param string $key
     * @return null|string
     */
    protected function getCached($key)
    {
        if ($this->cache) {
            $this->cachedResponse = true;
            return $this->cache->get($key);
        } else {
            return null;
        }
    }

    /**
     * Cache proxy
     *
     * @param $key
     * @param $value
     * @return void
     */
    protected function setCached($key, $value)
    {
        $this->cachedResponse = false;
        if ($this->cache) {
            $this->cache->save($key, $value);
        }
    }

    /**
     * Build url to call CarWeb
     *
     * @param string $endpoint
     * @param string $apiMethod
     * @param string $queryString
     * @return string
     */
    protected function getUrlFromEndpoint($endpoint, $apiMethod, $queryString)
    {
        return sprintf('%s/%s/%s?%s', $endpoint, self::API_PATH, $apiMethod, http_build_query($queryString));
    }
}