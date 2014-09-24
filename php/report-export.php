#!/usr/bin/php
<?php

require('./auth.php');

// Replace these variables with appropriate values for your company and user accounts
$host = "https://app.finaleinventory.com";
$authPath = "/demo/api/auth";
$username = "test";
$password = "finale";

$auth = finale_auth($host.$authPath, $username, $password);

echo "Authenticated successfully username=".$auth["auth_response"]->name."\n";

// Request report "Stock for each product, in units" using URL copied from address bar in the browser
// To run other reports just run the report from UI and copy the URL into your code
curl_setopt($auth['curl_handle'], CURLOPT_URL, 'https://app.finaleinventory.com/demo/doc/report/pivotTable/1411538143436/Report.csv?format=csv&data=product&rowDimensions=productProductId_86%7CproductDescription_133%7CproductCategory_72%7CproductManufacturer_72%7CproductStdBinId_43__U3RkXG5iaW4gSUQ%3D%7CproductStdPacking_43__U3RkXG5wYWNraW5n&metrics=productStockQuantityOnHandUnitsSum_54__VW5pdHNcblFvSA%3D%3D%7CproductStockReservationsUnitsSum_54__VW5pdHNcblJlc2VydmVk%7CproductStockRemainingAfterReservationsUnitsSum_54__VW5pdHNcblJlbWFpbmluZw%3D%3D%7CproductStockOnOrderUnitsSum_54__VW5pdHNcbk9uIG9yZGVy%7CproductStockAvailableToPromiseUnitsSum_54__VW5pdHNcbkF2YWlsYWJsZQ%3D%3D&filters=W1sicHJvZHVjdFN0YXR1cyIsWyJQUk9EVUNUX0FDVElWRSJdXSxbInByb2R1Y3RDYXRlZ29yeSIsbnVsbF0sWyJwcm9kdWN0TWFudWZhY3R1cmVyIixudWxsXSxbInByb2R1Y3RTdGRCaW5JZCIsbnVsbF0sWyJwcm9kdWN0UHJvZHVjdFVybCIsbnVsbF1d&reportTitle=Stock+for+each+product%2C+in+units');
curl_setopt($auth['curl_handle'], CURLOPT_HTTPGET, true);

$result = curl_exec($auth['curl_handle']);

$status_code = curl_getinfo($auth['curl_handle'], CURLINFO_HTTP_CODE);
if ($status_code != 200) exit("FAIL: export report statusCode=$status_code\n");
echo "Successfully exported report to CSV\n";

// Example program just outputs contents of report as string
var_dump($result);


?>
