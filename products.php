<?php
require_once 'vendor/autoload.php'; // Include Composer autoloader
use GuzzleHttp\Client; 
include_once 'credentials.php';

function fetchShopifyProducts($shopifyStoreUrl, $shopifyApiPassword) {
    echo "fetchShopifyProducts function called";
    $client = new Client([
        'base_uri' => "https://$shopifyStoreUrl/admin/api/2023-10/graphql.json",
    ]);
   
    $query = <<<'GQL'
    {
        products(first: 250) {
            edges {
                node {
                    id
                    title
                    descriptionHtml
                    status
                    variants(first: 15) {
                        edges {
                            node {
                                id
                                price                                
                                sku
                                taxable                                   
                            }
                        }
                    }
                }
            }
        }
    }
    GQL;

    $response = $client->request('POST', '', [
        'headers' => [
            'X-Shopify-Access-Token' => $shopifyApiPassword,
            'Content-Type' => 'application/json',
        ],
        'json' => ['query' => $query],
    ]);
    $responseBody = $response->getBody()->getContents();    
    $log = fopen('stockRecords.json', 'w') or die('Cannot open the file');
    fwrite($log, $responseBody);
    fclose($log); 

    $body = json_decode($response->getBody(), true);
    return $body['data']['products']['edges'] ?? [];
}

