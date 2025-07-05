<?php
// public/app/Managers/ItemManager.php

namespace App\Managers;

use PDO;
use PDOException;
use App\Config\Config; // Import the Config class for paths and constants

class ItemManager
{
    private PDO $db; // PDO database connection instance
    private string $uploadDirPath; // Absolute path to the uploads directory

    /**
     * Constructor for ItemManager.
     * @param PDO $db The PDO database connection object.
     * @param string $doc_root The document root (e.g., __DIR__ from public/index.php) to resolve paths.
     */
    public function __construct(PDO $db, string $doc_root)
    {
        $this->db = $db;
        // Get the absolute path to the uploads directory from Config
        $this->uploadDirPath = Config::getUploadDirPath($doc_root);

        // Ensure the upload directory exists and is writable
        if (!is_dir($this->uploadDirPath)) {
            mkdir($this->uploadDirPath, 0777, true); // Create directory if it doesn't exist
        }
    }

    /**
     * Retrieves all items from the database.
     * @return array An array containing item data or an error message and HTTP status.
     */
    public function getAllItems(): array
    {
        try {
            // Prepare and execute a SELECT query to get all items, ordered by ID descending
            $stmt = $this->db->query("SELECT id, name, description, price, imageUrl FROM items ORDER BY id DESC");
            $items = $stmt->fetchAll(); // Fetch all results

            // Ensure 'id' is an integer and 'price' is a float for consistent JSON output
            foreach ($items as &$item) {
                $item['id'] = (int) $item['id'];
                $item['price'] = (float) $item['price'];
            }
            return ['data' => $items, 'status' => 200]; // Return data with 200 OK status
        } catch (PDOException $e) {
            // Log any database errors
            error_log("Get items failed: " . $e->getMessage());
            return ['error' => 'Failed to retrieve items from database.', 'status' => 500]; // Return error with 500 status
        }
    }

