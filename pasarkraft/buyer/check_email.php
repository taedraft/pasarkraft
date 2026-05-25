<?php
header('Content-Type: application/json');

$email = isset($_GET['email']) ? trim($_GET['email']) : '';

$response = [
    'exists' => false,
    'valid' => false
];

if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // Extract domain from the email address
    $domain = substr(strrchr($email, "@"), 1);
    
    // Perform real-life DNS check to verify the domain has active mail (MX) or web (A) servers
    if (checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A')) {
        // We set 'exists' to true so the existing frontend JS (which checks data.exists) works seamlessly
        $response['exists'] = true;
        $response['valid'] = true;
    }
}

echo json_encode($response);
exit();
?>
