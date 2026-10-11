<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$kernel = $app->make('Illuminate\Contracts\Http\Kernel');

// First, get the login page to get CSRF token and session
$request1 = new Illuminate\Http\Request();
$request1->headers->set('Accept', 'text/html');
$request1->server->set('REQUEST_METHOD', 'GET');
$request1->server->set('REQUEST_URI', '/logistics/login');

$response1 = $kernel->handle($request1);
echo 'GET Status: ' . $response1->getStatusCode() . PHP_EOL;

// Extract CSRF token and session cookie
$cookie = $response1->headers->get('Set-Cookie');
echo 'Cookie: ' . $cookie . PHP_EOL;

$content = $response1->getContent();
if (preg_match('/name="_token" value="([^"]+)"/', $content, $matches)) {
    $token = $matches[1];
    echo 'Token: ' . $token . PHP_EOL;
    
    // Now POST with the token and session cookie
    $request2 = new Illuminate\Http\Request();
    $request2->headers->set('Accept', 'text/html');
    $request2->headers->set('Content-Type', 'application/x-www-form-urlencoded');
    if ($cookie) {
        $request2->headers->set('Cookie', $cookie);
    }
    $request2->server->set('REQUEST_METHOD', 'POST');
    $request2->server->set('REQUEST_URI', '/logistics/login');
    $request2->request->add([
        'email' => 'logistics_admin@test.com', 
        'password' => 'password123', 
        '_token' => $token
    ]);

    $response2 = $kernel->handle($request2);
    echo 'POST Status: ' . $response2->getStatusCode() . PHP_EOL;
    echo 'POST Redirect: ' . $response2->headers->get('Location') . PHP_EOL;
    echo 'POST Content length: ' . strlen($response2->getContent()) . PHP_EOL;
}