    /**
     * Adds a new item to the database, including handling image uploads.
     * @param array $data Associative array of item data (name, description, price).
     * @param array|null $file Associative array from $_FILES for the item image.
     * @return array An array indicating success/failure, a message, item data, and HTTP status.
     */
    public function addItem(array $data, ?array $file): array
    {
        // Extract and validate input data
        $name = $data['name'] ?? '';
        $description = $data['description'] ?? '';
        $price = $data['price'] ?? null;

        if (empty($name) || empty($description) || !isset($price)) {
            return ['success' => false, 'message' => 'Missing required item fields (name, description, price).', 'status' => 400];
        }
        if (!is_numeric($price)) {
            return ['success' => false, 'message' => 'Price must be a number.', 'status' => 400];
        }
        $price = (float) $price;

        $imageUrl = null; // Stores the URL path for the image in the database
        $uploadedFilePath = null; // Stores the absolute path of the uploaded file for potential cleanup

        // Handle image upload if a file is provided and no errors occurred
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $file['tmp_name'];
            $fileName = $file['name'];
            $fileSize = $file['size'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            // Generate a unique file name to prevent conflicts
            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
            $destPath = $this->uploadDirPath . $newFileName; // Absolute destination path

            // Validate file type
            $allowedFileExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($fileExtension, $allowedFileExtensions)) {
                return ['success' => false, 'message' => 'Invalid file type. Only JPG, JPEG, PNG, GIF allowed.', 'status' => 400];
            }
            // Validate file size (5 MB limit)
            if ($fileSize > 5 * 1024 * 1024) {
                return ['success' => false, 'message' => 'File size exceeds limit (5MB).', 'status' => 400];
            }

            // Move the uploaded file from temp to its permanent location
            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $imageUrl = Config::UPLOAD_DIR_RELATIVE . $newFileName; // Store relative URL for database
                $uploadedFilePath = $destPath; // Keep absolute path for cleanup if DB insert fails
            } else {
                return ['success' => false, 'message' => 'Failed to move uploaded file. Check permissions.', 'status' => 500];
            }
        } else {
            // Per original logic, an image is mandatory for a new item
            return ['success' => false, 'message' => 'No image file uploaded for new item.', 'status' => 400];
        }

        try {
            // Prepare and execute an INSERT statement to add the new item to the database
            $stmt = $this->db->prepare("INSERT INTO items (name, description, price, imageUrl) VALUES (:name, :description, :price, :imageUrl)");
            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':price' => $price,
                ':imageUrl' => $imageUrl
            ]);

            // Get the ID of the newly inserted item
            $newItemId = $this->db->lastInsertId();
            $newItem = [
                'id' => (int) $newItemId,
                'name' => $name,
                'description' => $description,
                'price' => $price,
                'imageUrl' => $imageUrl
            ];

            return ['success' => true, 'message' => 'Item added successfully!', 'item' => $newItem, 'status' => 201]; // 201 Created status
        } catch (PDOException $e) {
            // If the database insert fails, attempt to delete the uploaded image file to prevent orphans
            if ($uploadedFilePath && file_exists($uploadedFilePath)) {
                unlink($uploadedFilePath);
            }
            error_log("Add item failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to add item to database.', 'status' => 500];
        }
    }

    /**
     * Updates an existing item in the database, including image replacement/removal.
     * @param int $id The ID of the item to update.
     * @param array $data Associative array of item data (name, description, price, existingImageUrl).
     * @param array|null $file Associative array from $_FILES for the new item image.
     * @return array An array indicating success/failure, a message, updated item data, and HTTP status.
     */
    public function updateItem(int $id, array $data, ?array $file): array
    {
        try {
            // First, fetch the existing item details from the database
            $stmt = $this->db->prepare("SELECT id, name, description, price, imageUrl FROM items WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $existingItem = $stmt->fetch();

            if (!$existingItem) {
                return ['success' => false, 'message' => 'Item to update not found.', 'status' => 404];
            }

            // Get current values or use provided new values from $data
            $name = $data['name'] ?? $existingItem['name'];
            $description = $data['description'] ?? $existingItem['description'];
            $price = isset($data['price']) ? (float) $data['price'] : (float) $existingItem['price'];
            $currentImageUrl = $existingItem['imageUrl']; // The image URL currently in the DB
            $newImageUrl = $currentImageUrl; // Initialize new image URL with current one
            $oldImageFileToDelete = null; // Path to old image file on disk, if it needs to be deleted

            // --- Handle Image Upload/Replacement/Removal Logic ---
            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                // A new image file has been uploaded
                $fileTmpPath = $file['tmp_name'];
                $fileName = $file['name'];
                $fileSize = $file['size'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                $destPath = $this->uploadDirPath . $newFileName;

                $allowedFileExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                if (!in_array($fileExtension, $allowedFileExtensions)) {
                    return ['success' => false, 'message' => 'Invalid file type for new image. Only JPG, JPEG, PNG, GIF allowed.', 'status' => 400];
                }
                if ($fileSize > 5 * 1024 * 1024) {
                    return ['success' => false, 'message' => 'New image file size exceeds limit (5MB).', 'status' => 400];
                }

                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $newImageUrl = Config::UPLOAD_DIR_RELATIVE . $newFileName; // Update URL for DB
                    // If there was an old image, mark its absolute path for deletion
                    if ($currentImageUrl && file_exists($this->uploadDirPath . basename($currentImageUrl))) {
                        $oldImageFileToDelete = $this->uploadDirPath . basename($currentImageUrl);
                    }
                } else {
                    return ['success' => false, 'message' => 'Failed to move new uploaded file. Check permissions.', 'status' => 500];
                }
            } elseif (isset($data['existingImageUrl']) && $data['existingImageUrl'] === '') {
                // The frontend explicitly sent an empty 'existingImageUrl', meaning the image was removed
                $newImageUrl = null; // Set image URL in DB to null
                // If there was an old image, mark its absolute path for deletion
                if ($currentImageUrl && file_exists($this->uploadDirPath . basename($currentImageUrl))) {
                    $oldImageFileToDelete = $this->uploadDirPath . basename($currentImageUrl);
                }
            } else {
                // No new image uploaded, and no explicit removal request: keep the existing image URL
                $newImageUrl = $currentImageUrl;
            }

            // Prepare and execute the UPDATE statement
            $stmt = $this->db->prepare("UPDATE items SET name = :name, description = :description, price = :price, imageUrl = :imageUrl, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':price' => $price,
                ':imageUrl' => $newImageUrl,
                ':id' => $id
            ]);

            // Check if any rows were affected by the update query
            // OR if the image was updated but no other DB fields changed (rowCount would be 0)
            if ($stmt->rowCount() > 0 || ($newImageUrl !== $currentImageUrl)) {
                // If database update was successful or image was changed, delete the old physical image file if marked
                if ($oldImageFileToDelete && file_exists($oldImageFileToDelete)) {
                    unlink($oldImageFileToDelete);
                }
                $updatedItem = [
                    'id' => $id,
                    'name' => $name,
                    'description' => $description,
                    'price' => $price,
                    'imageUrl' => $newImageUrl
                ];
                return ['success' => true, 'message' => 'Item updated successfully!', 'item' => $updatedItem, 'status' => 200];
            } else {
                // If item was found but no data (other than potentially image, which we handle) actually changed
                return ['success' => false, 'message' => 'Item found but no changes applied or update failed.', 'status' => 200];
            }

        } catch (PDOException $e) {
            error_log("Update item failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update item in database.', 'status' => 500];
        }
    }

    /**
     * Deletes an item from the database, including its associated image file.
     * @param int $id The ID of the item to delete.
     * @return array An array indicating success/failure, a message, and HTTP status.
     */
    public function deleteItem(int $id): array
    {
        try {
            // First, retrieve the imageUrl of the item before deleting the record
            $stmt = $this->db->prepare("SELECT imageUrl FROM items WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $item = $stmt->fetch();

            if (!$item) {
                return ['success' => false, 'message' => 'Item not found.', 'status' => 404];
            }

            // Prepare and execute the DELETE statement
            $stmt = $this->db->prepare("DELETE FROM items WHERE id = :id");
            $stmt->execute([':id' => $id]);

            // Check if a row was actually deleted
            if ($stmt->rowCount() > 0) {
                // If successfully deleted from DB, proceed to delete the physical image file
                $imageToDeletePath = $item['imageUrl'];
                // Construct the absolute path to the image file
                if ($imageToDeletePath && file_exists($this->uploadDirPath . basename($imageToDeletePath))) {
                    unlink($this->uploadDirPath . basename($imageToDeletePath)); // Delete the file
                }
                return ['success' => true, 'message' => 'Item deleted successfully!', 'status' => 200];
            } else {
                // This case should ideally not be reached if $item was found initially
                return ['success' => false, 'message' => 'Failed to delete item (possibly not found after check).', 'status' => 404];
            }

        } catch (PDOException $e) {
            error_log("Delete item failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete item from database.', 'status' => 500];
        }
    }
}