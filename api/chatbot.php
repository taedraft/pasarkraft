<?php
/**
 * PasarKraft AI Chatbot API
 * Integrates with Google Gemini API to provide interactive guide services for Malaysian crafts.
 */

// Production error-handling setup (Change to 1 temporarily if debugging errors directly)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// Require db_connect to fetch our global secure constants cleanly
require_once __DIR__ . '/db_connect.php';

// Fetch the API Key from the global configuration engine
$apiKey = pk_env('GEMINI_API_KEY', '');

// Process request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

//  FIX: Read traditional form-encoded data instead of raw JSON stream
$userMessage = isset($_POST['message']) ? trim($_POST['message']) : '';

// Decode the history array string passed from URLSearchParams
$chatHistoryRaw = isset($_POST['history']) ? $_POST['history'] : '[]';
$chatHistory = json_decode($chatHistoryRaw, true);
if (!is_array($chatHistory)) {
    $chatHistory = [];
}

if (empty($userMessage)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Message is required']);
    exit();
}

// Fallback logic if API key is not configured
if (empty($apiKey) || $apiKey === 'YOUR_GEMINI_API_KEY') {
    $reply = "I'm currently running in offline mode (GEMINI_API_KEY is not configured).\n\n";
    $msgLower = strtolower($userMessage);
    if (strpos($msgLower, 'batik') !== false) {
        $reply .= "Batik is a traditional wax-resist fabric art in Malaysia. We feature authentic hand-drawn (canting) and block-printed (cap) shirts and sarongs. Feel free to contact our sellers directly via the 'Chat with Seller' button for customization inquiries!";
    } elseif (strpos($msgLower, 'wood') !== false || strpos($msgLower, 'carv') !== false) {
        $reply .= "Woodcraft includes hand-carved decor, wall panels, and dining tables made from teak, mahogany, and bamboo by local Malaysian carvers. You can negotiate custom requests directly by clicking 'Chat with Seller'.";
    } elseif (strpos($msgLower, 'contact') !== false || strpos($msgLower, 'buy') !== false || strpos($msgLower, 'order') !== false) {
        $reply .= "To make a purchase, open any product card and click 'Chat with Seller'. PasarKraft is a direct-to-artisan marketplace, so all orders and pricing are discussed and agreed upon directly through chats with the creators!";
    } else {
        $reply .= "I can help you learn more about Batik, Woodcraft, and how to contact local artisans. To order, please open any product page and click 'Chat with Seller' to start a negotiation.";
    }
    echo json_encode(['status' => 'success', 'reply' => $reply]);
    exit();
}

// Parse history into Gemini format
$contents = [];
foreach ($chatHistory as $msg) {
    $role = ($msg['role'] === 'user') ? 'user' : 'model';
    $text = $msg['text'] ?? ($msg['parts'][0]['text'] ?? '');
    if ($text !== '') {
        $contents[] = [
            'role' => $role,
            'parts' => [['text' => $text]]
        ];
    }
}

// Add the current user query
$contents[] = [
    'role' => 'user',
    'parts' => [['text' => $userMessage]]
];

// System prompt defining the chatbot's persona and rules
$systemPrompt = "You are PasarKraft AI Guide, a warm, friendly, and expert assistant for the PasarKraft marketplace.

Your character and instructions:
1. Expertise: You are a specialist in traditional Malaysian crafts, specifically Batik (fashion, textiles, block-printing/cap, hand-drawing/canting) and Woodcraft (relief carving, home decor, furniture, and local woods like Teak/Jati, Mahogany, Meranti, and Bamboo).
2. Primary Role: Help users understand traditional craftsmanship, explain what makes these pieces unique, suggest categories, and guide them on how the website functions.
3. Market Flow (CRITICAL): Explain to users that PasarKraft is a direct-to-artisan marketplace. There is no automated shopping cart or online checkout. Buyers must click the 'Chat with Seller' button on any product card to talk directly with the artisan. In the chat, buyers can negotiate prices, make custom offers, and request specific customizations (such as custom sizing or personalized engravings).
4. Sourcing & Customization: Encourage direct inquiry for any item they are interested in.
5. Tone: Be enthusiastic, professional, culturally appreciative of Malaysian heritage, and clear.
6. Conciseness: Keep responses relatively short and direct so it is easy to read inside a small chat widget.";

$payload = [
    'contents' => $contents,
    'systemInstruction' => [
        'parts' => [
            ['text' => $systemPrompt]
        ]
    ]
];

// Execute API call
$ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $apiKey);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

// Crucial InfinityFree Fix: Disable peer verification to prevent missing SSL cert crashes
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'API connection failed: ' . $curlError]);
    exit();
}

if ($httpCode !== 200) {
    http_response_code($httpCode);
    $errObj = json_decode($response, true);
    $errMsg = $errObj['error']['message'] ?? 'Unknown API error';
    echo json_encode(['status' => 'error', 'message' => 'Gemini API Error: ' . $errMsg]);
    exit();
}

$result = json_decode($response, true);
$replyText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

if (empty($replyText)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Empty response from Gemini API']);
    exit();
}

echo json_encode(['status' => 'success', 'reply' => $replyText]);