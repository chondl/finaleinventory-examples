<?php

/*
USAGE: 
 Example call with account path component - test Authentication with Finale Inventory account via api
 Replace these variables with appropriate values for your company and user accounts
 Replace youraccountname below with your fianle inventory account name
 Your account name is in between the slashes on your Finale Inventory log in URL.
 For example your web browser URL for your account is https://app.finaleinventory.com/accountname/ enter the account name in lower case with no spaces 
 Replace finaleusername below with your username, the same one you log into your Finale Inventory account on the web
 Replace password below with your password.
 IMPORTANT NOTE: To avoid authentication issues, make sure your password does not contain special characters
*/
$host = "https://app.finaleinventory.com";
$authPath = "/youraccountname/api/auth";
$username = "finaleusername"; 
$password = "password";

$auth = finale_auth($host, $authPath, $username, $password);

echo "Authenticated successfully username=".$auth["auth_response"]->name."\n";

function finale_auth($host, $path, $username, $password) {

    // Create curl handle with options used for all requests.  Finale API authentication is cookie based, so cookies need to be enabled
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR,"");
    
    
    // Login to Finale
    curl_setopt($ch, CURLOPT_URL, $host.$path);  
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode( array( "username" => $username, "password" => $password)));
    curl_setopt($ch, CURLOPT_HEADER, 1);
    
    $response = curl_exec($ch);
    
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($status_code != 200) exit("FAIL: authentication error statusCode=$status_code\n");
  
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
  
    // Pull out all JSESSIONID cookie headers
    preg_match_all('|Set-Cookie: JSESSIONID=(.*);|U', $header, $cookies);    
  
    // Don't return headers in future http requests to keep them simple (reuse to curl handle for automatic cookie handling)
    curl_setopt($ch, CURLOPT_HEADER, 0);
    
    return array( "curl_handle" => $ch, "auth_response" => json_decode($body), "host" => $host, "session_secret" => array_pop($cookies[1]) );
}

// Example program just tests authentication
//
?>
