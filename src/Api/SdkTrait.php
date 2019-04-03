<?php

namespace Baka\Http\Api;

use Baka\Hmac\Client as ClientSecurity;
use Phalcon\Http\Response;

trait SdkTrait
{
    /**
     * @string
     */
    protected $apiVersion;

    /**
     * @string
     */
    protected $apiPublicKey;

    /**
     * @string
     */
    protected $apiPrivateKey;

    /**
     * @string
     */
    protected $apiHeaders = [];

    /**
     * Function to set the API version since the property is protected.
     *
     * @param mixed $version
     *
     * @return void
     */
    public function setApiVersion($version)
    {
        $this->apiVersion = $version;
    }

    /**
     * Function to set the API public key since the property is protected.
     *
     * @param mixed $version
     *
     * @return void
     */
    public function setApiPublicKey($publicKey)
    {
        $this->apiPublicKey = $publicKey;
    }

    /**
     * Function to set the API private key since the property is protected.
     *
     * @param mixed $version
     *
     * @return void
     */
    public function setApiPrivateKey($privateKey)
    {
        $this->apiPrivateKey = $privateKey;
    }

    /**
     * Function to set the API headers since the property is protected.
     *
     * @param mixed $version
     *
     * @return void
     */
    public function setApiHeaders($apiHeaders)
    {
        $this->apiHeaders = $apiHeaders;
    }

    /**
     * Function tasked with delegating API requests to the configured API.
     *
     * @todo Verify headers being received from the API response before returning the request response.
     *
     * @return \Phalcon\Http\Response
     */
    public function transporterAction(): Response
    {
        // Get all router params
        $routeParams = $this->router->getParams();

        // Confirm that an API version has been configured
        if (!array_key_exists('version', $routeParams)) {
            return $this->response->setJsonContent([
                'error' => 'You must specify an API version in your configuration.',
            ])->send();
        }

        // Confirm that the API version matches with the configuration.
        if ($routeParams['version'] != $this->apiVersion) {
            return $this->response->setJsonContent([
                'error' => 'The specified API version is different from your configuration.',
            ])->send();
        }

        // Get real API URL
        $apiUrl = getenv('EXT_API_URL') . str_replace('api/', '', $this->router->getRewriteUri());

        // Execute the request, providing the URL, the request method and the data.
        $response = $this->makeRequest($apiUrl, $this->request->getMethod(), $this->getData(), $this->apiHeaders);

        //set status code so we can get 404
        if ($response->getStatusCode()) {
            $this->response->setStatusCode($response->getStatusCode());
        }

        //Set all the response headers
        foreach ($response->getHeaders() as $header => $value) {
            // Set the response headers based on the response headers of the API.
            if (is_array($value)) {
                $value = current($value);
            }

            $this->response->setHeader($header, $value);
        }

        return $this->response->setContent($response->getBody());
    }

    /**
     * Function that executes the request to the configured API.
     *
     * @param string $method - The request method
     * @param string $url - The request URL
     * @param array $data - The form data
     *
     * @return JSON
     */
    public function makeRequest($url, $method = 'GET', $data = [], $headers = [])
    {
        //we cant send to the API the array with get GET, JSON, form_params parameters
        //the API doesnt get it taht way, guzzle sends it directly
        //so we extract it
        $rawData = count(array_keys($data)) > 0 ? $data[array_keys($data)[0]] : [];

        $client = (new ClientSecurity($this->apiPublicKey, $this->apiPrivateKey, $rawData, $headers))->getGuzzle();

        $parse = function ($error) {
            if ($error->hasResponse()) {
                return $error->getResponse();
            }

            return json_decode($error->getMessage());
        };

        try {
            return $client->request($method, $url, $data);
        } catch (\GuzzleHttp\Exception\BadResponseException $error) {
            return $parse($error);
        } catch (\GuzzleHttp\Exception\ClientException $error) {
            return $parse($error);
        } catch (\GuzzleHttp\Exception\ConnectException $error) {
            return $parse($error);
        } catch (\GuzzleHttp\Exception\RequestException $error) {
            return $parse($error);
        }
    }

    /**
     * Function that obtains the data as per the request type.
     *
     * @return array
     */
    public function getData(): array
    {
        $uploads = [];
        //if the user is trying to upload a image
        if ($this->request->hasFiles()) {
            foreach ($this->request->getUploadedFiles() as $file) {
                $uploads[] = [
                    'name' => $file->getKey(),
                    'filename' => $file->getName(),
                    'contents' => file_get_contents($file->getTempName()),
                ];
            }

            $parseUpload = function ($request) use (&$uploads) {
                foreach ($request as $key => $value) {
                    if (is_array($value)) {
                        foreach ($value as $f => $v) {
                            $uploads[] = ['name' => $key . '[' . $f . ']', 'contents' => $v];
                        }
                    } else {
                        $uploads[] = ['name' => $key, 'contents' => $value];
                    }
                }
            };
        }

        switch ($this->request->getMethod()) {
            case 'GET':
                $queryParams = $this->request->getQuery();
                unset($queryParams['_url']);
                return ['query' => $queryParams];
                break;

            case 'POST':
                if (!$uploads) {
                    //if we dont get post send the raw
                    if (count($this->request->getPost()) > 0) {
                        return ['form_params' => $this->request->getPost()];
                    } elseif ($this->request->getJsonRawBody()) {
                        return ['json' => json_decode($this->request->getRawBody(), true)];
                    }
                    //why did we get here?
                    return ['form_params' => $this->request->getPost()];
                } else {
                    $parseUpload($this->request->getPost());
                    return ['multipart' => $uploads];
                }
                break;

            case 'PUT':
                if (!$uploads) {
                    //if we get put send it with the normal put, else send json
                    if (count($this->request->getPut()) > 0) {
                        return ['form_params' => $this->request->getPut()];
                    } elseif ($this->request->getJsonRawBody()) {
                        return ['json' => json_decode($this->request->getRawBody(), true)];
                    }
                    //why did we get here?
                    return ['form_params' => $this->request->getPut()];
                } else {
                    $parseUpload($this->request->getPut());
                    return ['multipart' => $uploads];
                }

                break;
            default:
                return [];
                break;
        }
    }
}
