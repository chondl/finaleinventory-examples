#!/usr/bin/php
<?php

// This example shows updating stock levels in Finale 
//   - starts with supplier product id values and corresponding quantity on hand values (an inventory feed)
//   - updates are at a specified sublocation since Finale tracks inventory at sublocation level
//   - uses the facility and product resource to lookup facilityUrl/productUrl from user data
//   - uses the inventory item resource to lookup current quantity on hand to calculate difference between new and old
//   - ignore packing and lot identifiers to keep it simple (if you need an example with packing and lot let the Finale team know)
//   - updates inventory using the inventory variance resource

// Replace these variables with appropriate values for your company and user accounts
$host = "https://app.finaleinventory.com";
$authPath = "/demo/api/auth";
$username = "test";
$password = "finale";

// Inventory variance always applies to a single sublocation (demo data - replace with sublocation in your account)
$sublocationName = "M0";

// Map from supplier product id to new stock level for each product id (demo data, should match supplier product id values in your account)
$stockLevelUpdate = array("71352" => 19, "2x" => 12);
    

require('./auth.php');

function finale_fetch_resource($auth, $resource) {
  curl_setopt($auth['curl_handle'], CURLOPT_URL, $auth['host'] . $auth['auth_response']->{$resource});
  curl_setopt($auth['curl_handle'], CURLOPT_HTTPGET, true);
  
  $result = curl_exec($auth['curl_handle']);
  
  $status_code = curl_getinfo($auth['curl_handle'], CURLINFO_HTTP_CODE);
  if ($status_code != 200) exit("FAIL: fetch $resource statusCode=$status_code\n");
  return json_decode($result);
}

$auth = finale_auth($host, $authPath, $username, $password);
echo "Authenticated successfully username=".$auth["auth_response"]->name."\n";

// Fetch facility resource and create index from name to facility url
$facilityResp = finale_fetch_resource($auth, 'resourceFacility');
foreach($facilityResp->facilityUrl as $idx => $facilityUrl) {
    $facilityNameLookup[$facilityResp->facilityName[$idx]] = $facilityUrl;
}
echo "Fetch facility resource length=".count($facilityResp->facilityUrl)."\n";


// Fetch product response and create index from supplier id to product url
$productResp = finale_fetch_resource($auth, 'resourceProduct');
foreach($productResp->productUrl as $idx => $productUrl) {
    foreach($productResp->supplierList[$idx] as $supplier) {
        if ($supplier->supplierProductId) {
            $supplierProductIdLookup[$supplier->supplierProductId] = $productUrl;
        }
    }
}
echo "Fetch product resource length=".count($productResp->productUrl)."\n";

// Fetch inventory response and create index from facilityUrl and productUrl to quantity on hand
//   - filter out any items with lots and packing to make things simpler
//   - it is possible for facilityUrl and productUrl pairs to appear multiple times so need to add them together
$iiResp = finale_fetch_resource($auth, 'resourceInventoryItem');
foreach($iiResp->facilityUrl as $idx => $facilityUrl) {
    if ($iiResp->quantityOnHand[$idx] and (!$iiResp->normalizedPackingString[$idx] and (!$iiResp->lotId[$idx]))) {
        $quantityLookup[$facilityUrl][$iiResp->productUrl[$idx]] += $iiResp->quantityOnHand[$idx];
    }
}
echo "Fetch inventory item resource length=".count($iiResp->facilityUrl)."\n";

// The inventory variance needs the facilityUrl which we lookup based on the sublocation name
$facilityUrl = $facilityNameLookup[$sublocationName];

// The body of the inventory variance has a few required fields:
//   - facilityUrl to be updated
//   - type which is "FACILITY" for a batch stock change and "FACILITY_COUNT" for a stock take
//   - statusId which is "PHSCL_INV_COMMITTED" the committed status and "PHSCL_INV_INPUT" for the editable/draft state
//   - sessionSecret for CSRF handling (not actually relevant for API calls from server but required since API is shared with browser)
$inventory_variance_body = array(
    "facilityUrl" => $facilityUrl, 
    "physicalInventoryTypeId" => "FACILITY", 
    "statusId" => "PHSCL_INV_COMMITTED",
    "sessionSecret" => $auth['session_secret']
);

// The inventoryItemVarianceList has one entry for each item to change 
//   - quantityOnHandVar is difference from previous value (there is no way to just specify the new value)
//   - productUrl must be specified
//   - facilityUrl for each item must match facilityUrl for inventory variance as a whole
foreach($stockLevelUpdate as $supplierProductId => $quantityOnHand) {
    $productUrl = $supplierProductIdLookup[$supplierProductId];
    $quantityOnHandVar = $quantityOnHand - $quantityLookup[$facilityUrl][$productUrl];
    if ($quantityOnHandVar) {
        $inventory_variance_body["inventoryItemVarianceList"][] = array( 
            "quantityOnHandVar" => $quantityOnHandVar,
            "productUrl" => $productUrl, 
            "facilityUrl" => $facilityUrl 
        );
    }
}


if (count($inventory_variance_body["inventoryItemVarianceList"])) {

    // Post to the top level resource URL to create a new entity
    curl_setopt($auth['curl_handle'], CURLOPT_URL, $auth['host'] . $auth['auth_response']->resourceInventoryVariance);
    curl_setopt($auth['curl_handle'], CURLOPT_POST, true);
    curl_setopt($auth['curl_handle'], CURLOPT_POSTFIELDS, json_encode($inventory_variance_body));
    $result = curl_exec($auth['curl_handle']);
      
    $status_code = curl_getinfo($auth['curl_handle'], CURLINFO_HTTP_CODE);
    if ($status_code != 200) exit("FAIL: inventory variance create error statusCode=$status_code result=$result\n");
    $inventory_variance_create_response = json_decode($result);

    echo "Create inventory variance success count=".count($inventory_variance_create_response->inventoryItemVarianceList)."\n";

} else {
    echo "Do not create inventory variance since there are no quantity changes\n";
}
  
?>