if (isset($_GET['action'])) { 
    try {
       // echo $shopifyStoreUrl;
        //echo $shopifyApiPassword;
        echo ($shopifyStoreUrl);
        echo $shopifyApiPassword;
        $products = fetchShopifyProducts($shopifyStoreUrl, $shopifyApiPassword);
        // $productLog = fopen('1Product.json', 'w') or die('Cannot open the file');
        // fwrite($productLog, json_encode($products, JSON_PRETTY_PRINT));
        // fclose($productLog);
        // Database connection
        $conn = new mysqli($servername, $username, $password, $database);
        if ($conn->connect_error) {
            $logFile = __DIR__ . '/shopify_errors.log';  // Log file in the same directory as the script
            $currentDateTime = date('Y-m-d H:i:s');      // Get current date and time
            $errorMessage = "[$currentDateTime] Error: ". $conn->connect_error ;
        
            // Write the error message to the log file
            file_put_contents($logFile, $errorMessage, FILE_APPEND);
        
            // Optionally print the error to the screen
            echo 'Error1: '. $conn->connect_error;
            die('Connection failed: ' . $conn->connect_error);
        }
        $id = uniqid(); // Generate a unique ID for the record
        $module = 'stockrecords'; // Module name
        $sql = "SELECT MAX(datecalled) AS last_called FROM hooklog WHERE module = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $module);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row && $row['last_called']) {
            $lastCalled = strtotime($row['last_called']); // Convert to timestamp
            $currentTime = time(); // Current timestamp

            if (($currentTime - $lastCalled) <= 30) {
                echo "The module 'Product' was called recently. Skipping 'createfruite' function.";
                $stmt->close();
                $conn->close();
                exit; // Exit script to avoid calling the function
            }
        }

        $dateCalled = date('Y-m-d H:i:s'); // Current datetime

        // Prepare the SQL statement
        $conn->begin_transaction();
        $sql = "INSERT INTO hooklog (id, module, datecalled) VALUES (?, ?, ?)";

        // Prepare and bind
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $id, $module, $dateCalled);

        // Execute the statement
        if ($stmt->execute()) {
            echo "Record inserted successfully.";
        } else {
            echo "Error: " . $stmt->error;
        }
        // Close the statement and connection
        $stmt->close();
        
        $processedProductIDs = []; 
        $productData = [];
        
        // First, get all existing variants from the database
        $existingVariants = [];
        $getExistingSQL = "SELECT variant_id, StockCode FROM stockrecords";
        $result = $conn->query($getExistingSQL);
        
        while ($row = $result->fetch_assoc()) {
            $existingVariants[$row['variant_id']] = $row['StockCode'];
        }
        
        // Collect all products with status 'DRAFT'
        $draftProductIDs = [];
        foreach ($products as $productEdge) {
            $product = $productEdge['node'];
            if ($product['status'] === 'DRAFT') {
                $productID = str_replace('gid://shopify/Product/', '', $product['id']);
                $draftProductIDs[] = $productID;
            }
        }
        
        // Delete all variants of 'DRAFT' products in one query
        if (!empty($draftProductIDs)) {
            // Create placeholders for the IN clause
            $placeholders = implode(',', array_fill(0, count($draftProductIDs), '?'));
            $sqlDelete = "DELETE FROM stockrecords WHERE ProductID IN ($placeholders)";
            $stmtDelete = $conn->prepare($sqlDelete);
        
            // Dynamically bind the parameters
            $types = str_repeat('s', count($draftProductIDs));
            $stmtDelete->bind_param($types, ...$draftProductIDs);
        
            if ($stmtDelete->execute()) {
                echo "Deleted " . $stmtDelete->affected_rows . " draft products from stockrecords.\n";
            } else {
                echo "Error deleting draft products: " . $stmtDelete->error . "\n";
            }
        
            $stmtDelete->close();
        }
        
   

        foreach ($products as $productEdge) {
            $product = $productEdge['node'];
            $productID = str_replace('gid://shopify/Product/', '', $product['id']);
            $title = $product['title'];
            $productStatus = $product['status'];
            if ($productStatus === 'ACTIVE') {
                    if (!empty($product['variants']['edges'])) {
                        foreach ($product['variants']['edges'] as $variantEdge) {
                            $variant = $variantEdge['node'];
                            $variantID = str_replace('gid://shopify/ProductVariant/', '', $variant['id']);
                            // Set default SKU if null or empty
                            //$sku = !empty($variant['sku']) ? $variant['sku'] : 'SKU-' . $variantID;
                            $sku = !empty($variant['sku']) ? $variant['sku'] : '';
                            $price = $variant['price'];
                            $taxable = $variant['taxable'];
                            
                            $currentDateTime = date('Y-m-d H:i:s');

                            // Check if variant exists and if SKU has changed
                            if (array_key_exists($variantID, $existingVariants)) 
                            {
                                // Only update if SKU is not null and has changed
                                if (!empty($sku) && $existingVariants[$variantID] !== $sku) {
                                    $updateSQL = "UPDATE stockrecords SET 
                                        StockCode = ?,
                                        Description = ?,
                                        taxable = ?,
                                        SellPrice = ?,
                                        status = ?
                                        WHERE variant_id = ?";
                                    
                                    $updateStmt = $conn->prepare($updateSQL);
                                    $updateStmt->bind_param(
                                        'ssssss',
                                        $sku,
                                        $title,
                                        $taxable,
                                        $price,
                                        $productStatus,
                                        $variantID
                                    );
                                    
                                    if ($updateStmt->execute()) {
                                        echo "Updated variant {$variantID} with new SKU: {$sku} (old SKU: {$existingVariants[$variantID]})\n";
                                    } else {
                                        echo "Error updating variant: " . $updateStmt->error . "\n";
                                    }
                                    $updateStmt->close();
                                }
                            } 
                            else 
                            {
                                // Add new product/variant to productData array for insertion
                                $productData[] = [
                                    'stockID' => time() . rand(1000, 9999),
                                    'sku' => $sku,
                                    'productID' => $productID,
                                    'title' => $title,
                                    'taxable' => $taxable,
                                    'price' => $price,
                                    'status' => $productStatus,
                                    'variantID' => $variantID,
                                    'creationDateTime' => $currentDateTime,
                                ];
                            }
                        }
                    }
                }     
        }

        // Insert only new products
        if (!empty($productData)) {
            // Step 1: Count ProductID occurrences
            $productIDCounts = [];
            foreach ($productData as $data) {
                $pid = $data['productID'];
                $productIDCounts[$pid] = ($productIDCounts[$pid] ?? 0) + 1;
            }
        
            // Step 2: Prepare multi-row insert
            $sql = "INSERT INTO stockrecords (
                stockID, StockCode, ProductID, Description, taxable, SellPrice,
                variant_id, CreationDateTime, status, hasVariant
            ) VALUES ";
        
            $placeholders = [];
            $types = '';
            $values = [];
        
            foreach ($productData as $data) {
                $hasVariant = ($productIDCounts[$data['productID']] > 1) ? 1 : 0;
                $placeholders[] = "(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
                // Append the correct types: i = int, s = string
                $types .= 'issssssssi';
        
                $values[] = $data['stockID'];
                $values[] = $data['sku'];
                $values[] = $data['productID'];
                $values[] = $data['title'];
                $values[] = $data['taxable'];
                $values[] = $data['price'];
                $values[] = $data['variantID'];
                $values[] = $data['creationDateTime'];
                $values[] = $data['status'];
                $values[] = $hasVariant;
            }
        
            $sql .= implode(', ', $placeholders);
        
            // Step 3: Prepare and bind
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                die("Prepare failed: " . $conn->error);
            }
        
            // mysqli bind_param requires references
            $bindValues = [];
            $bindValues[] = & $types;
        
            foreach ($values as $key => $val) {
                $bindValues[] = & $values[$key];  // pass by reference
            }
        
            call_user_func_array([$stmt, 'bind_param'], $bindValues);
        
            if ($stmt->execute()) {
                echo "✅ Inserted " . $stmt->affected_rows . " rows successfully.\n";
            } else {
                echo "❌ Error inserting: " . $stmt->error . "\n";
            }
        
            $stmt->close();
        }
        
       
       
        $conn->commit(); 
        $conn->close();

        http_response_code(200); // Send HTTP 200 status code
        echo json_encode(['message' => 'GraphQL request processed successfully.']);
    }
    catch (Exception $e) {
        $logFile = __DIR__ . '/shopify_errors.log'; 
        $currentDateTime = date('Y-m-d H:i:s'); 
        $errorMessage = "[$currentDateTime] Error: " . $e->getMessage() . PHP_EOL;
        $conn->rollback();
        file_put_contents($logFile, $errorMessage, FILE_APPEND);
    
        http_response_code(500); // Send HTTP 500 status code for errors
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>

