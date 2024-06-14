<?php

require_once 'RestClient.php'; // Assuming RestClient.php is in the same directory

use Drc\Core\RestClient;
use Drc\Core\RestClientException;

// Example usage:

try {
    // Create a new instance of RestClient
    $client = new RestClient([
        'base_url' => 'https://api.example.com',
        'username' => 'your_username',
        'password' => 'your_password',
        'headers' => [
            'Content-Type' => 'application/json'
        ]
    ]);

    // Perform a GET request
    $response = $client->get('/endpoint');
    
    // Access response data
    $responseData = $response->decoded_response;
    
    // Loop through response data if it's an array
    foreach ($responseData as $data) {
        // Process each data item
    }
    
    // Access response headers
    $responseHeaders = $response->headers;
    
    // Perform other HTTP methods
    // $response = $client->post('/endpoint', $postData);
    // $response = $client->put('/endpoint', $putData);
    // $response = $client->delete('/endpoint');

} catch (RestClientException $e) {
    // Handle RestClient exceptions
    echo 'RestClientException: ' . $e->getMessage();
} catch (Exception $e) {
    // Handle other exceptions
    echo 'Exception: ' . $e->getMessage();
}
