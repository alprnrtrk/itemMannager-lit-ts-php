<?php
// public/index.php - SQLite Version

// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$upload_dir = __DIR__ . '/uploads/';
$db_file_path = __DIR__ . '/data/items.sqlite'; // Path to your SQLite database file

// Ensure upload directory exists and is writable
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Ensure data directory exists
if (!is_dir(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0777, true);
}

/**
 * Establish SQLite Database Connection and Create Table if it doesn't exist.
 * @return PDO PDO database connection object
 */
function get_db_connection($db_path)
{
    try {
        $pdo = new PDO("sqlite:" . $db_path);
        // Set PDO to throw exceptions on error, which is crucial for debugging
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Set default fetch mode to associative array
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Create items table if it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT,
            price REAL NOT NULL,
            imageUrl TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
        exit;
    }
}

// Get the database connection
$db = get_db_connection($db_file_path);

// --- PHP API Endpoints ---
if (strpos($request_uri, '/api/') === 0) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *'); // Allow all origins for development. CHANGE FOR PRODUCTION!
    header('Access-Control-Allow-Methods: GET, POST, DELETE, PUT, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    // Handle OPTIONS requests (preflight for CORS)
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    // API endpoint: /api/data (for general testing)
    if ($request_uri === '/api/data') {
        echo json_encode(['message' => 'Data from PHP Backend (SQLite Version)!', 'timestamp' => time(), 'server_time' => date('Y-m-d H:i:s')]);
    }
    // Admin Authentication Endpoint
    elseif ($request_uri === '/api/auth/admin') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $password = $input['password'] ?? '';
            $admin_password_secret = '1234'; // <--- IMPORTANT: Change this password!

            if ($password === $admin_password_secret) {
                echo json_encode(['authenticated' => true, 'message' => 'Authentication successful']);
            } else {
                http_response_code(401);
                echo json_encode(['authenticated' => false, 'message' => 'Invalid password']);
            }
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
        }
    }
    // Add Item Endpoint
    elseif ($request_uri === '/api/items/add') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = $_POST['name'] ?? '';
            $description = $_POST['description'] ?? '';
            $price = $_POST['price'] ?? 0;

            if (empty($name) || empty($description) || !isset($price)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing required item fields (name, description, price).']);
                exit;
            }

            if (!is_numeric($price)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Price must be a number.']);
                exit;
            }
            $price = (float) $price;

            $imageUrl = null;
            if (isset($_FILES['itemImage']) && $_FILES['itemImage']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['itemImage']['tmp_name'];
                $fileName = $_FILES['itemImage']['name'];
                $fileSize = $_FILES['itemImage']['size'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                $destPath = $upload_dir . $newFileName;

                $allowedFileExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                if (!in_array($fileExtension, $allowedFileExtensions)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, JPEG, PNG, GIF allowed.']);
                    exit;
                }

                if ($fileSize > 5 * 1024 * 1024) { // 5 MB limit
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'File size exceeds limit (5MB).']);
                    exit;
                }

                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $imageUrl = '/uploads/' . $newFileName;
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file. Check permissions.']);
                    exit;
                }
            } else {
                // For new item, image is mandatory as per your original logic
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'No image file uploaded for new item.']);
                exit;
            }

            try {
                $stmt = $db->prepare("INSERT INTO items (name, description, price, imageUrl) VALUES (:name, :description, :price, :imageUrl)");
                $stmt->execute([
                    ':name' => $name,
                    ':description' => $description,
                    ':price' => $price,
                    ':imageUrl' => $imageUrl
                ]);

                $newItemId = $db->lastInsertId();
                $newItem = [
                    'id' => (int) $newItemId,
                    'name' => $name,
                    'description' => $description,
                    'price' => $price,
                    'imageUrl' => $imageUrl
                ];

                echo json_encode(['success' => true, 'message' => 'Item added successfully!', 'item' => $newItem]);
            } catch (PDOException $e) {
                // If DB insert fails, clean up the uploaded image
                if ($imageUrl && file_exists(__DIR__ . $imageUrl)) {
                    unlink(__DIR__ . $imageUrl);
                }
                http_response_code(500);
                error_log("Add item failed: " . $e->getMessage()); // Log the error
                echo json_encode(['success' => false, 'message' => 'Failed to add item to database.']);
            }

        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
        }
    }
    // Get Items Endpoint
    elseif ($request_uri === '/api/items/get') {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            try {
                $stmt = $db->query("SELECT id, name, description, price, imageUrl FROM items ORDER BY id DESC");
                $items = $stmt->fetchAll();
                // Ensure price is cast to float for consistent JSON output
                foreach ($items as &$item) {
                    $item['id'] = (int) $item['id'];
                    $item['price'] = (float) $item['price'];
                }
                echo json_encode($items);
            } catch (PDOException $e) {
                http_response_code(500);
                error_log("Get items failed: " . $e->getMessage());
                echo json_encode(['error' => 'Failed to retrieve items from database.']);
            }
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
        }
    }
    // Delete Item Endpoint (with image deletion)
    elseif ($request_uri === '/api/items/delete') {
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            $input = json_decode(file_get_contents('php://input'), true);
            $itemIdToDelete = (int) ($input['id'] ?? 0);

            if ($itemIdToDelete === 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Item ID is required for deletion.']);
                exit;
            }

            try {
                // First, get the item's imageUrl before deleting the record
                $stmt = $db->prepare("SELECT imageUrl FROM items WHERE id = :id");
                $stmt->execute([':id' => $itemIdToDelete]);
                $item = $stmt->fetch();

                if (!$item) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Item not found.']);
                    exit;
                }

                // Delete the record from the database
                $stmt = $db->prepare("DELETE FROM items WHERE id = :id");
                $stmt->execute([':id' => $itemIdToDelete]);

                if ($stmt->rowCount() > 0) {
                    // If successfully deleted from DB, delete the image file
                    $imageToDeletePath = $item['imageUrl'];
                    if ($imageToDeletePath && file_exists(__DIR__ . $imageToDeletePath)) {
                        unlink(__DIR__ . $imageToDeletePath);
                    }
                    echo json_encode(['success' => true, 'message' => 'Item deleted successfully!']);
                } else {
                    http_response_code(404); // Should not happen if previous fetch found item
                    echo json_encode(['success' => false, 'message' => 'Failed to delete item (possibly not found after check).']);
                }

            } catch (PDOException $e) {
                http_response_code(500);
                error_log("Delete item failed: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Failed to delete item from database.']);
            }

        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
        }
    }
    // Update Item Endpoint (with old image deletion and new upload)
    elseif ($request_uri === '/api/items/update') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $itemIdToUpdate = (int) ($_POST['id'] ?? 0);

            if ($itemIdToUpdate === 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Item ID is required for update.']);
                exit;
            }

            try {
                // Fetch the existing item to get its current image URL
                $stmt = $db->prepare("SELECT id, name, description, price, imageUrl FROM items WHERE id = :id");
                $stmt->execute([':id' => $itemIdToUpdate]);
                $existingItem = $stmt->fetch();

                if (!$existingItem) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Item to update not found.']);
                    exit;
                }

                $name = $_POST['name'] ?? $existingItem['name'];
                $description = $_POST['description'] ?? $existingItem['description'];
                $price = isset($_POST['price']) ? (float) $_POST['price'] : (float) $existingItem['price'];
                $currentImageUrl = $existingItem['imageUrl'];
                $newImageUrl = $currentImageUrl;
                $oldImageFileToDelete = null;

                // Handle new image upload
                if (isset($_FILES['itemImage']) && $_FILES['itemImage']['error'] === UPLOAD_ERR_OK) {
                    $fileTmpPath = $_FILES['itemImage']['tmp_name'];
                    $fileName = $_FILES['itemImage']['name'];
                    $fileSize = $_FILES['itemImage']['size'];
                    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                    $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                    $destPath = $upload_dir . $newFileName;

                    $allowedFileExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                    if (!in_array($fileExtension, $allowedFileExtensions)) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Invalid file type for new image. Only JPG, JPEG, PNG, GIF allowed.']);
                        exit;
                    }

                    if ($fileSize > 5 * 1024 * 1024) { // 5 MB limit
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'New image file size exceeds limit (5MB).']);
                        exit;
                    }

                    if (move_uploaded_file($fileTmpPath, $destPath)) {
                        $newImageUrl = '/uploads/' . $newFileName;
                        // Mark old image for deletion if a new one was uploaded
                        if ($currentImageUrl && file_exists(__DIR__ . $currentImageUrl)) {
                            $oldImageFileToDelete = __DIR__ . $currentImageUrl;
                        }
                    } else {
                        http_response_code(500);
                        echo json_encode(['success' => false, 'message' => 'Failed to move new uploaded file. Check permissions.']);
                        exit;
                    }
                } elseif (isset($_POST['existingImageUrl']) && $_POST['existingImageUrl'] === '') {
                    // Frontend explicitly indicates image removal
                    $newImageUrl = null;
                    if ($currentImageUrl && file_exists(__DIR__ . $currentImageUrl)) {
                        $oldImageFileToDelete = __DIR__ . $currentImageUrl;
                    }
                } else {
                    // No new image uploaded, and no explicit removal, keep existing image URL
                    $newImageUrl = $currentImageUrl;
                }

                // Update the item in the database
                $stmt = $db->prepare("UPDATE items SET name = :name, description = :description, price = :price, imageUrl = :imageUrl, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                $stmt->execute([
                    ':name' => $name,
                    ':description' => $description,
                    ':price' => $price,
                    ':imageUrl' => $newImageUrl,
                    ':id' => $itemIdToUpdate
                ]);

                if ($stmt->rowCount() > 0) {
                    // If DB update successful, delete the old image file if marked
                    if ($oldImageFileToDelete && file_exists($oldImageFileToDelete)) {
                        unlink($oldImageFileToDelete);
                    }

                    $updatedItem = [
                        'id' => $itemIdToUpdate,
                        'name' => $name,
                        'description' => $description,
                        'price' => $price,
                        'imageUrl' => $newImageUrl
                    ];
                    echo json_encode(['success' => true, 'message' => 'Item updated successfully!', 'item' => $updatedItem]);
                } else {
                    http_response_code(404); // Or 200 if no changes were made but item found
                    echo json_encode(['success' => false, 'message' => 'Item found but no changes applied or update failed.']);
                }

            } catch (PDOException $e) {
                http_response_code(500);
                error_log("Update item failed: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Failed to update item in database.']);
            }

        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
        }
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'API Endpoint not found']);
    }
    exit;
}

// --- Default Fallback: Serve the main HTML file ---
// This part remains mostly the same, ensuring your frontend SPA is served.
// Remember to start your PHP development server with `php -S localhost:8000 public/index.php`
readfile(__DIR__ . '/index.html');
exit;