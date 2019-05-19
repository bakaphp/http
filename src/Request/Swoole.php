<?php
// +----------------------------------------------------------------------
// | SwooleRequest.php [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016-2017 limingxinleo All rights reserved.
// +----------------------------------------------------------------------
// | Author: limx <715557344@qq.com> <https://github.com/limingxinleo>
// +----------------------------------------------------------------------

namespace Baka\Http\Request;

use Phalcon\DiInterface;
use Phalcon\Events\Manager;
use Phalcon\FilterInterface;
use Phalcon\Http\RequestInterface;
use Phalcon\Di\InjectionAwareInterface;
use Phalcon\Http\Request\File;
use Phalcon\Text;
use swoole_http_request;
use Exception;
use Phalcon\Di\FactoryDefault;

/**
 * Class SwooleRequest.
 *
 * To use Swoole Server with Phalcon we need to overwrite the Phalcon Request Object to use swoole Respnose object
 * Since swoole is our server he is the one who get all our _GET , _FILES, _POST , _PUT request and we need to parse that info
 * to make our phalcon project work
 *
 * @package Gewaer\Http
 *
 * @property \Phalcon\Di $di
 */
class Swoole implements RequestInterface, InjectionAwareInterface
{
    protected $_dependencyInjector;

    protected $_httpMethodParameterOverride = false;

    protected $_filter;

    protected $_putCache;

    protected $_strictHostCheck = false;

    protected $_files;

    protected $_rawBody;

    protected $headers;

    protected $server;

    protected $get;

    protected $post;

    protected $cookies;

    protected $files;

    protected $swooleRequest;

    /**
     * Init the object with Swoole reqeust.
     *
     * @param swoole_http_request $request
     * @return void
     */
    public function init(swoole_http_request $request): void
    {
        $this->swooleRequest = $request;
        $this->headers = [];
        $this->server = [];

        $this->get = isset($request->get) ? $request->get : [];
        $this->post = isset($request->post) ? $request->post : [];
        $this->cookies = isset($request->cookie) ? $request->cookie : [];
        $this->files = isset($request->files) ? $request->files : [];
        $this->_rawBody = $request->rawContent();

        //iterate header
        $this->setGlobalHeaders($request->header);
        $this->setGlobalServers($request->server);

        //iterate server

        /** @var Cookies $cookies */
        //$cookies = FactoryDefault::getDefault()->getCookies();
        //  $cookies->setSwooleCookies($this->cookies);
    }

    /**
     * Set global headers.
     *
     * @param array $headers
     * @return void
     */
    private function setGlobalHeaders(array $headers): void
    {
        foreach ($headers as $key => $val) {
            $key = strtoupper(str_replace(['-'], '_', $key));
            $this->headers[$key] = $val;
            $this->server[$key] = $val;
        }
    }

    /**
     * Set global Servers.
     *
     * @param array $servers
     * @return void
     */
    private function setGlobalServers(array $servers): void
    {
        foreach ($servers as $key => $val) {
            $key = strtoupper(str_replace(['-'], '_', $key));
            $this->server[$key] = $val;
        }
    }

    /**
     * Set Di.
     *
     * @param DiInterface $dependencyInjector
     * @return void
     */
    public function setDI(DiInterface $dependencyInjector)
    {
        $this->_dependencyInjector = $dependencyInjector;
    }

    /**
     * Get Di.
     *
     * @return void
     */
    public function getDI()
    {
        return $this->_dependencyInjector;
    }

    /**
     * Access to REQUEST.
     *
     * @param string $name
     * @param string $filters
     * @param string $defaultValue
     * @param boolean $notAllowEmpty
     * @param boolean $noRecursive
     * @return array|string
     */
    public function get($name = null, $filters = null, $defaultValue = null, $notAllowEmpty = false, $noRecursive = false)
    {
        $source = array_merge($this->get, $this->post);
        return $this->getHelper($source, $name, $filters, $defaultValue, $notAllowEmpty, $noRecursive);
    }

    /**
     * Acces to Post.
     *
     * @param string $name
     * @param string $filters
     * @param string $defaultValue
     * @param boolean $notAllowEmpty
     * @param boolean $noRecursive
     * @return array|string
     */
    public function getPost($name = null, $filters = null, $defaultValue = null, $notAllowEmpty = false, $noRecursive = false)
    {
        $source = $this->post;
        return $this->getHelper($source, $name, $filters, $defaultValue, $notAllowEmpty, $noRecursive);
    }

