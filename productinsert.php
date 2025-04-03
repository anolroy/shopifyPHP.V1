<?php
use GuzzleHttp\Client; 
include_once 'credentials.php';
include_once 'getRespose.php';
if (isset($_GET['action'])) 
{ 
    $conn = new mysqli($servername, $username, $password, $database);

    $file = fopen('1insert.txt', 'w') or die('Cannot open the file');
    fwrite($file, json_encode($productData, JSON_PRETTY_PRINT));
    fclose($file);
    $stmt->close();
    // $sql = "INSERT INTO stockrecords (
    //     stockID, StockCode, ProductID, Description, taxable, SellPrice, 
    //     variant_id, CreationDateTime,status
    // ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    //     $stmt = $conn->prepare($sql);
    //     foreach ($productData as $data) {
    //                 $stmt->bind_param(
    //                     'issssssss',
    //                     $data['stockID'], $data['sku'], $data['productID'], $data['title'], 
    //                     $data['taxable'], $data['price'], $data['variantID'], $data['creationDateTime'], $data['status']
    //                 );

    //         if ($stmt->execute()) 
    //         {
    //             echo "Inserted new: StockID {$data['stockID']} - {$data['title']}\n";
    //         } 
    //         else 
    //         {
    //             echo "Error: " . $stmt->error . "\n";
    //         }
    //     }
    if ($productData && isset($productData['id'])) {
        $productID = $productData['id'];
        $title = $productData['title'];
        $status = $productData['status'];
        $created_at = $productData['created_at'];
    
        // Prepare SQL for inserting into stockrecords
        $sql = "INSERT INTO stockrecords (
                    stockID, StockCode, ProductID, Description, taxable, SellPrice, 
                    variant_id, CreationDateTime, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
        $stmt = $conn->prepare($sql);
    
        // Loop through product variants and insert into stockrecords
        foreach ($productData['variants'] as $variant) {
            $stockID = $variant['id']; // Use variant ID as stock ID
            $stockCode = $variant['sku'] ?: "NO-SKU"; // Use SKU, or "NO-SKU" if empty
            $taxable = $variant['taxable'] ? 'Yes' : 'No'; // Convert boolean to Yes/No
            $price = $variant['price'];
            $variantID = $variant['id'];
            $creationDateTime = $created_at;
    
            // Bind values and execute statement
            $stmt->bind_param(
                'issssssss',
                $stockID, $stockCode, $productID, $title, 
                $taxable, $price, $variantID, $creationDateTime, $status
            );
    
            if ($stmt->execute()) {
                echo "Inserted: StockID $stockID - $title\n";
            } else {
                echo "Error: " . $stmt->error . "\n";
            }
        }
       
        // Close statement
        $stmt->close();
    } else {
        echo "Invalid Webhook Data";
    }
    
    // Close connection
    $conn->close();         
}
?>
