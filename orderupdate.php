<?php
set_time_limit(300);
include_once 'credentials.php';
include_once 'getRespose.php';



// Define the directory based on the server environment
// if (isLocalServer) {
//     $directory = 'E:\\Shopify PHP API';
// } else {
//     $directory = '/home/pearlcol/public_html/archive';
// }
// This module create sales return, Sales Return Receipt from orderupdate.json file
// Check if the action parameter is set
if (isset($_GET['action']) && $_GET['action'] === 'orderupdate' ) 
{
   // $file = "orderupdate.json";
        //$filePath = $directory . '/' . $file;

   
       // $order_data = json_decode(file_get_contents($filePath), true);
        // Insert order data into database
        // Here in this page we shall handle all returns   
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
        // rem on 20250306
        $conn = new mysqli($servername, $username, $password, $database);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        $dateCalled = date('Y-m-d H:i:s'); // Current datetime
        $idhook = $document_id; // Generate a unique ID for the record
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


        $module = 'Order Update'; // Module name
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
            foreach ($order_data['discount_codes'] as $allocation) 
            {
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
//********************20250303****** */
        if (isset($order_data['returns']) && is_array($order_data['returns'])) 
        {
            foreach ($order_data['returns'] as $return) 
            {
                if (isset($return['return_line_items']) && !empty($return['return_line_items'])) 
                {
                    $hasReturnLineItems = true;
                    break; // Exit the loop on first find
                }
            }
        }

        //*****20250410******** */
        if (isset($order_data['refunds']) && is_array($order_data['refunds'])) {
            foreach ($order_data['refunds'] as $refund) 
            {
                if (isset($refund['refund_line_items']) && !empty($refund['refund_line_items'])) 
                {
                    $hasRefundLineItems = true;
                    $restock_type = $refund['restock'];//  false means return associated with refund . true means refund with line items 
                    if ($restock_type == false) 
                    {
                        $TransactionType = 2;
                        //$TransactionTypeDesc = "Sales Return Refund without line items";
                        //new amendent on 20250306
                        if($hasRefundLineItems == true)                       
                            $TransactionTypeDesc = "Sales Return Refund with line items";
                        else
                            $TransactionTypeDesc = "Sales Return Refund wiithout line items";
                       
                    }    
                    elseif ($restock_type == true) 
                    {
                        $TransactionType = 4;
                        if($hasRefundLineItems == true) 
                             {$TransactionTypeDesc = "Manual Refund with item lines";}
                        else
                             $TransactionTypeDesc = "Manual Refund without item lines";    
                    }
                    break; // Exit the foreach loop
                }    
                else 
                {
                    $TransactionType = 3;
                    //make the change in this description 20250303
                    if($hasReturnLineItems == true)
                        $TransactionTypeDesc = "Sales Return Refund with line items";
                    else
                        $TransactionTypeDesc = "Manual Return Refund without line items";
                }
                //$TransactionTypeDesc = null;
                $SourcePHP = "orderupdate.php - " . $filename;
                $SourceID = ""; //$order_data['payment_id'];
            }
        }
//********************20250303****** */

        if (isset($order_data['refunds']) && is_array($order_data['refunds'])) {
            foreach ($order_data['refunds'] as $refund) 
            {
                
//1.Scenario : transactions node can be empty and still there can be a return
//i.e 'orderUpdate.php - Sales Return - /home/storage/588/4501588/user/htdocs/archive/20250331_082731_39878_orderupdate.json'
// then Order Number variable shall become null.

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
                            if($switchorderupdate == 1)
                            {
                                $receiptData = [
                                    'id' => $document_id,
                                    'order_id' => $orderNumberExt,
                                    'currency' => $currency,
                                    'payment_id' => $refund_id,
                                    'Type' => $TransactionType,
                                    'TransactionTypeDesc' => $TransactionTypeDesc,
                                    'SourcePHP' => $SourcePHP,
                                    'SourceID' => $SourceID,
                                    'created_at' => $created_at,
                                    'gateway' => $gateway,
                                    'message' => $message,                                    
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
                            if($switchorderupdate == 0)
                            {
                                    $sqlCheck = "SELECT COUNT(*) as count FROM Receipt WHERE order_id = '$orderNumberExt'";
                                    $resultCheck = $conn->query($sqlCheck);
                                    if ($resultCheck) 
                                    {
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
                                            
                                                $sql = "
                                                INSERT INTO Receipt (id, order_id, currency, payment_id,TransactionTypeDesc,SourcePHP,SourceID, created_at, gateway, message, Type, TotalTax, amount, ReceiptDownloaded)
                                                VALUES ('$document_id', '$orderNumberExt', '$currency', '$refund_id', '$TransactionTypeDesc','$SourcePHP','$SourceID','$created_at', '$gateway', '$message', '$TransactionType','$taxAmount', '$TotalOrder', 0)
                                                ";
                                                $stmt = $conn->query($sql);
                                                if (!$stmt) {
                                                    die('Insert failed: ' . $conn->error);
                                                }
                                                echo "Receipt inserted successfully.\n"; 
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




        $hasRefundLineItems = false;
        if (isset($order_data['refunds'])) {
            foreach ($order_data['refunds'] as $refund) 
            {
                // $orderNumber=  str_replace('#', '',$refund['name']);
                $orderNumber = $orderNumberExt;
                $checkSql = "SELECT OrderHeaderId FROM orderheaders WHERE OrderNumber = '$orderNumber'";
                $stmt = $conn->query($checkSql);
                // If order exists, skip insertion
                 if ($switchorderupdate == 0 && $stmt->num_rows > 0) {
                    echo "Duplicate Sales return. Skipping insertion in orderheaders table for Order ID: " . $orderNumber;
                    $stmt->close();
                    continue;
                }
                if (isset($refund['refund_line_items'])) 
                {
                    $TotalOrder = 0;
                    $tax_lines = 0;
                    $LineNumber = 1;
                    
                    foreach ($refund['refund_line_items'] as $Rline_item) {
                        $TransactionType = 2;
                        $hasRefundLineItems=true;
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
                                    // You can decide how to handle multiple discounts (sum them, use the largest, etc.)
                                
     

                        //$OrderLineitemNode is sales line item
                        foreach ($OrderLineitemNode as $OrderLineitemEach) {
                            if (($OrderLineitemEach['id'] === $RefundProductID) && ($orderNumber !== null)) {
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
                                $LineDiscountTotal = $OrderLineitemEach['total_discount']; //this is in the order line item
                                // Calculate Unit Line Discount Value
                                $unitLineDiscountValue = $LineDiscountTotal / $OrderLineitemEach['quantity'];                            
                                
                                $LineDiscountTotal = $unitLineDiscountValue * $refundProdQty; //this is in the refund line item
                                
                                // Calculate Unit Price After Line Discount
                                $unitPriceAfterLineDiscount = $price - $unitLineDiscountValue;

                                // Calculate Unit Price After Tax using the new formula
                                $unitPriceAfterTax = $unitPriceAfterLineDiscount - $unitTaxValue;

                                  
                                
    //************************************************               orderdetails               ************************* */

                                //Line item should be inserted into table here
                                $OrderHeaderId=$refund['id'];
                                $uniqueID=$refund['id'].$LineNumber;
                                if($switchorderupdate == 0)
                                {
                                    
                                    $sql = "INSERT INTO orderdetails (
                                        uniqueID, orderheaderID, TransactionType, TransactionTypeDesc, ReturnReason, orderdetailsID, ordernumber, taxable, tax_lines, productid, Description, stockcode,
                                        UnitNettPrice, Quantitysold, LineDiscountValue, UnitLineDiscountValue, target_type, type, value, value_type, allocation_method, target_selection, UnitTaxValue, UnitPriceAfterLineDiscount, UnitPriceAfterTax
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
                                        $errorMessage = "[$currentDateTime] Error: ". $conn->connect_error ;            
                                        // Write the error message to the log file
                                        file_put_contents($logFile, $errorMessage, FILE_APPEND);               
                                        die('Error inserting line item record: ' . $conn->error);
                                    }  
                                }
                                else
                                {
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
                                if ($shippingLine['kind'] == "shipping_refund")
                                {
                                    $ShippingChargeID = $shippingLine['id'];
                                    $shippingCharge = ($shippingLine['amount']);
                                    $Description = $shippingLine['reason'];
                                    $shipping_tax = ($shippingLine['tax_amount']);
                                    if ($hasRefundLineItems )
                                        $TransactionType = 2;
                                    else
                                        $TransactionType = 3;
                                    if($switchorderupdate == 0)
                                        { $sql = "Delete from  Shippingdetails where ShippingChargeID='$ShippingChargeID'";
                                            $result = $conn->query($sql);
                                            $sql = "INSERT INTO Shippingdetails (ShippingChargeID ,OrderHeaderId , OrderNumber ,TransactionType ,Description, ChargeAmount, TaxAmount)
                                                        VALUES ('$ShippingChargeID' ,'$OrderHeaderId' ,'$orderNumber' ,'$TransactionType' ,'$Description', '$shippingCharge', '$shipping_tax')";
                                            $result = $conn->query($sql);
                                        }
                                    else
                                        { 
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
                if ($hasRefundLineItems == true  && ($orderNumber !== null))
                {
                    $TransactionType = 2;  
                    if($switchorderupdate == 0)
                    {
                        //Copy Source
                        // $sql = "INSERT INTO orderheaders (
                        //     OrderHeaderId, OrderNumber, OrderDate, DeliveryDateEarliest,TransactionType,transactionTypeDesc,InvoiceName, company,DeliveryName,InvoiceAddress1, EmailAddress, CurrencyCode, OrderGrossTotal, Comments,
                        //     CreationDateTime, lastModifiedDateTime,  fulfilled,  financial_status,  orderamount, OrderDiscount,shippingCharge,
                        //     Code, Amount, Type, target_type, value, allocation_method, target_selection, total_tax,InvoiceAddress2,InvoiceAddress3, 
                        //     InvoiceAddress4, InvoicePostcode, DeliveryAddress1, DeliveryAddress2, DeliveryAddress3, DeliveryAddress4, DeliveryAddress5,DeliveryPostcode, DeliveryTelephone_number,shipping_tax,sourcePHP                   
                        // ) VALUES ('$OrderHeaderId', '$orderNumber', '$orderDate', '$deliveryDateEarliest', '$transactionType','$transactionTypeDesc','$InvoiceName',' $company','$DeliveryName', '$invoiceAddress1','$emailAddress', '$currencyCode', '$orderGrossTotal', '$comments', 
                        //     '$creationDateTime', '$updated_at', '$fulfillment_status', '$financial_status', '$orderamount', '$OrderDiscount', '$shippingCharge', 
                        //     '$discount_code', '$discount_amount', '$discount_type','$discount_target_type', '$discount_value',' $discount_allocation_method', '$discount_target_selection','$total_tax','$invoiceAddress2','$invoiceAddress3', 
                        //     '$invoiceAddress4', '$invoicePostcode', '$DeliveryAddress1', '$DeliveryAddress2', '$DeliveryAddress3', '$DeliveryAddress4', '$DeliveryAddress5','$DeliveryPostcode','$DeliveryTelephone_number','$shipping_tax','$sourcePHP' )";

                            // make changes to this original sql statement 
                        //end setting refund adjustment transactions // setting  shipping adjustment transactions  20250109  
                        //    $sql = "INSERT INTO orderheaders (OrderHeaderId,OrderNumber, OrderDate, CurrencyCode, OrderGrossTotal, CreationDateTime, 
                        //    lastModifiedDateTime, transactionType,TransactionTypeDesc,orderamount,total_tax,OrderDiscount,sourcePHP,Code,Amount, Type, target_type, value, allocation_method, 
                        //    target_selection,shippingCharge,shipping_tax) VALUES ('$OrderHeaderId','$orderNumber',  '$orderDate', '$currency','$GrossAmount','$creationDateTime', '$creationDateTime','$TransactionType',
                        //    '$TransactionTypeDesc','$OrderAmount','$Ttotal_tax','$HeaderDiscountTotal','$SourcePHP','$discount_code', '$discount_amount', '$discount_type','$discount_target_type',
                        //    '$discount_value',' $discount_allocation_method', '$discount_target_selection','$shippingCharge','$shipping_tax')";
                        //Before inserting into orderheaders table are you duplicate checking? I don't see Ans: Yes ther is duplicate checking
                        //but also this inserting is inside  foreach ($order_data['refunds'] as $refund)  why is this so? 
                        //ans: Yes this is weird  this part is looping foreach ($order_data['refunds'] as $refund) which are line items
                        // more over we are generating   OrderNumber from transaction Node. So I think orderheaders for return should come from
                        // transaction node as transaction is single is orderupdate.json file. but then other complexities like LineDiscountTotal shippingCharge are 
                        // come from details part. This is weird. I think we need to check this part.    
                        $sql = "INSERT INTO orderheaders (OrderHeaderId, OrderNumber, OrderDate, CurrencyCode, OrderGrossTotal, CreationDateTime, 
                        lastModifiedDateTime, transactionType, TransactionTypeDesc, orderamount, total_tax, OrderDiscount, sourcePHP, Code, Amount, Type, target_type, value, allocation_method, 
                        target_selection, shippingCharge, shipping_tax, InvoiceName, company, DeliveryName, InvoiceAddress1, EmailAddress, InvoiceAddress2, InvoiceAddress3, 
                        InvoiceAddress4, InvoicePostcode, DeliveryAddress1, DeliveryAddress2, DeliveryAddress3, DeliveryAddress4, DeliveryAddress5, DeliveryPostcode, DeliveryTelephone_number) 
                        VALUES ('$OrderHeaderId', '$orderNumber', '$orderDate', '$currency', '$GrossAmount', '$creationDateTime', '$creationDateTime', '$TransactionType',
                        '$TransactionTypeDesc', '$OrderAmount', '$Ttotal_tax', '$HeaderDiscountTotal', '$SourcePHP', '$discount_code', '$discount_amount', '$discount_type', '$discount_target_type',
                        '$discount_value', '$discount_allocation_method', '$discount_target_selection', '$shippingCharge', '$shipping_tax', '$InvoiceName', '$company', '$DeliveryName', 
                        '$invoiceAddress1', '$emailAddress', '$invoiceAddress2', '$invoiceAddress3', '$invoiceAddress4', '$invoicePostcode', '$DeliveryAddress1', '$DeliveryAddress2', 
                        '$DeliveryAddress3', '$DeliveryAddress4', '$DeliveryAddress5', '$DeliveryPostcode', '$DeliveryTelephone_number')";

                        // Prepare the statement                       
                        $stmt = $conn->query($sql);
                        if (!$stmt) 
                        {
                            die('Prepare failed: ' . $conn->error);                           
                        }  
                        else
                        {
                            echo "Line item record inserted successfully.<br>"; 
                        }    
                    }
                    else
                    {
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
                        'shipping_tax' => $shipping_tax,
                        'InvoiceName' => $InvoiceName,
                        'company' => $company,
                        'DeliveryName' => $DeliveryName,
                        'InvoiceAddress1' => $invoiceAddress1,
                        'EmailAddress' => $emailAddress,
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
                        'DeliveryTelephone_number' => $DeliveryTelephone_number
                        );

                        // Read existing data
                        $existingData = [];
                        if (file_exists('2orderheaderR.json')) {
                            $jsonContent = file_get_contents('2orderheaderR.json');
                            if (!empty($jsonContent)) {
                                $existingData = json_decode($jsonContent, true);
                            }
                        }

                        // Append new data
                        $existingData[] = $orderHeaderData;

                        // Write back to file
                        $jsonData = json_encode($existingData, JSON_PRETTY_PRINT);
                        file_put_contents('2orderheaderR.json', $jsonData);
                    }   
                }
            }
        }


        $conn->close();
   
    }

http_response_code(200); // Indicate successful execution
?>
