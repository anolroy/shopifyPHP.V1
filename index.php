<?php

declare(strict_types=1);
require_once('vendor/autoload.php');
include_once 'credentials.php';
require_once 'router.php';

// Check if the button is clicked
if (isset($_GET['action']) && $_GET['action'] === 'product_update') {
    $conn = new mysqli($servername, $username, $password, $database);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $checkEmptySql = "SELECT COUNT(*) as count FROM stockrecords";
    $result = $conn->query($checkEmptySql);
    $row = $result->fetch_assoc();

    if ($row['count'] == 0) {
        // If the table is empty, redirect to products.php
        header("Location: products.php?action=product_update");
        exit;
    }
    $conn->close();
}

// Function to list JSON files in the specified directory
function listJsonFiles($directory)
{
    $files = glob($directory . '/*.json');
    return $files;
}
if ($isLocalServer) {
    $directory = 'C:\Development\Shopify App\archive';     
} else {
    $directory = '/home/storage/588/4501588/user/htdocs/archive/';//this is pearl collection server
        //$directory = '/home/storage/651/4265651/user/htdocs/archive'; //this is pcmdesign collection server   
        ///home/storage/588/4501588/user/htdocs/archive
   // $directory = '/archive';    
}
//$directory = '/home/pearlcol/public_html/archive';
//$directory = 'E:\Shopify PHP API';
$jsonFiles = listJsonFiles($directory);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JSON Files DataTable</title>
    <!-- Include DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.css">
    <!-- Include jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Include DataTables JS -->
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.js"></script>
    <style>
        /* Custom styles for a slim and sleek DataTable */
        body {
            width: 100vw;
        }

        #jsonFilesTable {
            width: 100%;
            /* Adjusted to be three times less than 80% */
            margin-left: 0;
            /* Align the table to the left */
            border-collapse: collapse;

            margin-top: 1em;
            /* Set top margin to 1 line height */
        }

        #jsonFilesTable th,
        #jsonFilesTable td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        #jsonFilesTable th {
            background-color: #f2f2f2;
        }

        #jsonFilesTable tr:hover {
            background-color: #f5f5f5;
        }

        .div1a {
            display: flex;
            flex-direction: row;
            width: 100%;
        }
        .div1b {
           
            width: 100%;
        }
        .div1c {
           padding-top: 40px;
            width: 100%;
        }


        .update-btn,
        .show-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 5px 10px;

            cursor: pointer;
            border-radius: 5px;
            margin-right: 5px;
        }

        .update-btn:hover,
        .show-btn:hover {
            background-color: #45a049;
        }

        /* Set a specific width for the File Name column */
        #jsonFilesTable td:first-child {
            width: 150px;
            /* Adjust this value as needed */
        }

        /* Set a specific width for the Action column */
        #jsonFilesTable td:last-child {
            width: 200px;
            /* Adjust this value as needed to accommodate both buttons */
        }

        /* Align pagination to the left */
        .dataTables_wrapper .dataTables_paginate {
            float: left;
        }

        /* Move search box above the table and add margin */
        .dataTables_wrapper .dataTables_filter {
            float: left;
            margin-bottom: 1em;
            margin-top: 1em;
            /* Add top margin to the search box */
        }

        /* Style for the text area to display JSON content */
        #jsonContent {
            width: 70%;
            height: 400px;
            margin-left: 30px;
            margin-top: 20px;
            border: 1px solid #ccc;
            padding: 10px;
            font-family: monospace;
            white-space: pre-wrap;
            overflow: auto;
        }
    </style>
</head>

<body>    
    <!-- Add the Product Update button -->
    <div style="position: absolute; top: 10px; left: 10px;">
        <form method="get" action="">
            <button type="submit" name="action" value="product_update">Product Update</button>
        </form>
    </div>

    <div class="div1a" >
        <div class="div1b">
            <table id="jsonFilesTable" class="display">
                <thead>
                    <tr>
                        <th>File Name</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Use the listJsonFiles function to get the actual JSON files
                    $jsonFiles = listJsonFiles($directory);
                    //$jsonFiles = array_reverse($jsonFiles);
                    foreach ($jsonFiles as $file) {
                        $fileName = basename($file); // Get the file name from the path
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($fileName) . "</td>";
                        echo "<td>
                    <button class='update-btn' data-file='" . htmlspecialchars($fileName) . "'>Update</button>
                    <button class='show-btn' data-file='" . htmlspecialchars($fileName) . "'>Show</button>
                  </td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>


        <div class="div1c">
            <form id="fileForm" method="get" action="">
                <input type="hidden" name="action" value="orderupdateM">
                <input type="hidden" name="file" id="fileInput">
            </form>

            <textarea id="jsonContent" readonly></textarea>
        </div>

        
    </div>





    <script>
        $(document).ready(function() {
            // Initialize DataTable with 16 entries per page pageLength: 16,
            $('#jsonFilesTable').DataTable({ 
                paging: false, // Turn off pagination              
                dom: '<"top"f>rt<"bottom"lp><"clear">',
                order: [[0, 'desc']] // Sort by the first column (index 0) in descending order
            });

            // Handle Update button click
            $('.update-btn').on('click', function() {
                var fileName = $(this).data('file');
                document.getElementById('fileInput').value = fileName;
                document.getElementById('fileForm').submit();
            });

            // Handle Show button click //read json file cannot read outside the directory
            $('.show-btn').on('click', function() {
                var fileName = $(this).data('file');
                var filePath;
                var filePath = '/archive/' + fileName;
                //alert(filePath);
                // if (isLocalServer) {
                //     filePath = '/' + fileName;
                // } else {
                //     filePath = '/archive/' + fileName;
                // }
                //filePath = '/' + fileName;
                //if (isLocalServer) {
                //    var filePath = '/' + fileName; //                   
                // } else {
                //     var filePath = '/archive/' + fileName; 
                // }
                // alert('Full path: ' + filePath);
                //console.log('Full path: ' + filePath); // Log the full path to the console
                    //working code
                    //var fileName = $(this).data('file');
                    //var filePath = '/archive/' + fileName; /
                    //*********** */
               // alert('Full path: ' + '/' + isLocalServer);
               //alert('Full path: ' + filePath);
                //filePath = 'orderupdate.json';
                $.ajax({
                    url: filePath,
                    method: 'GET',
                    dataType: 'text',
                    success: function(data) {
                        $('#jsonContent').text(data);
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error('Error loading file:', textStatus, errorThrown);
                        $('#jsonContent').text('Error loading file: ' + textStatus + ' - ' + errorThrown);
                    }
                });
            });
        });
    </script>

</body>

</html>