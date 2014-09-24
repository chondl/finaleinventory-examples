<?php

function finale_auth($url, $username, $password) {
  // Create curl handle with options used for all requests.  Finale API authentication is cookie based, so cookies need to be enabled
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_COOKIEFILE,"");
  
  
  // Login to Finale
  curl_setopt($ch, CURLOPT_URL, $url);  
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode( array( "username" => $username, "password" => $password)));
  
  $result = curl_exec($ch);
  
  $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  if ($status_code != 200) exit("FAIL: authentication error statusCode=$status_code\n");
  $auth_response = json_decode($result);
  
  $auth = array( "curl_handle" => $ch, "auth_response" => $auth_response );

  return $auth;
}

?>