    /**
     * Access to GET.
     *
     * @param string $name
     * @param string $filters
     * @param string $defaultValue
     * @param boolean $notAllowEmpty
     * @param boolean $noRecursive
     * @return array|string
     */
    public function getQuery($name = null, $filters = null, $defaultValue = null, $notAllowEmpty = false, $noRecursive = false)
    {
        $source = $this->get;
        return $this->getHelper($source, $name, $filters, $defaultValue, $notAllowEmpty, $noRecursive);
    }

    /**
     * Get _SERVER.
     *
     * @param string $name
     * @return string|null
     */
    public function getServer($name)
    {
        $name = strtoupper(str_replace(['-'], '_', $name));
        if (isset($this->server[$name])) {
            return $this->server[$name];
        }

        return null;
    }

    /**
     * Get _PUT.
     *
     * @param string $name
     * @param string $filters
     * @param string $defaultValue
     * @param boolean $notAllowEmpty
     * @param boolean $noRecursive
     * @return array|string
     */
    public function getPut($name = null, $filters = null, $defaultValue = null, $notAllowEmpty = false, $noRecursive = false)
    {
        $put = $this->_putCache;

        if (empty($put)) {
            json_decode($this->getRawBody());
            //return (bool ) (json_last_error() == JSON_ERROR_NONE);
            //confirm is a true json reponse
            if ((json_last_error() == JSON_ERROR_NONE)) {
                parse_str($this->getRawBody(), $put);
            } else {
                $put = $this->getJsonRawBody(true);
            }
            $this->_putCache = $put;
        }

        return $this->getHelper($put, $name, $filters, $defaultValue, $notAllowEmpty, $noRecursive);
    }

    /**
     * Has.
     *
     * @param string $name
     * @return boolean
     */
    public function has($name)
    {
        $source = array_merge($this->get, $this->post);
        return isset($source[$name]);
    }

    /**
     * Has Post.
     *
     * @param string $name
     * @return boolean
     */
    public function hasPost($name)
    {
        return isset($this->post[$name]);
    }

    /**
     * Has Put.
     *
     * @param string $name
     * @return boolean
     */
    public function hasPut($name)
    {
        $put = $this->getPut();

        return isset($put[$name]);
    }

    /**
     * Has GET.
     *
     * @param string $name
     * @return boolean
     */
    public function hasQuery($name)
    {
        return isset($this->get[$name]);
    }

    /**
     * Has SERVER.
     *
     * @param string $name
     * @return boolean
     */
    public function hasServer($name)
    {
        $name = strtoupper(str_replace(['-'], '_', $name));

        return isset($this->server[$name]);
    }

    /**
     * Has HEADER.
     *
     * @param string $name
     * @return boolean
     */
    public function hasHeader($header)
    {
        if ($this->hasServer($header)) {
            return true;
        }
        if ($this->hasServer('HTTP_' . $header)) {
            return true;
        }
        return false;
    }

    /**
     * Get Header.
     *
     * @param string $name
     * @return string|void
     */
    public function getHeader($header)
    {
        $header = $this->getServer($header);
        if (isset($header)) {
            return $header;
        }

        $header = $this->getServer('HTTP_' . $header);
        if (isset($header)) {
            return $header;
        }

        return '';
    }

    /**
     * Get Schema.
     *
     * @return string
     */
    public function getScheme()
    {
        $https = $this->getServer('HTTPS');
        if ($https && $https != 'off') {
            return 'https';
        }

        return 'http';
    }

    /**
     * Is ajax.
     *
     * @return boolean
     */
    public function isAjax()
    {
        return $this->getServer('HTTP_X_REQUESTED_WITH') === 'XMLHttpRequest';
    }

    /**
     * is Soap.
     *
     * @return boolean
     */
    public function isSoap()
    {
        if ($this->hasServer('HTTP_SOAPACTION')) {
            return true;
        }

        $contentType = $this->getContentType();
        if (!empty($contentType)) {
            return (bool) strpos($contentType, 'application/soap+xml') !== false;
        }

        return false;
    }

    /**
     * is Soap.
     *
     * @return boolean
     */
    public function isSoapRequested()
    {
        return $this->isSoap();
    }

    /**
     * is HTTPS.
     *
     * @return boolean
     */
    public function isSecure()
    {
        return $this->getScheme() === 'https';
    }

