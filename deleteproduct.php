<?php
include_once 'credentials.php';
include_once 'getRespose.php';
// Check if the product ID or variant ID is provided
if (isset($_GET['action'])) {
    $productID =  $order_data['id'];

    // Create a connection to the database
    $conn = new mysqli($servername, $username, $password, $database);

    // Check the connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Prepare the SQL statement to delete the product
    $sql = "DELETE FROM stockrecords WHERE ProductId = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $productID);

    // Execute the statement
    if ($stmt->execute()) {
        echo "Product with product ID $productID deleted successfully.";
    } else {
        echo "Error deleting product: " . $stmt->error;
    }

    // Close the statement and connection
    $stmt->close();
    $conn->close();
} else {
    echo "No productID provided.";
}
?>
