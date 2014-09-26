#!/usr/bin/php
<?php

require('./auth.php');

// Replace these variables with appropriate values for your company and user accounts
$host = "https://app.finaleinventory.com";
$authPath = "/demo/api/auth";
$username = "test";
$password = "finale";

$auth = finale_auth($host, $authPath, $username, $password);

echo "Authenticated successfully username=".$auth["auth_response"]->name."\n";

// Request product data 
curl_setopt($auth['curl_handle'], CURLOPT_URL, $auth['host'] . $auth['auth_response']->resourceProduct);
curl_setopt($auth['curl_handle'], CURLOPT_HTTPGET, true);

$result = curl_exec($auth['curl_handle']);

$status_code = curl_getinfo($auth['curl_handle'], CURLINFO_HTTP_CODE);
if ($status_code != 200) exit("FAIL: fetch product statusCode=$status_code\n");
$productResp = json_decode($result);
echo "Successfully fetched product resource count=".count($productResp->productUrl)."\n";


// Example program just displays content of collection
var_dump($productResp);
?>

