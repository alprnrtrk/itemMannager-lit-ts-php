<?php
// public/index.php - Modular SQLite Version (All in Public)

// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Autoload classes: This simple autoloader finds classes based on their namespace
// and file path relative to this index.php file.
spl_autoload_register(function ($class) {
    $prefix = 'App\\'; // All our custom classes will start with the 'App' namespace
    // The base directory for our application logic files (e.g., public/app/)
    $base_dir = __DIR__ . '/app/';
    $len = strlen($prefix);

    // Check if the class uses our defined namespace prefix
    if (strncmp($prefix, $class, $len) !== 0) {
        return; // Not our class, let other autoloaders handle it (or fail)
    }

    // Get the relative class name (e.g., 'Config\Config', 'Managers\ItemManager')
    $relative_class = substr($class, $len);

    // Construct the full file path:
    // 1. Replace namespace backslashes with directory slashes
    // 2. Append the .php extension
    // Example: App\Database\Database -> public/app/Database/Database.php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // Uncomment the line below for debugging if you encounter 'Class not found' errors
    // error_log("Attempting to load class: " . $class . " from file: " . $file);

    // If the file exists, include it
    if (file_exists($file)) {
        require_once $file;
    }
});

// Import necessary classes using their full namespaces
use App\Config\Config;
use App\Database\Database;
use App\Managers\AuthManager;
use App\Managers\ItemManager;

// --- Parse Request URI to determine Resource and Action ---
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// All our API endpoints start with '/api/'. We remove this prefix
// to get the relevant part of the path (e.g., 'items/add', 'auth/admin').
$api_path = substr($request_uri, strlen('/api/'));

// Split the path into segments. The first segment is usually the 'resource'
// (e.g., 'items', 'auth') and the second is the 'action' (e.g., 'add', 'get', 'admin').
$path_segments = explode('/', $api_path);
$resource = $path_segments[0] ?? ''; // Get the first segment (e.g., 'items')
$action = $path_segments[1] ?? '';   // Get the second segment (e.g., 'add')

// --- Initialize Database Connection and Managers ---
// We pass __DIR__ (the path to the public folder) to the Database and ItemManager
// so they can correctly resolve absolute paths for the SQLite file and upload directory.
$db_connection = Database::getConnection(__DIR__);
$itemManager = new ItemManager($db_connection, __DIR__);
$authManager = new AuthManager();

// --- Helper Function for JSON Responses ---
// This function standardizes sending JSON responses and setting HTTP status codes.
function sendJsonResponse(array $data, int $statusCode): void
{
    // Set JSON content type only when sending JSON
    header('Content-Type: application/json');
    // Common CORS headers for API responses
    header('Access-Control-Allow-Origin: *'); // CHANGE FOR PRODUCTION!
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    http_response_code($statusCode);
    echo json_encode($data);
    exit; // Terminate script execution after sending response
}

// Handle preflight OPTIONS requests for CORS (browsers send these before actual requests)
// This must be done BEFORE any content is sent or headers are modified by other parts
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(200);
    exit;
}


// --- API Routing Logic ---
// This main conditional block checks if the request is for an API endpoint
if (strpos($request_uri, '/api/') === 0) {
    // Route based on the 'resource' segment of the URL
    switch ($resource) {
        case 'data':
            sendJsonResponse([
                'message' => 'Data from PHP Backend (Modular SQLite Version - All in Public)!',
                'timestamp' => time(),
                'server_time' => date('Y-m-d H:i:s')
            ], 200);
            break;

        case 'auth':
            if ($action === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $password = $input['password'] ?? '';
                $response = $authManager->authenticate($password);
                sendJsonResponse($response, $response['authenticated'] ? 200 : 401);
            } else {
                sendJsonResponse(['error' => 'Method Not Allowed or Invalid Auth Endpoint'], 405);
            }
            break;

        case 'items':
            switch ($action) {
                case 'get':
                    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                        $response = $itemManager->getAllItems();
                        sendJsonResponse($response['data'], $response['status']);
                    } else {
                        sendJsonResponse(['error' => 'Method Not Allowed for /api/items/get'], 405);
                    }
                    break;

                case 'add':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $response = $itemManager->addItem($_POST, $_FILES['itemImage'] ?? null);
                        sendJsonResponse($response, $response['status']);
                    } else {
                        sendJsonResponse(['error' => 'Method Not Allowed for /api/items/add'], 405);
                    }
                    break;

                case 'update':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $itemId = (int) ($_POST['id'] ?? 0);
                        if ($itemId === 0) {
                            sendJsonResponse(['success' => false, 'message' => 'Item ID is required for update.'], 400);
                        }
                        $response = $itemManager->updateItem($itemId, $_POST, $_FILES['itemImage'] ?? null);
                        sendJsonResponse($response, $response['status']);
                    } else {
                        sendJsonResponse(['error' => 'Method Not Allowed for /api/items/update'], 405);
                    }
                    break;

                case 'delete':
                    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
                        $input = json_decode(file_get_contents('php://input'), true);
                        $itemId = (int) ($input['id'] ?? 0);
                        if ($itemId === 0) {
                            sendJsonResponse(['success' => false, 'message' => 'Item ID is required for deletion.'], 400);
                        }
                        $response = $itemManager->deleteItem($itemId);
                        sendJsonResponse($response, $response['status']);
                    } else {
                        sendJsonResponse(['error' => 'Method Not Allowed for /api/items/delete'], 405);
                    }
                    break;

                default:
                    sendJsonResponse(['error' => 'Invalid API action for items.'], 404);
                    break;
            }
            break;

        default:
            sendJsonResponse(['error' => 'API Endpoint not found.'], 404);
            break;
    }
} else {
    // --- Default Fallback: Serve the main HTML file ---
    // If the request is not for an API endpoint, serve the frontend's index.html file.
    // Set the Content-Type header to text/html for HTML files
    header('Content-Type: text/html');
    readfile(__DIR__ . '/index.html');
    exit;
}