<?php
use GuzzleHttp\Client; 
include_once 'credentials.php';
include_once 'getRespose.php';
if (isset($_GET['action'])) 
{ 
    $conn = new mysqli($servername, $username, $password, $database);

    //$orderNumber = intval($_GET['orderNumber']); // Sanitize input
    $sqlDelete = "DELETE FROM stockrecords WHERE ProductId = ?";
    $stmt = $conn->prepare($sqlDelete);
    $stmt->bind_param("i", $orderNumber);
    if ($stmt->execute()) {
        echo "Record deleted successfully.";
        
    } else {
        echo "Error deleting record: " . $conn->error;
    }
    $stmt->close();
    $conn->close();
}
?>