    /**
     * is HTTPS.
     *
     * @return boolean
     */
    public function isSecureRequest()
    {
        return $this->isSecure();
    }

    /**
     * get RAW.
     *
     * @return string
     */
    public function getRawBody()
    {
        return $this->_rawBody;
    }

    /**
     * Get json.
     *
     * @param boolean $associative
     * @return void|string
     */
    public function getJsonRawBody($associative = false)
    {
        $rawBody = $this->getRawBody();
        if (!is_string($rawBody)) {
            return false;
        }

        return json_decode($rawBody, $associative);
    }

    /**
     * Get servers addres.
     *
     * @return string
     */
    public function getServerAddress()
    {
        $serverAddr = $this->getServer('SERVER_ADDR');
        if ($serverAddr) {
            return $serverAddr;
        }

        return gethostbyname('localhost');
    }

    /**
     * Get server name.
     *
     * @return string
     */
    public function getServerName()
    {
        $serverName = $this->getServer('SERVER_NAME');
        if ($serverName) {
            return $serverName;
        }

        return 'localhost';
    }

    /**
     * Get https hosts.
     *
     * @return string
     */
    public function getHttpHost()
    {
        $strict = $this->_strictHostCheck;

        /**
         * Get the server name from $_SERVER["HTTP_HOST"].
         */
        $host = $this->getServer('HTTP_HOST');
        if (!$host) {
            /**
             * Get the server name from $_SERVER["SERVER_NAME"].
             */
            $host = $this->getServer('SERVER_NAME');
            if (!$host) {
                /**
                 * Get the server address from $_SERVER["SERVER_ADDR"].
                 */
                $host = $this->getServer('SERVER_ADDR');
            }
        }

        if ($host && $strict) {
            /**
             * Cleanup. Force lowercase as per RFC 952/2181.
             */
            $host = strtolower(trim($host));
            if (strpos($host, ':') !== false) {
                $host = preg_replace('/:[[:digit:]]+$/', '', $host);
            }

            /**
             * Host may contain only the ASCII letters 'a' through 'z' (in a case-insensitive manner),
             * the digits '0' through '9', and the hyphen ('-') as per RFC 952/2181.
             */
            if ('' !== preg_replace("/[a-z0-9-]+\.?/", '', $host)) {
                throw new \UnexpectedValueException('Invalid host ' . $host);
            }
        }

        return (string) $host;
    }

    /**
     * Sets if the `Request::getHttpHost` method must be use strict validation of host name or not.
     */
    public function setStrictHostCheck($flag = true)
    {
        $this->_strictHostCheck = $flag;

        return $this;
    }

    /**
     * Checks if the `Request::getHttpHost` method will be use strict validation of host name or not.
     */
    public function isStrictHostCheck()
    {
        return $this->_strictHostCheck;
    }

    /**
     * Get port.
     *
     * @return int
     */
    public function getPort()
    {
        /**
         * Get the server name from $_SERVER["HTTP_HOST"].
         */
        $host = $this->getServer('HTTP_HOST');
        if ($host) {
            if (strpos($host, ':') !== false) {
                $pos = strrpos($host, ':');

                if (false !== $pos) {
                    return (int)substr($host, $pos + 1);
                }

                return 'https' === $this->getScheme() ? 443 : 80;
            }
        }
        return (int) $this->getServer('SERVER_PORT');
    }

    /**
     * Gets HTTP URI which request has been made.
     */
    public function getURI()
    {
        $requestURI = $this->getServer('request_uri'); //$this->getServer('REQUEST_URI') == $this->getQuery('_url') ? $this->getServer('REQUEST_URI') : $this->getQuery('_url');
        if ($requestURI) {
            return $requestURI;
        }

        return '';
    }

    /**
     * Get client ip.
     *
     * @param boolean $trustForwardedHeader
     * @return string|boolean
     */
    public function getClientAddress($trustForwardedHeader = true)
    {
        $address = null;

        /**
         * Proxies uses this IP.
         */
        if ($trustForwardedHeader) {
            $address = $this->getServer('X_FORWARDED_FOR');
            if ($address === null) {
                $address = $this->getServer('X_REAL_IP');
            }
        }

        if ($address === null) {
            $address = $this->getServer('REMOTE_ADDR');
        }

        if (is_string($address)) {
            if (strpos($address, ',') !== false) {
                /**
                 * The client address has multiples parts, only return the first part.
                 */
                return explode(',', $address)[0];
            }
            return $address;
        }

        return false;
    }

