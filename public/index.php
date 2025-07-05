<?php
// public/index.php

$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$data_file_path = __DIR__ . '/data/items.json';
$upload_dir = __DIR__ . '/uploads/';

// Ensure upload directory exists and is writable
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Function to read items from JSON file
function get_items($file_path)
{
    if (file_exists($file_path)) {
        $file_content = file_get_contents($file_path);
        if ($file_content === false) {
            return [['error' => 'Failed to read items file.'], 500];
        }
        $decoded_content = json_decode($file_content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_content)) {
            // Crucial: Ensure 'id' is an integer upon reading
            foreach ($decoded_content as &$item) { // Use & for reference to modify in place
                if (isset($item['id'])) {
                    $item['id'] = (int) $item['id'];
                }
            }
            return [$decoded_content, 200];
        } else {
            return [['error' => 'Invalid JSON data in items file.'], 500];
        }
    } else {
        return [[], 200];
    }
}

// Function to save items to JSON file
function save_items($file_path, $items)
{
    if (file_put_contents($file_path, json_encode($items, JSON_PRETTY_PRINT)) !== false) {
        return true;
    } else {
        return false;
    }
}


// --- PHP API Endpoints ---
if (strpos($request_uri, '/api/') === 0) {
    header('Content-Type: application/json');

    // API endpoint: /api/data (for general testing)
    if ($request_uri === '/api/data') {
        echo json_encode(['message' => 'Data from PHP Backend!', 'timestamp' => time(), 'server_time' => date('Y-m-d H:i:s')]);
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
            $newItem = [
                'name' => $_POST['name'] ?? '',
                'description' => $_POST['description'] ?? '',
                'price' => $_POST['price'] ?? 0,
                'id' => (int) ($_POST['id'] ?? (int) round(microtime(true) * 1000)), // Unique ID generation
                'imageUrl' => null
            ];

            if (empty($newItem['name']) || empty($newItem['description']) || !isset($newItem['price'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing required item fields (name, description, price).']);
                exit;
            }

            if (!is_numeric($newItem['price'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Price must be a number.']);
                exit;
            }
            $newItem['price'] = (float) $newItem['price'];

            if (isset($_FILES['itemImage']) && $_FILES['itemImage']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['itemImage']['tmp_name'];
                $fileName = $_FILES['itemImage']['name'];
                $fileSize = $_FILES['itemImage']['size'];
                $fileType = $_FILES['itemImage']['type'];
                $fileNameCmps = explode(".", $fileName);
                $fileExtension = strtolower(end($fileNameCmps));

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
                    $newItem['imageUrl'] = '/uploads/' . $newFileName;
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file. Check permissions.']);
                    exit;
                }
            } else {
                // If it's a new item, image is mandatory
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'No image file uploaded for new item.']);
                exit;
            }

            list($current_items, $status_code) = get_items($data_file_path);
            if ($status_code !== 200) {
                http_response_code($status_code);
                echo json_encode($current_items);
                exit;
            }

            $current_items[] = $newItem;

            if (save_items($data_file_path, $current_items)) {
                echo json_encode(['success' => true, 'message' => 'Item added successfully!', 'item' => $newItem]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to write data to file. Check permissions.']);
            }

        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
        }
    }
    // Get Items Endpoint
    elseif ($request_uri === '/api/items/get') {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            list($items, $status_code) = get_items($data_file_path);
            http_response_code($status_code);
            echo json_encode($items);
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

            list($current_items, $status_code) = get_items($data_file_path);
            if ($status_code !== 200) {
                http_response_code($status_code);
                echo json_encode($current_items);
                exit;
            }

            $itemDeleted = false;
            $updated_items = [];
            $imageToDeletePath = null;

            foreach ($current_items as $item) {
                if ($item['id'] === $itemIdToDelete) {
                    $itemDeleted = true;
                    // Store image path for deletion *before* removing the item
                    if (isset($item['imageUrl']) && !empty($item['imageUrl'])) {
                        $imageFileName = basename($item['imageUrl']);
                        $imageToDeletePath = $upload_dir . $imageFileName;
                    }
                } else {
                    $updated_items[] = $item;
                }
            }

            if (!$itemDeleted) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Item not found.']);
                exit;
            }

            if (save_items($data_file_path, $updated_items)) {
                // Delete the image file *after* saving the updated items.
                if ($imageToDeletePath && file_exists($imageToDeletePath)) {
                    unlink($imageToDeletePath);
                }
                echo json_encode(['success' => true, 'message' => 'Item deleted successfully!']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to save updated items.']);
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

            list($current_items, $status_code) = get_items($data_file_path);
            if ($status_code !== 200) {
                http_response_code($status_code);
                echo json_encode($current_items);
                exit;
            }

            $itemUpdated = false;
            $oldImageToDeletePath = null; // Store path of old image if it's being replaced

            foreach ($current_items as &$item) { // Use & for reference to modify the item in place
                if ($item['id'] === $itemIdToUpdate) {
                    $itemUpdated = true;

                    // Store the old image path *before* updating imageUrl, if a new image is uploaded
                    if (isset($item['imageUrl']) && !empty($item['imageUrl']) && isset($_FILES['itemImage']) && $_FILES['itemImage']['error'] === UPLOAD_ERR_OK) {
                        $oldImageFileName = basename($item['imageUrl']);
                        $oldImageToDeletePath = $upload_dir . $oldImageFileName;
                    }

                    // Update item properties from POST data
                    $item['name'] = $_POST['name'] ?? $item['name'];
                    $item['description'] = $_POST['description'] ?? $item['description'];
                    $item['price'] = isset($_POST['price']) ? (float) $_POST['price'] : $item['price'];

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
                            $item['imageUrl'] = '/uploads/' . $newFileName;
                        } else {
                            http_response_code(500);
                            echo json_encode(['success' => false, 'message' => 'Failed to move new uploaded file. Check permissions.']);
                            exit;
                        }
                    } else if (isset($_POST['existingImageUrl'])) { // No new image, but existing image path provided
                        $item['imageUrl'] = $_POST['existingImageUrl'];
                    } else { // No new image and no existing image path, means image was removed or never existed
                        $item['imageUrl'] = null;
                        // If there was an old image, mark it for deletion even if not replaced by a new one
                        if (isset($item['imageUrl']) && !empty($item['imageUrl'])) { // Check old value before setting to null
                            $oldImageFileName = basename($item['imageUrl']);
                            $oldImageToDeletePath = $upload_dir . $oldImageFileName;
                        }
                    }

                    // Ensure the ID type is consistent (already handled by get_items but good to be explicit)
                    $item['id'] = (int) $item['id'];

                    break; // Exit loop after updating the item
                }
            }

            if (!$itemUpdated) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Item to update not found.']);
                exit;
            }

            if (save_items($data_file_path, $current_items)) {
                // Delete old image file if a new one was uploaded or the image was explicitly removed
                if ($oldImageToDeletePath && file_exists($oldImageToDeletePath)) {
                    unlink($oldImageToDeletePath);
                }
                echo json_encode(['success' => true, 'message' => 'Item updated successfully!', 'item' => $item]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to save updated items. Check permissions.']);
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
readfile(__DIR__ . '/index.html');
exit;