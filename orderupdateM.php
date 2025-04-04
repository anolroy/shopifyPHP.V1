<?php
set_time_limit(300);
include_once 'credentials.php';

//echo "Hello world  ";
//echo isset($_GET['action']);
if (isset($_GET['action'])) {
    // Insert order data into database
    // Here in this page we shall handle all returns 
    $file = $_GET['file'] ?? null;

    // Define the file path (ensure it's a safe directory)
    if ($isLocalServer) {
        $filePath = 'C:\\Development\\Shopify App\\archive\\' .  $file;
    } else {
        echo "public server"."<br>";
        //$filePath = '/home/pearlcol/public_html/archive/' .  $file;
        $filePath = '/home/storage/588/4501588/user/htdocs/archive/' .  $file;        
        echo $filePath ."<br>";
    }  
   
    // Check if the file exists and is readable
    if ($file && file_exists($filePath)) {
        // Read the file content
        $jsonData = file_get_contents($filePath);

        // Decode JSON into an associative array
        $order_data = json_decode($jsonData, true);
    } else {
        die('File not found.');
    }
    $document_id = $order_data['id'];
    $order_id = str_replace("#", " ", $order_data['name']);
    $created_at = str_replace("T", " ", $order_data['created_at']);
    $currency = $order_data['currency'];
    //$TotalOrder = $order_data['total_price'];                
    $orderNumber  = null; //$order_data['order_number'];
    $orderDate = date('Y-m-d H:i:s', strtotime($order_data['processed_at'])) ?? '';
    //$HeaderDiscountTotal= $order_data['current_total_discounts'];
    $HeaderDiscountTotal = 0;
    $creationDateTime = date('Y-m-d H:i:s') ?? '';
    $TransactionType = 2;
    $TransactionTypeDesc = "";
    $module="OrderUpdateM";
    $conn = new mysqli($servername, $username, $password, $database);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $dateCalled = date('Y-m-d H:i:s'); // Current datetime
    $idhook = $document_id; // Generate a unique ID for the record
   
    // Prepare the SQL statement
    // we do not need Order Update in 'Order Update' because this document modifies several time and allowed to come multiple times
    //1030-R1 in one orderupdate 1030 -R2 in orderupdate. where theier Id is same. so we cannot check duplicate on document id

    $sql = "INSERT INTO hooklog (id, module, datecalled) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $idhook, $module, $dateCalled);
    if ($stmt->execute()) {
        echo "Record inserted successfully..<br>";
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();


    //**** disocunt header */
    if (!empty($order_data['discount_codes'])) {
        foreach ($order_data['discount_codes'] as $allocation) {
            $discount_code = $allocation['code'];
            $discount_amount =  $allocation['amount'];
            $discount_type = $allocation['type'];
            // You can decide how to handle multiple discounts (sum them, use the largest, etc.)
        }
    }


    //end  discount header


    //*************************************** */ Insert line return items data into database******************************************
    $paymentTermsName = null;
    $shipping_tax = 0;
    if (empty($order_data['refund_line_items']) && empty($order_data['refund_line_items'])) {
        // Extract and print payment_terms_name
        if (isset($order_data['payment_terms']['payment_terms_name'])) {
            $paymentTermsName = $order_data['payment_terms']['payment_terms_name'];
        };
    }

    if (isset($order_data['payment_terms']['payment_schedules'])) {
        $paymentSchedules = $order_data['payment_terms']['payment_schedules'];

        // Iterate through each schedule and insert into the database
        foreach ($paymentSchedules as $schedule) {
            $document_id = $conn->real_escape_string($schedule['id']);
            $currency = $conn->real_escape_string($schedule['currency']);
            $created_at = $conn->real_escape_string($schedule['created_at']);
            $amount = $conn->real_escape_string($schedule['amount']);
            $type = 5;
            //there lines creating duplicate records. So I rem it for now 2024-12-19
            // $query = "INSERT INTO Receipt (id,order_id, type,currency,message, created_at, amount,ReceiptDownloaded) VALUES
            //  ('$document_id','$order_id','$type', '$currency', '$paymentTermsName','$created_at', '$amount',0)";                
            // if ($conn->query($query) === TRUE) {
            //     echo "Inserted schedule ID: " . $order_id . "\n";
            // } else {
            //     echo "Error: " . $conn->error . "\n";
            // }
        }
    }

    $Ttotal_tax = 0;
    $taxAmount = 0;
    $productIds = [];
    // Check if 'line_items' key exists
    if (isset($order_data['line_items'])) {
        $OrderLineitemNode = $order_data['line_items'];
    }
    if (isset($order_data['discount_applications'])) {
        $discountInfo = $order_data['discount_applications'];
    }
    //No return no refund
    $TransactionType = 0; // Default value

    if (isset($order_data['refunds']) && is_array($order_data['refunds'])) {
        foreach ($order_data['refunds'] as $refund) {
            if (isset($refund['refund_line_items']) && !empty($refund['refund_line_items'])) {
                $hasRefundLineItems = true;
                $restock_type = $refund['restock']; // if this is false there is a return associated with refund . true means refund with line items only
                if ($restock_type == false) {
                    $TransactionType = 2;
                    $TransactionTypeDesc = "Sales Return Refund";
                } elseif ($restock_type == true) {
                    $TransactionType = 4;
                    $TransactionTypeDesc = "Refund with item lines";
                }
            } else {
                $TransactionType = 3;
                $TransactionTypeDesc = "without line items Refund";
            }
            //$TransactionTypeDesc = null;
            $SourcePHP = "orderupdate.php - " . $filename;
            $SourceID = ""; //$order_data['payment_id'];


            if (isset($refund['transactions']) && is_array($refund['transactions'])) {
                foreach ($refund['transactions'] as $transaction) {
                    $orderNumber = str_replace('#', '', $transaction['payment_id'] ?? '');
                    $parts = explode('.', (string) $orderNumber);
                    if (isset($parts[1])) {
                        $fractionalValue = $parts[1] - 1;
                        $orderNumberExt = (int) $orderNumber . "-R" . $fractionalValue;
                    } else {
                        $orderNumberExt = (int) $orderNumber;
                    }
                    if (!empty($orderNumber)) {
                        //$orderNumber = (int) $orderNumber;
                        if ($transaction['kind'] == "refund") {
                            //$TotalOrder = -1 * ($transaction['amount'] ?? 0);
                            $TotalOrder = ($transaction['amount'] ?? 0);
                        } else {
                            $TotalOrder =  ($transaction['amount'] ?? 0);
                        }
                        $currency = $transaction['currency'] ?? null;
                        $document_id = $transaction['id'] ?? null;
                        $order_id = $order_data['id'] ?? null;
                        $gateway = $transaction['gateway'] ?? null;
                        $message =  $transaction['message'] ?? null;
                        $refund_id = $refund['id'];


                        // Check if receipt already exists
                        $sqlCheck = "SELECT COUNT(*) as count FROM Receipt WHERE order_id = '$orderNumberExt'";
                        $resultCheck = $conn->query($sqlCheck);
                        if ($resultCheck) {
                            $row = $resultCheck->fetch_assoc();
                            if ($row['count'] > 0) { //if (false) { //
                                echo "Receipt already exists for order ID: $order_id\n";
                            } else {
                                if ($TransactionType == 4) {
                                    foreach ($refund['refund_line_items'] as $refundTax) {
                                        if ($refund_id == $refund['id']) {
                                            $taxAmount = $taxAmount + $refundTax['total_tax'];
                                        }
                                    }
                                }
                                if ($switchorderupdateM == 0) {
                                    $sql = "
                                    INSERT INTO Receipt (id, order_id, currency, payment_id,TransactionTypeDesc,SourcePHP,SourceID, created_at, gateway, message, Type, TotalTax, amount, ReceiptDownloaded)
                                    VALUES ('$document_id', '$orderNumberExt', '$currency', '$refund_id', '$TransactionTypeDesc','$SourcePHP','$SourceID','$created_at', '$gateway', '$message', '$TransactionType','$taxAmount', '$TotalOrder', 0)
                                    ";
                                    $stmt = $conn->query($sql);
                                    if (!$stmt) {
                                        die('Insert failed: ' . $conn->error);
                                    }
                                    echo "Receipt inserted successfully.\n";
                                } else {
                                    $receiptData = [
                                        'id' => $document_id,
                                        'order_id' => $orderNumberExt,
                                        'currency' => $currency,
                                        'payment_id' => $refund_id,
                                        'TransactionTypeDesc' => $TransactionTypeDesc,
                                        'SourcePHP' => $SourcePHP,
                                        'SourceID' => $SourceID,
                                        'created_at' => $created_at,
                                        'gateway' => $gateway,
                                        'message' => $message,
                                        'Type' => $TransactionType,
                                        'TotalTax' => $taxAmount,
                                        'amount' => $TotalOrder,
                                        'ReceiptDownloaded' => 0
                                    ];

                                    $existingData = [];
                                    if (file_exists('1receipt.json')) {
                                        $existingJson = file_get_contents('1receipt.json');
                                        $existingData = json_decode($existingJson, true);
                                        if (!is_array($existingData)) {
                                            $existingData = [$existingData];
                                        }
                                    }
                                    $existingData[] = $receiptData;
                                    file_put_contents('1receipt.json', json_encode($existingData, JSON_PRETTY_PRINT));
                                }
                            }
                        }
                        $taxAmount = 0;
                        //get Tax amount, added 2024-12-19. This part I am going to rem because. Tax is coming for total. It should be item wise.
                        $orderId = $order_data['id'];
                    }
                }
            }
        }
    }
    // Determine TransactionType based on the conditions

    //after this line are going to update the shipping and main order
    echo "after this line are going to update the shipping and main order" ."<br>";
        $OrderDiscount= $order_data['current_total_discounts'];
        $shippingCharge= $order_data['total_shipping_price_set']['shop_money']['amount'];
        $OrderHeaderId= $order_data['id']; 
        $city ='';
        $zip ='';
        $country ='';
        $address1 ='';
        $address2 ='';  
        $deliveryDateEarliest = null;
        $sourcePHP="orderupdateM.php - ".$file;
    if (isset($order_data['shipping_lines'])) 
               {
                    foreach ($order_data['shipping_lines'] as $shippingLine) 
                    {
                        $ChargeAmount=$shippingLine['price'];
                        $Description=$shippingLine['title'];
                        $TransactionType=1;
                        $ShippingChargeID=$shippingLine['id'];
                        $sql = "Delete from  Shippingdetails where ShippingChargeID='$ShippingChargeID'";
                        $result = $conn->query($sql); 
                        if (isset($shippingLine['tax_lines'])){
                            foreach ($shippingLine['tax_lines'] as $taxLine) 
                            {
                                if (isset($taxLine['price'])) {
                                    //echo "Tax Price: " . $taxLine['price'] . "\n";
                                        $shipping_tax=$shipping_tax+ $taxLine['price'];                                                                        
                                        if ($switchorderupdateM == 0) {
                                            $sql = "INSERT INTO Shippingdetails (ShippingChargeID ,OrderHeaderId , OrderNumber ,TransactionType ,Description, ChargeAmount, TaxAmount)
                                            VALUES ('$ShippingChargeID' ,'$OrderHeaderId' ,'$orderNumber' ,'$TransactionType' ,'$Description', '$ChargeAmount', '$shipping_tax')";
                                            $result = $conn->query($sql);
                                        } else {
                                            $shippingData = array(
                                                'ShippingChargeID' => $ShippingChargeID,
                                                'OrderHeaderId' => $OrderHeaderId,
                                                'OrderNumber' => $orderNumber,
                                                'TransactionType' => $TransactionType,
                                                'Description' => $Description,
                                                'ChargeAmount' => $ChargeAmount,
                                                'TaxAmount' => $shipping_tax
                                            );

                                            // Read existing content if file exists
                                            $existingContent = [];
                                            if (file_exists('1shippingDetails.json')) {
                                                $existingContent = json_decode(file_get_contents('1shippingDetails.json'), true);
                                            }

                                            // Add new shipping data
                                            $existingContent[] = $shippingData;

                                            // Write back to file
                                            file_put_contents('1shippingDetails.json', json_encode($existingContent, JSON_PRETTY_PRINT));
                                        }
                                }
                            }
                        }
                    }
                }  


                //******shipping data ends */

            $order = $order_data;
            $orderNumber = $order['order_number']?? '';
            $orderDate = date('Y-m-d H:i:s', strtotime($order['created_at']))?? '';
            //$invoiceName = ($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '');
            //$invoiceAddress1 = "Address Placeholder"?? ''; // No address in the JSON, so placeholder
            $customer = $order['customer'];
            $shipping_address = $order['shipping_address'];
            $DeliveryAddress5=null;
            if (!empty($shipping_address)){
                $DeliveryName =$shipping_address['name']?? ''; // No address in the JSON, so placeholder
                $DeliveryAddress1 =$shipping_address['address1']?? '';
                $DeliveryAddress2=$shipping_address['address1']?? '';
                $DeliveryAddress3 =$shipping_address['address2']?? '';
                $DeliveryAddress4=$shipping_address['city'].'  '.$shipping_address['province'].'  '.$shipping_address['country']?? '';
                $DeliveryPostcode = $shipping_address['zip']?? '';
                $DeliveryTelephone_number =$shipping_address['phone']?? '';
            }            
            // $invaddressp1=$customer['default_address'];
            // if (!empty($invaddressp1)){
            //         $invoiceAddress1=$invaddressp1['first_name']?? ''.'  '.$invaddressp1['last_name']?? ''; // No address in the JSON, so placeholder
            //          $invoiceAddress2=$invaddressp1['company']?? '';
            //          $invoiceAddress3 =$invaddressp1['address1']?? '';
            //          $invoiceAddress4 =$invaddressp1['address2']?? '';
            //          $invoicePostcode=$invaddressp1['zip']?? '';
            //         //$DeliveryAddress5=$invaddressp1['city'].'  '.$invaddressp1['province'].'  '.$invaddressp1['country']?? '';
            // }
            $invaddressp1=$order_data['billing_address'];
            if (!empty($invaddressp1)){
                    $InvoiceName=$invaddressp1['first_name']?? ''.'  '.$invaddressp1['last_name']?? ''; // No address in the JSON, so placeholder
                     $invoicecompany=$invaddressp1['company']?? '';
                     $company=$invaddressp1['company']?? '';
                     $invoiceAddress1 =$invaddressp1['address1']?? '';//$invoiceAddress1
                     $invoiceAddress2 =$invaddressp1['address2']?? '';
                     $invoicePostcode=$invaddressp1['zip']?? '';
                     $invoiceAddress3=$invaddressp1['city'].'  '.$invaddressp1['province'].'  '.$invaddressp1['country']?? '';
            }

           
            $emailAddress = $customer['email'];
            $locationCode = $order['location_id']?? '';
          
            $total_tax = $order['total_tax']?? '';
            $currencyCode = $order['currency']?? '';
           // $comments = "Generated from Shopify API"?? '';
            $comments =  $order['note'];
            $creationDateTime = date('Y-m-d H:i:s')?? '';
            $paymentMethod = null;//$order['financial_status']?? ''; // Mapping financial status as payment method
            $fulfillment_status = $order['fulfillment_status']?? '';
            $financial_status = $order['financial_status']?? '';
            $orderamount=$order['current_total_price'];
            $orderGrossTotal = $order['total_price']?? '';
            
            $updated_at=$order['updated_at'];
            $OrderHeaderId= $order_data['id']; 
            $discount_code=null;
            $discount_amount=null;
            $discount_type=null;
            $discount_target_type=null;
            $discount_value=null;
            $discount_allocation_method=null;
            $discount_target_selection=null;
        //**** disocunt header */
                if (!empty($order['discount_codes'])) {
                    foreach ($order['discount_codes'] as $allocation) {
                       
                        $discount_code = $allocation['code'];
                        $discount_amount =  $allocation['amount'];
                        $discount_type = $allocation['type'];
                        
                        // You can decide how to handle multiple discounts (sum them, use the largest, etc.)
                    }
                }

                foreach ($order_data['line_items'] as $line_item) {
                    if (!empty($line_item['discount_allocations'])) {
                        foreach ($line_item['discount_allocations'] as $allocation) {                           
                                    $index = $allocation['discount_application_index'];                    
                                    // Fetch the corresponding discount application
                              $discount_application = $order_data['discount_applications'][$index]; 
                              if ($discount_application['target_selection']=='all'){                      
                                            // Extract the relevant discount details
                                            $discount_target_type = $discount_application['target_type'];
                                        // $discount_type =  $discount_application['type']; this shall not come
                                            $discount_value = $discount_application['value'];// this shall be inserted in value field
                                           // $discount_type = $discount_application['value_type']; no need
                                            $discount_allocation_method = $discount_application['allocation_method'];
                                            $discount_target_selection =$discount_application['target_selection'];                                            
                                    }
                            // You can decide how to handle multiple discounts (sum them, use the largest, etc.)
                        }
                    }
                }
      //end  discount header
                $shipping_tax=0;
               //geting shipping header
               $conn = new mysqli($servername, $username, $password, $database);
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            } 


            $module = 'Sales Order';
            $checkSql = "SELECT OrderHeaderId FROM orderheaders WHERE OrderNumber = ? and transactionType=1";
                    $stmt = $conn->prepare($checkSql);
                    $stmt->bind_param('i', $orderNumber);
                    $stmt->execute();
                    $stmt->store_result();
                    
                    
                    // If order exists, skip insertion // 0 means SQL MODE and 1 means JSON MODE
                    if ($switchorderupdateM == 0 && $stmt->num_rows > 0 ) {
                        echo "Duplicate order received. Skipping insertion for Order ID: " . $orderNumber;
                        $stmt->close();
                    } else {
                        // $sql = "UPDATE Receipt R, orderheaders O SET R.order_id = O.OrderNumber  WHERE O.OrderHeaderId = R.id and LENGTH(O.OrderHeaderId)>20 AND ((R.order_id)='' or isnull(R.order_id))";
                        // if ($conn->query($sql) === TRUE) {
                        //     echo "Records updated successfully.";
                        // } 
                        // else
                        // {
                        // echo "Error updating records: " . $conn->error;
                        // }      
                    $transactionType=1;
                    $transactionTypeDesc="Sales Order";   
                    $orderGrossTotal= $orderamount+ $shippingCharge+ $shipping_tax;  
                    
                    echo "after this line INSERT INTO orderheaders " ."<br>";
                    if ($switchorderupdateM == 0) {
                        $sql = "INSERT INTO orderheaders (
                            OrderHeaderId, OrderNumber, OrderDate, DeliveryDateEarliest,TransactionType,transactionTypeDesc,InvoiceName, company,DeliveryName,InvoiceAddress1, EmailAddress, CurrencyCode, OrderGrossTotal, Comments,
                            CreationDateTime, lastModifiedDateTime,  fulfilled,  financial_status,  orderamount, OrderDiscount,shippingCharge,
                            Code, Amount, Type, target_type, value, allocation_method, target_selection, total_tax,InvoiceAddress2,InvoiceAddress3, 
                            InvoiceAddress4, InvoicePostcode, DeliveryAddress1, DeliveryAddress2, DeliveryAddress3, DeliveryAddress4, DeliveryAddress5,DeliveryPostcode, DeliveryTelephone_number,shipping_tax,sourcePHP                   
                        ) VALUES ('$OrderHeaderId', '$orderNumber', '$orderDate', '$deliveryDateEarliest', '$transactionType','$transactionTypeDesc','$InvoiceName',' $company','$DeliveryName', '$invoiceAddress1','$emailAddress', '$currencyCode', '$orderGrossTotal', '$comments', 
                            '$creationDateTime', '$updated_at', '$fulfillment_status', '$financial_status', '$orderamount', '$OrderDiscount', '$shippingCharge', 
                            '$discount_code', '$discount_amount', '$discount_type','$discount_target_type', '$discount_value',' $discount_allocation_method', '$discount_target_selection','$total_tax','$invoiceAddress2','$invoiceAddress3', 
                            '$invoiceAddress4', '$invoicePostcode', '$DeliveryAddress1', '$DeliveryAddress2', '$DeliveryAddress3', '$DeliveryAddress4', '$DeliveryAddress5','$DeliveryPostcode','$DeliveryTelephone_number','$shipping_tax','$sourcePHP' )";
                        $result = $conn->query($sql);
                        if ($result) {
                            echo "Order header record inserted successfully.<br>";
                        } else {
                            echo "Error inserting order header record: " . $conn->error . "<br>";
                            $logFile = __DIR__ . '/shopify_errors.log';  // Log file in the same directory as the script                
                            $currentDateTime = date('Y-m-d H:i:s');      // Get current date and time
                            $errorMessage = "[$currentDateTime] Error: ". $conn->connect_error ;            
                            // Write the error message to the log file
                            file_put_contents($logFile, $errorMessage, FILE_APPEND);               
                            die('Error inserting order header record: ' . $conn->error);
                        }
                        $stmt->close();
                    } else {
                        $orderHeaderData = array(
                            'OrderHeaderId' => $OrderHeaderId,
                            'OrderNumber' => $orderNumber,
                            'OrderDate' => $orderDate,
                            'DeliveryDateEarliest' => $deliveryDateEarliest,
                            'TransactionType' => $transactionType,
                            'transactionTypeDesc' => $transactionTypeDesc,
                            'InvoiceName' => $InvoiceName,
                            'company' => $company,
                            'DeliveryName' => $DeliveryName,
                            'InvoiceAddress1' => $invoiceAddress1,
                            'EmailAddress' => $emailAddress,
                            'CurrencyCode' => $currencyCode,
                            'OrderGrossTotal' => $orderGrossTotal,
                            'Comments' => $comments,
                            'CreationDateTime' => $creationDateTime,
                            'lastModifiedDateTime' => $updated_at,                    
                            'fulfilled' => $fulfillment_status,
                            'financial_status' => $financial_status,
                            'orderamount' => $orderamount,
                            'OrderDiscount' => $OrderDiscount,
                            'shippingCharge' => $shippingCharge,
                            'Code' => $discount_code,
                            'Amount' => $discount_amount,
                            'Type' => $discount_type,
                            'target_type' => $discount_target_type,
                            'value' => $discount_value,
                            'allocation_method' => $discount_allocation_method,
                            'target_selection' => $discount_target_selection,
                            'total_tax' => $total_tax,
                            'InvoiceAddress2' => $invoiceAddress2,
                            'InvoiceAddress3' => $invoiceAddress3,
                            'InvoiceAddress4' => $invoiceAddress4,
                            'InvoicePostcode' => $invoicePostcode,
                            'DeliveryAddress1' => $DeliveryAddress1,
                            'DeliveryAddress2' => $DeliveryAddress2,
                            'DeliveryAddress3' => $DeliveryAddress3,
                            'DeliveryAddress4' => $DeliveryAddress4,
                            'DeliveryAddress5' => $DeliveryAddress5,
                            'DeliveryPostcode' => $DeliveryPostcode,
                            'DeliveryTelephone_number' => $DeliveryTelephone_number,
                            'shipping_tax' => $shipping_tax,
                            'sourcePHP' => $sourcePHP
                        );

                        // Read existing content if file exists
                        $existingContent = [];
                        if (file_exists('1Orderheader.json')) {
                            $existingContent = json_decode(file_get_contents('1Orderheader.json'), true);
                        }

                        // Add new order header data
                        $existingContent[] = $orderHeaderData;

                        // Write back to file
                        file_put_contents('1Orderheader.json', json_encode($existingContent, JSON_PRETTY_PRINT));
                    }
                    $index = null;                                
                    $discount_application = null;                              
                    $discount_target_type = null;
                    $discount_type = null;
                    $discount_value = null;;
                    $discount_value_type = null;;
                    $discount_allocation_method =null;;
                    $discount_target_selection =null;;
                //*************************************** */ Insert line items data into database******************************************
                $LineNumber=1;
                foreach ($order_data['line_items'] as $line_item) {
                    $line_item_id = $line_item['product_id'];
                    $current_quantity = $line_item['current_quantity'];
                    $name = $line_item['name'];
                    $price = $line_item['price'];
                    // Check if $price is null and make it zero
                    if (is_null($price)) {
                        $price = 0;
                    }
                    // $total_discount = $line_item['total_discount'];
                    // // Check if $total_discount is null and make it zero
                    // if (is_null($total_discount)) {
                    //     $total_discount = 0;
                    // }
                    $total_discount = 0;
                    foreach ($line_item['discount_allocations'] as $discount_allocations) {
                        $total_discount =$total_discount + $discount_allocations['amount'];
                    }


                    $variant_id = $line_item['variant_id'];
                    $vendor = $line_item['vendor'];
                    $sku=$order_data['line_items'][$LineNumber-1]['sku'];
                    $taxable=($line_item['taxable'] == 1) ? 1 : 0;;
                    
                    $discount_target_type = null;
                    $discount_type = null;
                    $discount_value = null;
                    $discount_value_type = null;
                    $discount_allocation_method = null;
                    $discount_target_selection = null; 
                    $tax_line_price=0;
                    foreach ($line_item['tax_lines'] as $tax_lines) { 
                        if (isset($tax_lines['price']) ) {                    
                                $tax_line_price= $tax_lines['price'];
                            
                        } 
                    }

                    // Calculate Unit Tax Value
                    $unitTaxValue = $tax_line_price / $current_quantity;

                    // Calculate Unit Line Discount Value
                    $unitLineDiscountValue = $total_discount / $current_quantity;

                    // Calculate Unit Price After Line Discount
                    $unitPriceAfterLineDiscount = $price - $unitLineDiscountValue;

                    // Calculate Unit Price After Tax using the new formula
                    $unitPriceAfterTax = $unitPriceAfterLineDiscount - $unitTaxValue;

                    // Check if discounts exist for this line item
                    if (!empty($line_item['discount_allocations'])) {
                        foreach ($line_item['discount_allocations'] as $allocation) {
                            $index = $allocation['discount_application_index'];
                                    // Fetch the corresponding discount application
                            $discount_application = $order_data['discount_applications'][$index];
                                    // Extract the relevant discount details
                            if ($discount_application['target_selection']!=='all'){   
                                $discount_target_type = $discount_application['target_type'];
                                $discount_type = $discount_application['type'];
                                $discount_value = $discount_application['value'];
                                $discount_value_type = $discount_application['value_type'];
                                $discount_allocation_method = $discount_application['allocation_method'];
                                $discount_target_selection = $discount_application['target_selection'];
                                echo $discount_target_type;
                                echo $discount_type;
                            }
                            // You can decide how to handle multiple discounts (sum them, use the largest, etc.)
                        }
                    }
                    $uniqueID = $line_item['id'];
                    echo "after this line INSERT INTO orderdetails " ."<br>";
                    if ($switchorderupdateM == 0) {
                        $sql = "INSERT INTO orderdetails (
                            uniqueID, orderheaderID, orderdetailsID, ordernumber, TransactionType, transactionTypeDesc, productid, Description, stockcode, UnitNettPrice, Quantitysold,  taxable, target_type, type, value,
                            value_type, allocation_method, target_selection, LineDiscountValue, UnitLineDiscountValue, tax_lines, UnitPriceAfterLineDiscount, UnitPriceAfterTax, UnitTaxValue
                        ) VALUES (
                            '$uniqueID', '$OrderHeaderId', '$LineNumber', '$orderNumber', '$transactionType', '$transactionTypeDesc', '$line_item_id', '$name', '$sku', '$price', '$current_quantity',  '$taxable',
                            '$discount_target_type', '$discount_type', '$discount_value', '$discount_value_type', '$discount_allocation_method', '$discount_target_selection', '$total_discount', '$unitLineDiscountValue', '$tax_line_price',
                            '$unitPriceAfterLineDiscount', '$unitPriceAfterTax', '$unitTaxValue'
                        )";

                        if ($conn->query($sql) === TRUE) {
                            echo "Line item record inserted successfully.<br>";
                        } else {
                            echo "Error inserting line item record: " . $conn->error . "<br>";
                            $logFile = __DIR__ . '/shopify_errors.log';  // Log file in the same directory as the script                
                            $currentDateTime = date('Y-m-d H:i:s');      // Get current date and time
                            $errorMessage = "[$currentDateTime] Error: ". $conn->connect_error ;            
                            // Write the error message to the log file
                            file_put_contents($logFile, $errorMessage, FILE_APPEND);               
                            die('Error inserting line item record: ' . $conn->error);
                        }
                    } else {
                        $orderDetails = array(
                            'uniqueID' => $uniqueID,
                            'orderheaderID' => $OrderHeaderId,
                            'orderdetailsID' => $LineNumber,
                            'ordernumber' => $orderNumber,
                            'TransactionType' => $transactionType,
                            'transactionTypeDesc' => $transactionTypeDesc,
                            'productid' => $line_item_id,
                            'Description' => $name,
                            'stockcode' => $sku,
                            'UnitNettPrice' => $price,
                            'Quantitysold' => $current_quantity,                    
                            'taxable' => $taxable,
                            'target_type' => $discount_target_type,
                            'type' => $discount_type,
                            'value' => $discount_value,
                            'value_type' => $discount_value_type,
                            'allocation_method' => $discount_allocation_method,
                            'target_selection' => $discount_target_selection,
                            'LineDiscountValue' => $total_discount,
                            'UnitLineDiscountValue' => $unitLineDiscountValue,
                            'tax_lines' => $tax_line_price,
                            'UnitPriceAfterLineDiscount' => $unitPriceAfterLineDiscount,
                            'UnitPriceAfterTax' => $unitPriceAfterTax,
                            'UnitTaxValue' => $unitTaxValue
                        );

                        // Read existing content if file exists
                        $existingContent = [];
                        if (file_exists('1Orderdetails.json')) {
                            $existingContent = json_decode(file_get_contents('1Orderdetails.json'), true);
                        }

                        // Add new order details
                        $existingContent[] = $orderDetails;

                        // Write back to file
                        file_put_contents('1Orderdetails.json', json_encode($existingContent, JSON_PRETTY_PRINT));
                    }
                    $LineNumber = $LineNumber + 1;
                }
            }
      
    //after this line we are going to update order header and order details
    $module = 'Order Update'; // Module name       
    $hasRefundLineItems = false;
    echo "after this line INSERT INTO refunds " ."<br>";
    if (isset($order_data['refunds'])) {
        foreach ($order_data['refunds'] as $refund) {
            // $orderNumber=  str_replace('#', '',$refund['name']);
            $orderNumber = $orderNumberExt;
            $checkSql = "SELECT OrderHeaderId FROM orderheaders WHERE OrderNumber = '$orderNumber'";
            $stmt = $conn->query($checkSql);
            // If order exists, skip insertion
            if ($switchorderupdateM == 0 && $stmt->num_rows > 0) {
                echo "Duplicate Sales return. Skipping insertion in orderheaders table for Order ID: " . $orderNumber;
                $stmt->close();
                continue;
            }
            if (isset($refund['refund_line_items'])) {
                $TotalOrder = 0;
                $tax_lines = 0;
                $LineNumber = 1;

                foreach ($refund['refund_line_items'] as $Rline_item) {
                    $TransactionType = 2;
                    $hasRefundLineItems = true;
                    $TransactionTypeDesc = "Sales Return";
                    $RefundProductID = $Rline_item['line_item_id'];
                    $refundProdQty = $Rline_item['quantity'];
                    $tax_lines = 0; //$Rline_item['total_tax'];

                    $returnReason = $Rline_item['return_reason'];

                    $sku = null;
                    $name = null;
                    $price = null;
                    $Product_id = null;
                    $tax_lines = 0; // Tax for this line item
                    $discount_value_type = "";
                    $taxable = 0;

                    //$OrderLineitemNode is sales line item
                    foreach ($OrderLineitemNode as $OrderLineitemEach) {
                        if ($OrderLineitemEach['id'] === $RefundProductID) {
                            $Product_id = $OrderLineitemEach['product_id']; //Here you need to find product ID from an product array with the help of line_item_id
                            $sku = $OrderLineitemEach['sku'];
                            $name = $OrderLineitemEach['title'];
                            $price = $OrderLineitemEach['price'];
                            $taxable  = $OrderLineitemEach['taxable'];
                            $TotalOrder += $price * $refundProdQty;
                            if (isset($OrderLineitemEach['tax_lines']) && is_array($OrderLineitemEach['tax_lines'])) {
                                foreach ($OrderLineitemEach['tax_lines'] as $tax_line) {
                                    $tax_lines = isset($tax_line['price']) ? (float)$tax_line['price'] : 0;
                                    $tax_lines = $tax_lines / $OrderLineitemEach['quantity'] * $refundProdQty;
                                    $Ttotal_tax += $tax_lines;
                                }
                            }

                            // Calculate Unit Tax Value
                            $unitTaxValue = $tax_lines / $refundProdQty;
                            // Set $LineDiscountTotal to 0 if not set
                            if (!isset($LineDiscountTotal)) {
                                $LineDiscountTotal = 0;
                            }
                            $LineDiscountTotal = $OrderLineitemEach['total_discount'];
                            // Calculate Unit Line Discount Value
                            $unitLineDiscountValue = $LineDiscountTotal / $OrderLineitemEach['quantity'];

                            $LineDiscountTotal = $unitLineDiscountValue * $refundProdQty; //this is in the refund line item
                            // Calculate Unit Price After Line Discount
                            $unitPriceAfterLineDiscount = $price - $unitLineDiscountValue;

                            // Calculate Unit Price After Tax using the new formula
                            $unitPriceAfterTax = $unitPriceAfterLineDiscount - $unitTaxValue;

                            $index = $discount_application_index = $refund['refund_line_items'][0]['line_item']['discount_allocations'][0]['discount_application_index'];
                            // Fetch the corresponding discount application
                            if (isset($index)) {
                            $discount_application = $order_data['discount_applications'][$index];
                            if ($discount_application['target_selection'] !== 'all') {
                                // Extract the relevant discount details
                                $discount_target_type = $discount_application['target_type'];
                                // $discount_type =  $discount_application['type']; this shall not come
                                $discount_value = $discount_application['value']; // this shall be inserted in value field
                                $discount_type = $discount_application['value_type']; //no need
                                $discount_allocation_method = $discount_application['allocation_method'];
                                $discount_target_selection = $discount_application['target_selection'];
                                $discount_value_type = $discount_application['value_type']; //added 2024-12-30                                          
                            }
                        }

                            //************************************************               orderdetails               ************************* */

                            //Line item should be inserted into table here
                            $OrderHeaderId = $refund['id'];
                            $uniqueID = $refund['id'] . $LineNumber;
                            if ($switchorderupdateM == 0) {

                                $sql = "INSERT INTO orderdetails (
                                    uniqueID, orderheaderID, TransactionType, TransactionTypeDesc, ReturnReason, orderdetailsID, ordernumber, taxable, tax_lines, productid, Description, stockcode,
                                    UnitNettPrice, Quantitysold, LineDiscountValue, UnitLineDiscountValue, target_type, type, value, value_type, allocation_method, target_selection,  UnitTaxValue, UnitPriceAfterLineDiscount, UnitPriceAfterTax
                                ) VALUES (
                                    '$uniqueID', '$OrderHeaderId', '$TransactionType', '$TransactionTypeDesc', '$returnReason', '$LineNumber', '$orderNumber', '$taxable', '$tax_lines', '$Product_id', '$name', '$sku',
                                    '$price', '$refundProdQty', '$LineDiscountTotal', '$unitLineDiscountValue', '$discount_target_type', '$discount_type', '$discount_value', '$discount_value_type', '$discount_allocation_method', '$discount_target_selection',  '$unitTaxValue', '$unitPriceAfterLineDiscount', '$unitPriceAfterTax'
                                )";
                                $stmt = $conn->query($sql);
                                if ($stmt) {
                                    echo "Line item record inserted successfully.<br>";
                                } else {
                                    echo "Error inserting line item record(orderdetails): " . $conn->error . "<br>";
                                    $logFile = __DIR__ . '/shopify_errors.log';  // Log file in the same directory as the script                
                                    $currentDateTime = date('Y-m-d H:i:s');      // Get current date and time
                                    $errorMessage = "[$currentDateTime] Error: " . $conn->connect_error;
                                    // Write the error message to the log file
                                    file_put_contents($logFile, $errorMessage, FILE_APPEND);
                                    die('Error inserting line item record: ' . $conn->error);
                                }
                            } else {
                                $orderDetails = [
                                    'uniqueID' => $uniqueID,
                                    'orderheaderID' => $OrderHeaderId,
                                    'TransactionType' => $TransactionType,
                                    'TransactionTypeDesc' => $TransactionTypeDesc,
                                    'ReturnReason' => $returnReason,
                                    'orderdetailsID' => $LineNumber,
                                    'ordernumber' => $orderNumber,
                                    'taxable' => $taxable,
                                    'LineDiscountValue' => $LineDiscountTotal,
                                    'UnitLineDiscountValue' => $unitLineDiscountValue,
                                    'tax_lines' => $tax_lines,
                                    'productid' => $Product_id,
                                    'Description' => $name,
                                    'stockcode' => $sku,
                                    'UnitNettPrice' => $price,
                                    'Quantitysold' => $refundProdQty,
                                    'target_type' => $discount_target_type,
                                    'type' => $discount_type,
                                    'value' => $discount_value,
                                    'value_type' => $discount_value_type,
                                    'allocation_method' => $discount_allocation_method,
                                    'target_selection' => $discount_target_selection,                                    
                                    'UnitTaxValue' => $unitTaxValue,
                                    'UnitPriceAfterLineDiscount' => $unitPriceAfterLineDiscount,
                                    'UnitPriceAfterTax' => $unitPriceAfterTax
                                ];

                                // Read existing content
                                $existingContent = file_exists('2orderdetailsR.json') ? json_decode(file_get_contents('2orderdetailsR.json'), true) : [];
                                if (!is_array($existingContent)) {
                                    $existingContent = [];
                                }

                                // Append new data
                                $existingContent[] = $orderDetails;

                                // Write back to file
                                file_put_contents('2orderdetailsR.json', json_encode($existingContent, JSON_PRETTY_PRINT));
                            }

                            $LineNumber = $LineNumber + 1;
                        }
                    }
                } //this is end of looping through return lines


            }
            // inserting header information of refund for line item refund. I am doing it after inserting line item because I need to sum tax from the total
            $SourcePHP = "orderUpdate.php - Sales Return - " . $filename;

            foreach ($order_data['line_items'] as $line_item) {
                if (!empty($line_item['discount_allocations'])) {
                    foreach ($line_item['discount_allocations'] as $allocation) {
                        $index = $allocation['discount_application_index'];
                        // Fetch the corresponding discount application
                        $discount_application = $order_data['discount_applications'][$index];
                        if ($discount_application['target_selection'] == 'all') {
                            // Extract the relevant discount details
                            $discount_target_type = $discount_application['target_type'];
                            // $discount_type =  $discount_application['type']; this shall not come
                            $discount_value = $discount_application['value']; // this shall be inserted in value field
                            $discount_type = $discount_application['value_type']; //no need
                            $discount_allocation_method = $discount_application['allocation_method'];
                            $discount_target_selection = $discount_application['target_selection'];
                            $discount_value_type = $discount_application['value_type']; //added 2024-12-30                                          
                        }
                        // You can decide how to handle multiple discounts (sum them, use the largest, etc.)
                    }
                }
            }
            //setting refund adjustment transactions // setting  shipping adjustment transactions  20250109  

            if (isset($order_data['refunds']) && is_array($order_data['refunds'])) {
                foreach ($order_data['refunds'] as $refund) {
                    if (isset($refund['order_adjustments'])) {
                        foreach ($refund['order_adjustments'] as $shippingLine) {
                            // we must work here with kind. make sure it is not the kind of refund_discrepency or shipping_refund
                            if ($shippingLine['kind'] == "shipping_refund") {
                                $ShippingChargeID = $shippingLine['id'];
                                $shippingCharge = ($shippingLine['amount']);
                                $Description = $shippingLine['reason'];
                                $shipping_tax = ($shippingLine['tax_amount']);
                                $TransactionType = 2;
                                if ($switchorderupdateM == 0) {
                                    $sql = "Delete from  Shippingdetails where ShippingChargeID='$ShippingChargeID'";
                                    $result = $conn->query($sql);
                                    $sql = "INSERT INTO Shippingdetails (ShippingChargeID ,OrderHeaderId , OrderNumber ,TransactionType ,Description, ChargeAmount, TaxAmount)
                                                    VALUES ('$ShippingChargeID' ,'$OrderHeaderId' ,'$orderNumber' ,'$TransactionType' ,'$Description', '$shippingCharge', '$shipping_tax')";
                                    $result = $conn->query($sql);
                                } else {
                                    $shippingData = array(
                                        'ShippingChargeID' => $ShippingChargeID,
                                        'OrderHeaderId' => $OrderHeaderId,
                                        'OrderNumber' => $orderNumber,
                                        'TransactionType' => $TransactionType,
                                        'Description' => $Description,
                                        'ChargeAmount' => $ChargeAmount,
                                        'TaxAmount' => $shipping_tax
                                    );

                                    // Read existing content if file exists
                                    $existingContent = [];
                                    if (file_exists('1shiping.json')) {
                                        $existingContent = json_decode(file_get_contents('1shiping.json'), true);
                                    }

                                    // Add new shipping data
                                    $existingContent[] = $shippingData;

                                    // Write back to file
                                    file_put_contents('1shiping.json', json_encode($existingContent, JSON_PRETTY_PRINT));
                                }
                            }
                        }
                    }
                }
            }

            $OrderAmount = $TotalOrder - $LineDiscountTotal - $HeaderDiscountTotal;
            $GrossAmount = $OrderAmount + $shippingCharge + $shipping_tax;
            $shippingCharge = abs($shippingCharge);
            $shipping_tax = abs($shipping_tax);
            if ($hasRefundLineItems == true) {
                $TransactionType = 2;
                if ($switchorderupdateM == 0) {
                    //end setting refund adjustment transactions // setting  shipping adjustment transactions  20250109  
                    $sql = "INSERT INTO orderheaders (OrderHeaderId,OrderNumber, OrderDate, CurrencyCode, OrderGrossTotal, CreationDateTime, 
                   lastModifiedDateTime, transactionType,TransactionTypeDesc,orderamount,total_tax,OrderDiscount,sourcePHP,Code,Amount, Type, target_type, value, allocation_method, 
                   target_selection,shippingCharge,shipping_tax) VALUES ('$OrderHeaderId','$orderNumber',  '$orderDate', '$currency','$GrossAmount','$creationDateTime', '$creationDateTime','$TransactionType',
                   '$TransactionTypeDesc','$OrderAmount','$Ttotal_tax','$HeaderDiscountTotal','$SourcePHP','$discount_code', '$discount_amount', '$discount_type','$discount_target_type',
                   '$discount_value',' $discount_allocation_method', '$discount_target_selection','$shippingCharge','$shipping_tax')";
                    // Prepare the statement                       
                    $stmt = $conn->query($sql);
                    if (!$stmt) {
                        die('Prepare failed: ' . $conn->error);
                    } else {
                        echo "Line item record inserted successfully.<br>";
                    }
                } else {
                    $orderHeaderData = array(
                        'OrderHeaderId' => $OrderHeaderId,
                        'OrderNumber' => $orderNumber,
                        'OrderDate' => $orderDate,
                        'CurrencyCode' => $currency,
                        'OrderGrossTotal' => $GrossAmount,
                        'CreationDateTime' => $creationDateTime,
                        'lastModifiedDateTime' => $creationDateTime,
                        'transactionType' => $TransactionType,
                        'TransactionTypeDesc' => $TransactionTypeDesc,
                        'orderamount' => $OrderAmount,
                        'total_tax' => $Ttotal_tax,
                        'OrderDiscount' => $HeaderDiscountTotal,
                        'sourcePHP' => $SourcePHP,
                        'Code' => $discount_code,
                        'Amount' => $discount_amount,
                        'Type' => $discount_type,
                        'target_type' => $discount_target_type,
                        'value' => $discount_value,
                        'allocation_method' => $discount_allocation_method,
                        'target_selection' => $discount_target_selection,
                        'shippingCharge' => $shippingCharge,
                        'shipping_tax' => $shipping_tax
                    );

                    // Read existing data
                    $existingData = [];
                    if (file_exists('1orderheaderR.json')) {
                        $jsonContent = file_get_contents('1orderheaderR.json');
                        if (!empty($jsonContent)) {
                            $existingData = json_decode($jsonContent, true);
                        }
                    }

                    // Append new data
                    $existingData[] = $orderHeaderData;

                    // Write back to file
                    $jsonData = json_encode($existingData, JSON_PRETTY_PRINT);
                    file_put_contents('1orderheaderR.json', $jsonData);
                }
            }
        }
    }


    $conn->close();

    http_response_code(200); // Send HTTP 200 status code
    echo json_encode(['message' => 'Order update processed successfully.']);
}