    /**
     * Get method.
     *
     * @return string
     */
    public function getMethod()
    {
        $returnMethod = $this->getServer('REQUEST_METHOD');
        if (!isset($returnMethod)) {
            return 'GET';
        }

        $returnMethod = strtoupper($returnMethod);
        if ($returnMethod === 'POST') {
            $overridedMethod = $this->getHeader('X-HTTP-METHOD-OVERRIDE');
            if (!empty($overridedMethod)) {
                $returnMethod = strtoupper($overridedMethod);
            } elseif ($this->_httpMethodParameterOverride) {
                if ($spoofedMethod = $this->get('_method')) {
                    $returnMethod = strtoupper($spoofedMethod);
                }
            }
        }

        if (!$this->isValidHttpMethod($returnMethod)) {
            return 'GET';
        }

        return $returnMethod;
    }

    /**
     * Get user agent.
     *
     * @return string|void
     */
    public function getUserAgent()
    {
        $userAgent = $this->getServer('HTTP_USER_AGENT');
        if ($userAgent) {
            return $userAgent;
        }
        return '';
    }

    /**
     * Is method.
     *
     * @param string $methods
     * @param boolean $strict
     * @return boolean
     */
    public function isMethod($methods, $strict = false)
    {
        $httpMethod = $this->getMethod();

        if (is_string($methods)) {
            if ($strict && !$this->isValidHttpMethod($methods)) {
                throw new Exception('Invalid HTTP method: ' . $methods);
            }
            return $methods == $httpMethod;
        }

        if (is_array($methods)) {
            foreach ($methods as $method) {
                if ($this->isMethod($method, $strict)) {
                    return true;
                }
            }

            return false;
        }

        if ($strict) {
            throw new Exception('Invalid HTTP method: non-string');
        }

        return false;
    }

    /**
     * Is post.
     *
     * @return boolean
     */
    public function isPost()
    {
        return $this->getMethod() === 'POST';
    }

    /**
     * Is GET.
     *
     * @return boolean
     */
    public function isGet()
    {
        return $this->getMethod() === 'GET';
    }

    /**
     * Is Put.
     *
     * @return boolean
     */
    public function isPut()
    {
        return $this->getMethod() === 'PUT';
    }

    /**
     * Is patch.
     *
     * @return boolean
     */
    public function isPatch()
    {
        return $this->getMethod() === 'PATCH';
    }

    /**
     * Is head.
     *
     * @return boolean
     */
    public function isHead()
    {
        return $this->getMethod() === 'HEAD';
    }

    /**
     * Is dealete.
     *
     * @return boolean
     */
    public function isDelete()
    {
        return $this->getMethod() === 'DELETE';
    }

    /**
     * Is Options.
     *
     * @return boolean
     */
    public function isOptions()
    {
        return $this->getMethod() === 'OPTIONS';
    }

    /**
     * Is Purge.
     *
     * @return boolean
     */
    public function isPurge()
    {
        return $this->getMethod() === 'PURGE';
    }

    /**
     * Is trace.
     *
     * @return boolean
     */
    public function isTrace()
    {
        return $this->getMethod() === 'TRACE';
    }

    /**
     * Is connect.
     *
     * @return boolean
     */
    public function isConnect()
    {
        return $this->getMethod() === 'CONNECT';
    }

    /**
     * Has uploaded files?
     *
     * @param boolean $onlySuccessful
     * @return string
     */
    public function hasFiles($onlySuccessful = false)
    {
        $numberFiles = 0;

        $files = $this->files;

        if (empty($files)) {
            return $numberFiles;
        }

        foreach ($files as $file) {
            $error = $file['error'];
            if ($error) {
                if (!is_array($error)) {
                    if (!$error || !$onlySuccessful) {
                        $numberFiles++;
                    }
                } else {
                    $numberFiles += $this->hasFileHelper($error, $onlySuccessful);
                }
            }
        }

        return $numberFiles;
    }

    /**
     * Recursively counts file in an array of files.
     */
    protected function hasFileHelper($data, $onlySuccessful)
    {
        $numberFiles = 0;

        if (!is_array($data)) {
            return 1;
        }

        foreach ($data as $value) {
            if (!is_array($value)) {
                if (!$value || !$onlySuccessful) {
                    $numberFiles++;
                }
            } else {
                $numberFiles += $this->hasFileHelper($value, $onlySuccessful);
            }
        }

        return $numberFiles;
    }

