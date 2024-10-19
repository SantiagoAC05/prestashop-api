<?php

// METHODS TO CONNECT TO THE PRESTASHOP API
class PrestaShopAPI {
    private $shopUrl;
    private $apiKey;

    public function __construct($shopUrl, $apiKey) {
        $this->shopUrl = rtrim($shopUrl, '/');
        $this->apiKey = $apiKey;
    }

    // Function to make the request
    private function makeRequest($endpoint, $method = 'GET', $data = null) {
        $url = $this->shopUrl . '/' . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERPWD, $this->apiKey . ':');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/xml']);

        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [$response, $httpCode];
    }

    // Show all products
    public function getAllProducts() {
        list($response, $httpCode) = $this->makeRequest('products');
        if ($httpCode == 200) {
            return simplexml_load_string($response);
        }
        return false;
    }

    // Show a specific product by ID
    public function getProduct($productId) {
        list($response, $httpCode) = $this->makeRequest('products/' . $productId);
        if ($httpCode == 200) {
            return simplexml_load_string($response);
        }
        return false;
    }

    // Create a new product
    public function createProduct($productData) {
        // echo 'xd';
        $xmlData = <<<XML
        <prestashop xmlns:xlink="http://www.w3.org/1999/xlink">
            <product>
                <id_category_default><![CDATA[{$productData['id_category_default']}]]></id_category_default>
                <price><![CDATA[{$productData['price']}]]></price>
                <name>
                    <language id="1"><![CDATA[{$productData['name']}]]></language> <!-- Name in language ID 1 -->
                </name>
                <active><![CDATA[1]]></active> <!-- Product active status -->
                <reference><![CDATA[SP001]]></reference> <!-- Product reference -->
                <id_tax><![CDATA[1]]></id_tax> <!-- Tax rule ID -->
                <description>
                    <language id="1"><![CDATA[This is a sample product description.]]></language> <!-- Description -->
                </description>
                <description_short>
                    <language id="1"><![CDATA[Short description of the product.]]></language> <!-- Short description -->
                </description_short>
                <associations>
                    <categories>
                        <category>
                            <id><![CDATA[1]]></id> <!-- Category ID -->
                        </category>
                    </categories>
                </associations>
            </product>
        </prestashop>
        XML;
        list($response, $httpCode) = $this->makeRequest('products', 'POST', $xmlData);
        echo $httpCode;

        return $httpCode == 201;
    }

    // Update an existing product
    public function updateProduct($productId, $updatedData) {
        // Retrieve the existing product data
        $existingProduct = $this->getProduct($productId);
        if (!$existingProduct) {
            echo "Product with ID $productId not found.\n";
            return false;
        }

        $xmlData = <<<XML
        <prestashop xmlns:xlink="http://www.w3.org/1999/xlink">
            <product>
                <id><![CDATA[$productId]]></id>
                <price><![CDATA[{$updatedData['price']}]]></price>
            </product>
        </prestashop>
        XML;

        // Send the PUT request to update the product
        list($response, $httpCode) = $this->makeRequest('products/' . $productId, 'PUT', $xmlData);

        if ($httpCode == 200) {
            echo "Product updated successfully!\n";
            return true;
        } else {
            echo "Failed to update product. HTTP Code: $httpCode\n";
            return false;
        }
    }

    // Delete a product by ID
    public function deleteProduct($productId) {
        list($response, $httpCode) = $this->makeRequest('products/' . $productId, 'DELETE');
        return $httpCode == 200;
    }
}

// Example usage of PrestaShopAPI
$shopUrl = 'http://192.168.0.123/api';
$apiKey = '8UNK5A7EZRDC4JT928E2N5N4B5QDCS44';
$api = new PrestaShopAPI($shopUrl, $apiKey);

// Show all products
$products = $api->getAllProducts();

// Retrieve product by ID
$productDetails = null;
if (isset($_GET['retrieve_id'])) {
    $productId = $_GET['retrieve_id'];
    $productDetails = $api->getProduct($productId);
    if (!$productDetails) {
        echo "<p>Failed to fetch product details for ID: $productId</p>";
    }
}

