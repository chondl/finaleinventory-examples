#!/usr/bin/php
<?php

require('./auth.php');

$headerColumns = fgetcsv(STDIN, 1000, ",");
foreach($headerColumns as $idx => $headerColumn) {
    if (strcasecmp(trim($headerColumn), 'Facility: Name') == 0) {
        $facilityNameIdx = $idx;
    }
    if (strcasecmp(trim($headerColumn), 'Facility: Parent name') == 0) {
        $facilityParentNameIdx = $idx;
    }
}

while (($data = fgetcsv(STDIN, 1000, ",")) !== FALSE) {
    $imports[] = [name => trim($data[$facilityNameIdx]), parentName => trim($data[$facilityParentNameIdx])];
}

// Replace these variables with appropriate values for your company and user accounts
$host = "https://app.finaleinventory.com";
$authPath = "/demo/api/auth";
$username = $argv[1];
$password = $argv[2];

$auth = finale_auth($host, $authPath, $username, $password);

echo "Authenticated successfully username=".$auth["auth_response"]->name."\n";

// Request facility data 
$resourceFacility = $auth['auth_response']->resourceFacility;
curl_setopt($auth['curl_handle'], CURLOPT_URL, $auth['host'] . $resourceFacility);
curl_setopt($auth['curl_handle'], CURLOPT_HTTPGET, true);

$result = curl_exec($auth['curl_handle']);

$status_code = curl_getinfo($auth['curl_handle'], CURLINFO_HTTP_CODE);
if ($status_code != 200) exit("FAIL: fetch facility statusCode=$status_code\n");
$facilityResp = json_decode($result, true);
echo "Successfully fetched facility resource count=".count($facilityResp->facilityUrl)."\n";

foreach($facilityResp['facilityUrl'] as $idx => $facilityUrl) {
    $facilityLookup[$facilityResp['facilityName'][$idx]] = $facilityUrl;
}

foreach($imports as $import) {
    $name = $import['name'];
    $parentName = $import['parentName'];
    $facilityUrl = $facilityLookup[$name];
    $parentFacilityUrl = $facilityLookup[$parentName];


    if ($facilityUrl) {
        echo "Did not import $name. Location or sub-location with that name already exists." . PHP_EOL;
    } else if (!$parentFacilityUrl) {
        echo "Did not import $name. Parent location $parentName does not exist." . PHP_EOL;
    } else {
        echo "Import $name begin." . PHP_EOL;
        curl_setopt($auth['curl_handle'], CURLOPT_URL, $auth['host'] . $resourceFacility);
        curl_setopt($auth['curl_handle'], CURLOPT_HTTPGET, 0);
        curl_setopt($auth['curl_handle'], CURLOPT_POST, 1);
        curl_setopt($auth['curl_handle'], CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($auth['curl_handle'], CURLOPT_POSTFIELDS, json_encode([ 
            'facilityName'=>$name, 'parentFacilityUrl'=>$parentFacilityUrl, 'sessionSecret' => $auth['session_secret']
        ]));

        $result = curl_exec($auth['curl_handle']);
        
        $status_code = curl_getinfo($auth['curl_handle'], CURLINFO_HTTP_CODE);

        if ($status_code == 200) {
            echo "Import $name success!" . PHP_EOL;
            echo "Import $name end" . PHP_EOL;
        } else {
            echo "Did not import $name. statusCode=$statusCode result=$result" . PHP_EOL;
            echo "Import $name end" . PHP_EOL;
        }


    }
}


// Example program just displays content of collection
//var_dump($facilityLookup);
?>