    /**
     * Get the uploaded files.
     *
     * @param boolean $onlySuccessful
     * @return array
     */
    public function getUploadedFiles($onlySuccessful = false)
    {
        $files = [];

        $superFiles = $this->files;

        if (count($superFiles) > 0) {
            foreach ($superFiles as $prefix => $input) {
                if (is_array(!$input['name'])) {
                    $smoothInput = $this->smoothFiles(
                        $input['name'],
                        $input['type'],
                        $input['tmp_name'],
                        $input['size'],
                        $input['error'],
                        $prefix
                    );

                    foreach ($smoothInput as $file) {
                        if ($onlySuccessful === false || $file['error'] == UPLOAD_ERR_OK) {
                            $dataFile = [
                                'name' => $file['name'],
                                'type' => $file['type'],
                                'tmp_name' => $file['tmp_name'],
                                'size' => $file['size'],
                                'error' => $file['error']
                            ];

                            $files[] = new File($dataFile, $file['key']);
                        }
                    }
                } else {
                    if ($onlySuccessful === false || $input['error'] == UPLOAD_ERR_OK) {
                        $files[] = new File($input, $prefix);
                    }
                }
            }
        }

        return $files;
    }

    /**
     * Get the files.
     *
     * @param string $key
     * @return string|void
     */
    public function getFile($key)
    {
        if (!isset($this->_files)) {
            $this->_files = [];
            $files = $this->getUploadedFiles();
            foreach ($files as $file) {
                $this->_files[$file->getKey()] = $file;
            }
        }

        if (!isset($this->_files[$key])) {
            return null;
        }

        return $this->_files[$key];
    }

    /**
     * Smooth out $_FILES to have plain array with all files uploaded.
     */
    protected function smoothFiles($names, $types, $tmp_names, $sizes, $errors, $prefix)
    {
        $files = [];

        foreach ($names as $idx => $name) {
            $p = $prefix . '.' . $idx;

            if (is_string($name)) {
                $files[] = [
                    'name' => $name,
                    'type' => $types[$idx],
                    'tmp_name' => $tmp_names[$idx],
                    'size' => $sizes[$idx],
                    'error' => $errors[$idx],
                    'key' => $p
                ];
            }

            if (is_array($name)) {
                $parentFiles = $this->smoothFiles(
                    $names[$idx],
                    $types[$idx],
                    $tmp_names[$idx],
                    $sizes[$idx],
                    $errors[$idx],
                    $p
                );

                foreach ($parentFiles as $file) {
                    $files[] = $file;
                }
            }
        }

        return $files;
    }

    /**
     * Get the servers.
     *
     * @return array
     */
    public function getServers()
    {
        return $this->server;
    }

    /**
     * Get the headers.
     *
     * @return array
     */
    public function getHeaders()
    {
        $headers = [];
        $contentHeaders = ['CONTENT_TYPE' => true, 'CONTENT_LENGTH' => true, 'CONTENT_MD5' => true];

        $servers = $this->getServers();
        foreach ($servers as $name => $value) {
            if (Text::startsWith($name, 'HTTP_')) {
                $name = ucwords(strtolower(str_replace('_', ' ', substr($name, 5))));
                $name = str_replace(' ', '-', $name);
                $headers[$name] = $value;
            }

            $name = strtoupper($name);
            if (isset($contentHeaders[$name])) {
                $name = ucwords(strtolower(str_replace('_', ' ', $name)));
                $name = str_replace(' ', '-', $name);
                $headers[$name] = $value;
            }
        }

        $authHeaders = $this->resolveAuthorizationHeaders();

        // Protect for future (child classes) changes
        if (is_array($authHeaders)) {
            $headers = array_merge($headers, $authHeaders);
        }

        return $headers;
    }

    /**
     * Get the httpd reference.
     *
     * @return string|void
     */
    public function getHTTPReferer()
    {
        $httpReferer = $this->getServer('HTTP_REFERER');
        if ($httpReferer) {
            return $httpReferer;
        }

        return '';
    }

