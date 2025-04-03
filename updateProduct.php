<?php
    use GuzzleHttp\Client; 
    include_once 'credentials.php';
    try{
        if( (isset($_GET['productID']))  && isset($_GET['inventory_qnty'])    )
            {               
                $config = array(
                    'ShopUrl' => @$shopifyStoreUrl,
                    'AccessToken' => $shopifyApiPassword,
                );
                $inventory_qnty=$_GET['inventory_qnty'];
                PHPShopify\ShopifySDK::config($config);
                $shopify = new PHPShopify\ShopifySDK;
                //$productID = '8307274449209';                  
                $productID=$_GET['productID'];                
                // Shopify API credentials
                
                // Guzzle HTTP client
                $client = new Client([
                    'base_uri' => $shopifyStoreUrl,
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'auth' => [$shopifyApiKey, $shopifyApiPassword],
                ]);

                // Fetch product information
                $response = $client->get("/admin/api/2023-04/products/$productID.json");
                $productData = json_decode($response->getBody(), true);
                $price = $productData['product']['variants'][0]['price'];
                if(isset($_GET['price'])) 
                {
                    $price =$_GET['price'];

                }    
                /* getting price end  */
                $ar = [
                    'id' => $productID,
                    'variants' => [
                        [
                            'price'      => $price,
                            'inventory_quantity' => $inventory_qnty

                        ]
                    ]
                ];
                echo '<pre>';
                print_r($ar);
                echo '</pre>';


                $response = $shopify->Product($productID)->put($ar);
                echo '<pre>';
                print_r($response);
                echo '</pre>';
            } 
        }    
        catch (Exception $e)
     {
        $logFile = __DIR__ . '/shopify_errors.log';  // Log file in the same directory as the script
        $currentDateTime = date('Y-m-d H:i:s');      // Get current date and time
        $errorMessage = "[$currentDateTime] Error updating product: " . $e->getMessage() . PHP_EOL;
    
        // Write the error message to the log file
        file_put_contents($logFile, $errorMessage, FILE_APPEND);
    
        // Optionally print the error to the screen
        echo 'Error1: ' . $e->getMessage();
    }
?>