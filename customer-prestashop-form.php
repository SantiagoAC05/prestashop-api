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
    
        // Show all customers
        public function getAllCustomers() {
            list($response, $httpCode) = $this->makeRequest('customers');
            if ($httpCode == 200) {
                // dump($response);
                return simplexml_load_string($response);
            }
            return false;
        }
    
        // Show a specific customer by ID
        public function getCustomer($customerId) {
            list($response, $httpCode) = $this->makeRequest('customers/' . $customerId);
            if ($httpCode == 200) {
                return simplexml_load_string($response);
            }
            return false;
        }
    
        // Create a new customer
        public function createCustomer($customerData) {    
            $firstname = $customerData['firstname'];
            $lastname = $customerData['lastname'];
            $email = $customerData['email'];
            $password = $customerData['password'];
            $active = 1;
    
            $xmlData = <<<XML
            <prestashop xmlns:xlink="http://www.w3.org/1999/xlink">
                <customer>
                    <id_gender>1</id_gender>
                    <firstname><![CDATA[$firstname]]></firstname>
                    <lastname><![CDATA[$lastname]]></lastname>
                    <email><![CDATA[$email]]></email>
                    <passwd><![CDATA[$password]]></passwd>
                    <active><![CDATA[$active]]></active>
                    <id_default_group>3</id_default_group>
                    <associations>
                        <groups>
                            <group>
                                <id>3</id>
                            </group>
                        </groups>
                    </associations>
                </customer>
            </prestashop>
            XML;
    
            // Inicializa cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->shopUrl . '/customers');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $this->apiKey . ':');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlData);
    
            $response = curl_exec($ch);
    
            // Verifica si hubo algún error
            if (curl_errno($ch)) {
                var_dump('Error en cURL: ' . curl_error($ch));
                curl_close($ch);
                // exit;
            }
    
            // Verificar el código de estado HTTP de la respuesta
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            // var_dump($httpCode);
            // var_dump('caca');
    
            if ($httpCode == 201) {
                return true;
            } else {
                return false;
            }
        }
    
        public function updateCustomer($customerId, $updatedData) {
            // Retrieve the existing customer data
            $existingCustomer = $this->getCustomer($customerId);
            if (!$existingCustomer) {
                echo "Customer with ID $customerId not found.\n";
                return false;
            }
        
            // Update the existing customer XML with new values
            if (isset($updatedData['firstname'])) {
                $existingCustomer->customer->firstname = $updatedData['firstname'];
            }
            if (isset($updatedData['lastname'])) {
                $existingCustomer->customer->lastname = $updatedData['lastname'];
            }
            if (isset($updatedData['email'])) {
                $existingCustomer->customer->email = $updatedData['email'];
            }
            if (isset($updatedData['password'])) {
                // Ensure password is hashed correctly
                $existingCustomer->customer->passwd = md5($updatedData['password']);
            }
            if (isset($updatedData['active'])) {
                $existingCustomer->customer->active = $updatedData['active'];
            }
        
            // Convert the updated SimpleXMLElement back to a string
            $xmlData = $existingCustomer->asXML();
        
            // Send the PUT request to update the customer
            list($response, $httpCode) = $this->makeRequest('customers/' . $customerId, 'PUT', $xmlData);
        
            if ($httpCode == 200) {
                echo "Customer updated successfully!\n";
                return true;
            } else {
                echo "Failed to update customer. HTTP Code: $httpCode\n";
                return false;
            }
        }
    
        // Delete a customer by ID
        public function deleteCustomer($customerId) {
            list($response, $httpCode) = $this->makeRequest('customers/' . $customerId, 'DELETE');
            return $httpCode == 200 ? true : false;
        }
    }

    // Example usage of PrestaShopAPI
    $shopUrl = 'http://192.168.0.123/api';
    $apiKey = '8UNK5A7EZRDC4JT928E2N5N4B5QDCS44';
    $api = new PrestaShopAPI($shopUrl, $apiKey);

    // Show all customers
    $customers = $api->getAllCustomers();

    // Retrieve customer by ID
    // Initialize customer details variable
    $customerDetails = null;

    // Check if the form has been submitted
    if (isset($_GET['retrieve_id'])) {
        $customerId = $_GET['retrieve_id'];  // Get the customer ID from the URL
        $customerDetails = $api->getCustomer($customerId);
        if ($customerDetails) {
        } else {
            echo "<p>Failed to fetch customer details for ID: $customerId</p>";
        }
    } else {
        // echo "No id for retrieving";
    }

    // Create a new customer
    if (isset($_POST['create'])) {
        $newCustomerData = [
            'firstname' => $_POST['firstname'],
            'lastname' => $_POST['lastname'],
            'email' => $_POST['email'],
            'password' => $_POST['password']
        ];
        if ($api->createCustomer($newCustomerData)) {
            $customers = $api->getAllCustomers();
            echo "<p>Customer created successfully!</p>";
        } else {
            echo "<p>Failed to create customer.</p>";
        }
    }

    // Update an existing customer
    if (isset($_POST['update'])) {
        $customerId = $_POST['update_id'];
        $updatedData = [
            'firstname' => $_POST['update_firstname'],
            'lastname' => $_POST['update_lastname'],
            'email' => $_POST['update_email'],
            'password' => $_POST['update_password'],
            'active' => $_POST['update_active']
        ];
        if ($api->updateCustomer($customerId, array_filter($updatedData))) {
            $customers = $api->getAllCustomers();
            if (isset($_GET['retrieve_id'])) {
                $customerId = $_GET['retrieve_id'];  // Get the customer ID from the URL
                $customerDetails = $api->getCustomer($customerId);
            }
            echo "<p>Customer updated successfully!</p>";
        } else {
            echo "<p>Failed to update customer.</p>";
        }
    }

    // Delete a customer by ID
    if (isset($_POST['delete'])) {
        $customerId = $_POST['delete_id'];
        if ($api->deleteCustomer($customerId)) {
            $customers = $api->getAllCustomers();
            echo "<p>Customer deleted successfully!</p>";
        } else {
            echo "<p>Failed to delete customer.</p>";
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PrestaShop Customers</title>
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
<h1>PrestaShop Customers</h1>
    <?php
    // Fetch all customers
    // $customers = getAllCustomers();

    if ($customers && $customers->customers->customer): ?>
        <table>
            <thead>
                <tr>
                    <th>Customer ID</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customers->customers->customer as $customer): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($customer['id']); ?></td>
                        <td><a href="<?php echo htmlspecialchars($customer['xlink:href']); ?>">View</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No customers found or failed to fetch customers.</p>
    <?php endif; ?>

    <h1>PrestaShop Customers Management</h1>

    <!-- Form to retrieve a specific customer -->
    <h2>Retrieve Customer by ID</h2>
        <form action="" method="get" id="retrieveForm">
        <label for="retrieve_id">Customer ID:</label>
        <input type="number" id="retrieve_id" name="retrieve_id" required>
        <button type="submit">Send</button>
    </form>

    <!-- SHOW SPECIFIC CUSTOMER -->
    <?php if ($customerDetails): ?>
        <h2>Specific Customer Details (ID: <?php echo htmlspecialchars($customerId); ?>)</h2>
        <table>
            <thead>
                <tr>
                    <th>Customer ID</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Email</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo htmlspecialchars($customerDetails->customer->id); ?></td>
                    <td><?php echo htmlspecialchars($customerDetails->customer->firstname); ?></td>
                    <td><?php echo htmlspecialchars($customerDetails->customer->lastname); ?></td>
                    <td><?php echo htmlspecialchars($customerDetails->customer->email); ?></td>
                </tr>
            </tbody>
        </table>
    <?php else: ?>
        <p>Sin ID ingresado</p>
    <?php endif; ?>
    
    <!-- Form to create a new customer -->
    <h2>Create New Customer</h2>
    <form action="" method="post">
        <label for="firstname">First Name:</label>
        <input type="text" id="firstname" name="firstname" required><br><br>
        <label for="lastname">Last Name:</label>
        <input type="text" id="lastname" name="lastname" required><br><br>
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required><br><br>
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required><br><br>
        <button type="submit" name="create">Create Customer</button>
    </form>

    <!-- Form to update an existing customer -->
    <h2>Update Existing Customer</h2>
    <form action="" method="post">
        <label for="update_id">Customer ID:</label>
        <input type="number" id="update_id" name="update_id" required><br><br>
        <label for="update_firstname">First Name:</label>
        <input type="text" id="update_firstname" name="update_firstname"><br><br>
        <label for="update_lastname">Last Name:</label>
        <input type="text" id="update_lastname" name="update_lastname"><br><br>
        <label for="update_email">Email:</label>
        <input type="email" id="update_email" name="update_email"><br><br>
        <label for="update_password">Password:</label>
        <input type="password" id="update_password" name="update_password"><br><br>
        <label for="update_active">Active (1 or 0):</label>
        <input type="number" id="update_active" name="update_active" min="0" max="1"><br><br>
        <button type="submit" name="update">Update Customer</button>
    </form>

    <!-- Form to delete a customer -->
    <h2>Delete Customer by ID</h2>
    <form action="" method="post">
        <label for="delete_id">Customer ID:</label>
        <input type="number" id="delete_id" name="delete_id" required>
        <button type="submit" name="delete">Delete Customer</button>
    </form>
</body>
</html>