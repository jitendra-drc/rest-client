<?php

namespace Drc\Core;

class RestClientException extends \Exception {}

class RestClient implements \Iterator, \ArrayAccess {

    private const BASE_URL = 'https://api.example.com';
    
    private $options;
    private $handle;
    private $url;
    private $response;
    private $headers;
    private $info;
    private $error;
    private $response_status_lines;
    private $decoded_response;

    public function __construct(array $options = []) {
        $this->setDefaultOptions($options);
    }

    private function setDefaultOptions(array $options): void {
        $defaultOptions = [
            'headers' => [],
            'parameters' => [],
            'curl_options' => [],
            'build_indexed_queries' => false,
            'user_agent' => "PHP RestClient/0.1.8",
            'base_url' => null,
            'format' => null,
            'format_regex' => "/(\w+)\/(\w+)(;[.+])?/",
            'decoders' => [
                'json' => 'json_decode',
                'php' => 'unserialize'
            ],
            'username' => null,
            'password' => null
        ];
        $this->options = array_merge($defaultOptions, $options);
        if (array_key_exists('decoders', $options)) {
            $this->options['decoders'] = array_merge($defaultOptions['decoders'], $options['decoders']);
        }
    }

    public function setOption($key, $value): void {
        $this->options[$key] = $value;
    }

    public function registerDecoder(string $format, callable $method): void {
        $this->options['decoders'][$format] = $method;
    }

    // Iterable methods:
    public function rewind(): void {
        $this->decodeResponse();
        reset($this->decoded_response);
    }

    public function current() {
        return current($this->decoded_response);
    }

    public function key() {
        return key($this->decoded_response);
    }

    public function next(): void {
        next($this->decoded_response);
    }

    public function valid(): bool {
        return is_array($this->decoded_response) && key($this->decoded_response) !== null;
    }

    // ArrayAccess methods:
    public function offsetExists($key): bool {
        $this->decodeResponse();
        return is_array($this->decoded_response) ? isset($this->decoded_response[$key]) : isset($this->decoded_response->{$key});
    }

    public function offsetGet($key) {
        $this->decodeResponse();
        if (!$this->offsetExists($key)) {
            return null;
        }
        return is_array($this->decoded_response) ? $this->decoded_response[$key] : $this->decoded_response->{$key};
    }

    public function offsetSet($key, $value): void {
        throw new RestClientException("Decoded response data is immutable.");
    }

    public function offsetUnset($key): void {
        throw new RestClientException("Decoded response data is immutable.");
    }

    // Request methods:
    public function get(string $url, $parameters = [], array $headers = []): RestClient {
        return $this->execute($url, 'GET', $parameters, $headers);
    }

    public function post(string $url, $parameters = [], array $headers = []): RestClient {
        return $this->execute($url, 'POST', $parameters, $headers);
    }

    public function put(string $url, $parameters = [], array $headers = []): RestClient {
        return $this->execute($url, 'PUT', $parameters, $headers);
    }

    public function patch(string $url, $parameters = [], array $headers = []): RestClient {
        return $this->execute($url, 'PATCH', $parameters, $headers);
    }

    public function delete(string $url, $parameters = [], array $headers = []): RestClient {
        return $this->execute($url, 'DELETE', $parameters, $headers);
    }

    public function head(string $url, $parameters = [], array $headers = []): RestClient {
        return $this->execute($url, 'HEAD', $parameters, $headers);
    }

    public function execute(string $url, string $method = 'GET', $parameters = [], array $headers = []): RestClient {
        $client = clone $this;
        $client->url = $url;
        $client->handle = curl_init();
        $curlopt = [
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => $client->options['user_agent']
        ];

        if ($client->options['username'] && $client->options['password']) {
            $curlopt[CURLOPT_USERPWD] = sprintf("%s:%s", $client->options['username'], $client->options['password']);
        }

        if (count($client->options['headers']) || count($headers)) {
            $curlopt[CURLOPT_HTTPHEADER] = [];
            $headers = array_merge($client->options['headers'], $headers);
            foreach ($headers as $key => $values) {
                foreach (is_array($values) ? $values : [$values] as $value) {
                    $curlopt[CURLOPT_HTTPHEADER][] = sprintf("%s: %s", $key, $value);
                }
            }
        }

        if ($client->options['format']) {
            $client->url .= '.' . $client->options['format'];
        }

        if (is_array($parameters)) {
            $parameters = array_merge($client->options['parameters'], $parameters);
            $parametersString = http_build_query($parameters);

            if (!$client->options['build_indexed_queries']) {
                $parametersString = preg_replace("/%5B[0-9]+%5D=/simU", "%5B%5D=", $parametersString);
            }
        } else {
            $parametersString = (string) $parameters;
        }

        if (strtoupper($method) === 'POST') {
            $curlopt[CURLOPT_POST] = true;
            $curlopt[CURLOPT_POSTFIELDS] = $parametersString;
        } elseif (strtoupper($method) !== 'GET') {
            $curlopt[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
            $curlopt[CURLOPT_POSTFIELDS] = $parametersString;
        } elseif ($parametersString) {
            $client->url .= strpos($client->url, '?') ? '&' : '?';
            $client->url .= $parametersString;
        }

        if ($client->options['base_url']) {
            $client->url = sprintf("%s/%s", rtrim((string) $client->options['base_url'], '/'), ltrim((string) $client->url, '/'));
        }
        $curlopt[CURLOPT_URL] = $client->url;

        if ($client->options['curl_options']) {
            foreach ($client->options['curl_options'] as $key => $value) {
                $curlopt[$key] = $value;
            }
        }
        curl_setopt_array($client->handle, $curlopt);

        $response = curl_exec($client->handle);
        if ($response !== false) {
            $client->parseResponse($response);
        }
        $client->info = (object) curl_getinfo($client->handle);
        $client->error = curl_error($client->handle);

        curl_close($client->handle);
        return $client;
    }

    private function parseResponse($response): void {
        $headers = [];
        $this->response_status_lines = [];
        $line = strtok($response, "\n");
        do {
            if (strlen(trim($line)) == 0) {
                if (count($headers) > 0) {
                    break;
                }
            } elseif (strpos($line, 'HTTP') === 0) {
                $this->response_status_lines[] = trim($line);
            } else {
                [$key, $value] = explode(':', $line, 2);
                $key = strtolower(trim(str_replace('-', '_', $key)));
                $value = trim($value);

                if (empty($headers[$key])) {
                    $headers[$key] = $value;
                } elseif (is_array($headers[$key])) {
                    $headers[$key][] = $value;
                } else {
                    $headers[$key] = [$headers[$key], $value];
                }
            }
        } while ($line = strtok("\n"));

        $this->headers = (object) $headers;
        $this->response = strtok("");
    }

    private function getResponseFormat(): string {
        if (!$this->response) {
            throw new RestClientException("A response must exist before it can be decoded.");
        }

        if (!empty($this->options['format'])) {
            return $this->options['format'];
        }

        if (!empty($this->headers->content_type) && preg_match($this->options['format_regex'], $this->headers->content_type, $matches)) {
            return $matches[2];
        }

        throw new RestClientException("Response format could not be determined.");
    }

    private function decodeResponse(): void {
        if (empty($this->decoded_response)) {
            $format = $this->getResponseFormat();
            if (!array_key_exists($format, $this->options['decoders'])) {
                throw new RestClientException("'{$format}' is not a supported format, register a decoder to handle this response.");
            }

            $this->decoded_response = call_user_func($this->options['decoders'][$format], $this->response);
        }
    }
}
