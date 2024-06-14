=======
# RestClient Example Usage

This example demonstrates how to use the `RestClient` class to interact with a REST API. The `RestClient` class is designed to handle common HTTP methods and manage responses in an organized manner.

## Requirements

- PHP 7.4 or higher
- `RestClient.php` (Make sure this file is in the same directory as your script)

## Usage

### Including the RestClient

First, include the `RestClient` class in your script and use the necessary namespaces:

```php
require_once 'RestClient.php'; // Assuming RestClient.php is in the same directory

use Drc\Core\RestClient;
use Drc\Core\RestClientException; 


Example Usage
Here is an example of how to create an instance of RestClient and perform various HTTP requests:

php
Copy code
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
Handling Exceptions
The example above includes basic exception handling for RestClientException and other generic exceptions. Make sure to handle exceptions properly to avoid unexpected errors in your application.

License
This example is provided under the MIT License.