// Create a new product
if (isset($_POST['create'])) {
    $newProductData = [
        'id_category_default' => $_POST['id_category_default'],
        'name' => $_POST['name'],
        'price' => $_POST['price']
    ];
    if ($api->createProduct($newProductData)) {
        $products = $api->getAllProducts();
        echo "<p>Product created successfully!</p>";
    } else {
        echo "<p>Failed to create product.</p>";
    }
}

// Update an existing product
if (isset($_POST['update'])) {
    $productId = $_POST['update_id'];
    $updatedData = [
        'price' => $_POST['update_price']
    ];
    if ($api->updateProduct($productId, array_filter($updatedData))) {
        $products = $api->getAllProducts();
        if (isset($_GET['retrieve_id'])) {
            $productId = $_GET['retrieve_id'];
            $productDetails = $api->getProduct($productId);
        }
        echo "<p>Product updated successfully!</p>";
    } else {
        echo "<p>Failed to update product.</p>";
    }
}

// Delete a product by ID
if (isset($_POST['delete'])) {
    $productId = $_POST['delete_id'];
    if ($api->deleteProduct($productId)) {
        $products = $api->getAllProducts();
        if (isset($_GET['retrieve_id'])) {
            $productId = $_GET['retrieve_id'];
            $productDetails = $api->getProduct($productId);
        }
        echo "<p>Product deleted successfully!</p>";
    } else {
        echo "<p>Failed to delete product.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PrestaShop Products</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table, th, td {
            border: 1px solid black;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
    </style>
</head>
<body>
<h1>PrestaShop Products</h1>
    <?php
    // Fetch all products
    if ($products && $products->products->product): ?>
        <table>
            <thead>
                <tr>
                    <th>Product ID</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products->products->product as $product): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($product['id']); ?></td>
                        <td><a href="<?php echo htmlspecialchars($product['xlink:href']); ?>">View</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No products found or failed to fetch products.</p>
    <?php endif; ?>

    <h1>PrestaShop Products Management</h1>

    <!-- Form to retrieve a specific product -->
    <h2>Retrieve Product by ID</h2>
    <form action="" method="get" id="retrieveForm">
        <label for="retrieve_id">Product ID:</label>
        <input type="number" id="retrieve_id" name="retrieve_id" required>
        <button type="submit">Send</button>
    </form>

    <!-- SHOW SPECIFIC PRODUCT -->
    <?php if ($productDetails): ?>
        <h2>Specific Product Details (ID: <?php echo htmlspecialchars($productId); ?>)</h2>
        <table>
            <thead>
                <tr>
                    <th>Product ID</th>
                    <th>Type</th>
                    <th>Price</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo htmlspecialchars($productDetails->product->id); ?></td>
                    <td><?php echo htmlspecialchars($productDetails->product->product_type); ?></td>
                    <td><?php echo htmlspecialchars($productDetails->product->price); ?></td>
                </tr>
            </tbody>
        </table>
    <?php else: ?>
        <p>No ID entered</p>
    <?php endif; ?>
    
    <!-- Form to create a new product -->
    <h2>Create New Product</h2>
    <form action="" method="post">
        <label for="id_category_default">Category ID:</label>
        <input type="number" id="id_category_default" name="id_category_default" required>
        <label for="name">Product Name:</label>
        <input type="text" id="name" name="name" required>
        <label for="price">Price:</label>
        <input type="number" id="price" name="price" step="0.01" required>
        <button type="submit" name="create">Create Product</button>
    </form>

    <!-- Form to update a product -->
    <h2>Update Existing Product</h2>
    <form action="" method="post">
        <label for="update_id">Product ID:</label>
        <input type="number" id="update_id" name="update_id" required>
        <label for="update_price">New Price:</label>
        <input type="number" id="update_price" name="update_price" step="0.01">
        <button type="submit" name="update">Update Product</button>
    </form>

    <!-- Form to delete a product -->
    <h2>Delete Product</h2>
    <form action="" method="post">
        <label for="delete_id">Product ID:</label>
        <input type="number" id="delete_id" name="delete_id" required>
        <button type="submit" name="delete">Delete Product</button>
    </form>
</body>
</html>