    /**
     * Process a request header and return the one with best quality.
     *
     * @return string
     */
    protected function _getBestQuality($qualityParts, $name)
    {
        $i = 0;
        $quality = 0.0;
        $selectedName = '';

        foreach ($qualityParts as $accept) {
            if ($i == 0) {
                $quality = (double)$accept['quality'];
                $selectedName = $accept[$name];
            } else {
                $acceptQuality = (double)$accept['quality'];
                if ($acceptQuality > $quality) {
                    $quality = $acceptQuality;
                    $selectedName = $accept[$name];
                }
            }
            $i++;
        }

        return $selectedName;
    }

    /**
     * Get the content.
     *
     * @return array
     */
    public function getAcceptableContent()
    {
        return $this->_getQualityHeader('HTTP_ACCEPT', 'accept');
    }

    /**
     * Get the content.
     *
     * @return string
     */
    public function getBestAccept()
    {
        return $this->_getBestQuality($this->getAcceptableContent(), 'accept');
    }

    /**
     * Get the content.
     *
     * @return array
     */
    public function getClientCharsets()
    {
        return $this->_getQualityHeader('HTTP_ACCEPT_CHARSET', 'charset');
    }

    /**
     * Get the content.
     *
     * @return string
     */
    public function getBestCharset()
    {
        return $this->_getBestQuality($this->getClientCharsets(), 'charset');
    }

    /**
     * Get the content.
     *
     * @return array
     */
    public function getLanguages()
    {
        return $this->_getQualityHeader('HTTP_ACCEPT_LANGUAGE', 'language');
    }

    /**
     * Get the content.
     *
     * @return string
     */
    public function getBestLanguage()
    {
        return $this->_getBestQuality($this->getLanguages(), 'language');
    }

    /**
     * Get the basic httpd auth.
     *
     * @return array|void
     */
    public function getBasicAuth()
    {
        if ($this->hasServer('PHP_AUTH_USER') && $this->hasServer('PHP_AUTH_PW')) {
            return [
                'username' => $this->getServer('PHP_AUTH_USER'),
                'password' => $this->getServer('PHP_AUTH_PW')
            ];
        }

        return null;
    }

    /**
     * Get the server digest.
     *
     * @return array
     */
    public function getDigestAuth()
    {
        $auth = [];
        if ($this->hasServer('PHP_AUTH_DIGEST')) {
            $digest = $this->getServer('PHP_AUTH_DIGEST');
            $matches = [];
            if (!preg_match_all("#(\\w+)=(['\"]?)([^'\" ,]+)\\2#", $digest, $matches, 2)) {
                return $auth;
            }
            if (is_array($matches)) {
                foreach ($matches as $match) {
                    $auth[$match[1]] = $match[3];
                }
            }
        }

        return $auth;
    }

    /**
     * Checks if a method is a valid HTTP method.
     */
    public function isValidHttpMethod($method)
    {
        switch (strtoupper($method)) {
            case 'GET':
            case 'POST':
            case 'PUT':
            case 'DELETE':
            case 'HEAD':
            case 'OPTIONS':
            case 'PATCH':
            case 'PURGE': // Squid and Varnish support
            case 'TRACE':
            case 'CONNECT':
                return true;
        }

        return false;
    }

    /**
     * Helper to get data from superglobals, applying filters if needed.
     * If no parameters are given the superglobal is returned.
     */
    protected function getHelper($source, $name = null, $filters = null, $defaultValue = null, $notAllowEmpty = false, $noRecursive = false)
    {
        if ($name === null) {
            return $source;
        }

        if (!isset($source[$name])) {
            return $defaultValue;
        }

        $value = $source[$name];

        if ($filters !== null) {
            $filter = $this->_filter;
            if (!$filter instanceof FilterInterface) {
                $dependencyInjector = $this->_dependencyInjector;
                if (!$dependencyInjector instanceof DiInterface) {
                    throw new Exception("A dependency injection object is required to access the 'filter' service");
                }

                $filter = $dependencyInjector->getShared('filter');
                $this->_filter = $filter;
            }

            $value = $filter->sanitize($value, $filters, $noRecursive);
        }

        if (empty($value) && $notAllowEmpty === true) {
            return $defaultValue;
        }

        return $value;
    }

    /**
     * Gets content type which request has been made.
     */
    public function getContentType()
    {
        $contentType = $this->getHeader('CONTENT_TYPE');
        if ($contentType) {
            return $contentType;
        }

        return null;
    }

