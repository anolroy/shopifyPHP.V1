<?php
$action = $_GET['action'] ?? '';
//echo "router:".$action;
switch ($action) {    
    case 'updateProduct':
        require_once 'updateProduct.php';
        break;
    case 'product':
        require_once 'products.php';
        break;    
    case 'order':
        require_once 'order.php';
    case 'orderupdate':
        require_once 'orderupdate.php';
        break; 
    case 'fulfillment':
        require_once 'fulfillment.php';
        break;
     case 'levelupdate':
        require_once 'levelupdate.php';
        break;    
    case 'receipt':
        require_once 'receipt.php';
        break;    
    case 'refund':
        require_once 'refund.php';
    case 'orderupdateM':       
        require_once 'orderupdateM.php';    
        break;
    case 'paymentsettings':
        require_once 'paymentSettings.php';
        break;
    case 'connection':
        require_once 'connection.php';
        break;
    case 'productdelete':
        require_once 'productdelete.php';
        break;
        //need to wrtie product update code tomorrow
    case 'productupdate':
        require_once 'productupdate.php';
        break;    
    case 'productinsert':
        require_once 'productinsert.php';   
        break;               
    default:
       // include_once 'getRespose.php';
        echo "Error: Invalid action specified.";
        break;
}
?>