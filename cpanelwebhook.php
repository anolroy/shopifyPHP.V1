<?php
$secret = "12345678"; // Optional: Set this in GitHub webhook settings
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$payload = file_get_contents('php://input');

// Verify GitHub Signature (Optional, add your secret in GitHub webhook settings)
// if ($secret) {
//     $hash = "sha256=" . hash_hmac('sha256', $payload, $secret, false);
//     if (!hash_equals($hash, $signature)) {
//         http_response_code(403);
//         die("Invalid signature");
//     }
// }

// Log the payload (optional, for debugging)
file_put_contents("webhook.log", $payload . PHP_EOL, FILE_APPEND);

// Execute deployment script
exec("cd /home/pearlcol/repositories/shopifyPHP.V1 && git pull origin master && /usr/local/cpanel/bin/cpu.sh --user=pearlcol deploy", $output);
file_put_contents("webhook.log", implode("\n", $output) . PHP_EOL, FILE_APPEND);

http_response_code(200);
echo "Deployment triggered!";
?>