    /**
     * Process a request header and return an array of values with their qualities.
     *
     * @return array
     */
    protected function _getQualityHeader($serverIndex, $name)
    {
        $returnedParts = [];
        $parts = preg_split('/,\\s*/', $this->getServer($serverIndex), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($parts as $part) {
            $headerParts = [];
            $hParts = preg_split("/\s*;\s*/", trim($part), -1, PREG_SPLIT_NO_EMPTY);
            foreach ($hParts as $headerPart) {
                if (strpos($headerPart, '=') !== false) {
                    $split = explode('=', $headerPart, 2);
                    if ($split[0] === 'q') {
                        $headerParts['quality'] = (double)$split[1];
                    } else {
                        $headerParts[$split[0]] = $split[1];
                    }
                } else {
                    $headerParts[$name] = $headerPart;
                    $headerParts['quality'] = 1.0;
                }
            }

            $returnedParts[] = $headerParts;
        }

        return $returnedParts;
    }

    /**
     * Resolve authorization headers.
     */
    protected function resolveAuthorizationHeaders()
    {
        $headers = [];
        $hasEventsManager = false;
        $eventsManager = null;

        $dependencyInjector = $this->getDI();
        if ($dependencyInjector instanceof DiInterface) {
            $hasEventsManager = (bool)$dependencyInjector->has('eventsManager');
            if ($hasEventsManager) {
                $eventsManager = $dependencyInjector->getShared('eventsManager');
            }
        }

        if ($hasEventsManager && $eventsManager instanceof Manager) {
            $resolved = $eventsManager->fire(
                'request:beforeAuthorizationResolve',
                $this,
                ['server' => $this->getServers()]
            );

            if (is_array($resolved)) {
                $headers = array_merge($headers, $resolved);
            }
        }

        $this->resolveAuthHeaderPhp($headers);
        $this->resolveAuthHeaderPhpDigest($headers);

        if ($hasEventsManager && $eventsManager instanceof Manager) {
            $resolved = $eventsManager->fire(
                'request:afterAuthorizationResolve',
                $this,
                ['headers' => $headers, 'server' => $this->getServers()]
            );

            if (is_array($resolved)) {
                $headers = array_merge($headers, $resolved);
            }
        }

        return $headers;
    }

    /**
     * Resolve the PHP_AUTH_USER.
     *
     * @param array $headers
     * @return void
     */
    protected function resolveAuthHeaderPhp(array &$headers): void
    {
        $authHeader = false;

        if ($this->hasServer('PHP_AUTH_USER') && $this->hasServer('PHP_AUTH_PW')) {
            $headers['Php-Auth-User'] = $this->getServer('PHP_AUTH_USER');
            $headers['Php-Auth-Pw'] = $this->getServer('PHP_AUTH_PW');
        } else {
            if ($this->hasServer('HTTP_AUTHORIZATION')) {
                $authHeader = $this->getServer('HTTP_AUTHORIZATION');
            } elseif ($this->hasServer('REDIRECT_HTTP_AUTHORIZATION')) {
                $authHeader = $this->getServer('REDIRECT_HTTP_AUTHORIZATION');
            }

            if ($authHeader) {
                if (stripos($authHeader, 'basic ') === 0) {
                    $exploded = explode(':', base64_decode(substr($authHeader, 6)), 2);
                    if (count($exploded) == 2) {
                        $headers['Php-Auth-User'] = $exploded[0];
                        $headers['Php-Auth-Pw'] = $exploded[1];
                    }
                } elseif (stripos($authHeader, 'digest ') === 0 && !$this->hasServer('PHP_AUTH_DIGEST')) {
                    $headers['Php-Auth-Digest'] = $authHeader;
                } elseif (stripos($authHeader, 'bearer ') === 0) {
                    $headers['Authorization'] = $authHeader;
                }
            }
        }
    }

    /**
     * Reseolve PHP auth digest.
     *
     * @param array $headers
     * @return void
     */
    protected function resolveAuthHeaderPhpDigest(array &$headers): void
    {
        if (!isset($headers['Authorization'])) {
            if (isset($headers['Php-Auth-User'])) {
                $headers['Authorization'] = 'Basic ' . base64_encode($headers['Php-Auth-User'] . ':' . $headers['Php-Auth-Pw']);
            } elseif (isset($headers['Php-Auth-Digest'])) {
                $headers['Authorization'] = $headers['Php-Auth-Digest'];
            }
        }
    }
}
