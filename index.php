<?php

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
        echo 'cURL error: ' . htmlspecialchars($response);

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

// Example usage
$shopUrl = 'http://192.168.0.123/api';
$apiKey = '8UNK5A7EZRDC4JT928E2N5N4B5QDCS44';
$api = new PrestaShopAPI($shopUrl, $apiKey);

// Show all customers
$customers = $api->getAllCustomers();

// Show a specific customer
$customerId = 7; // Change to the desired customer ID
$customerDetails = $api->getCustomer($customerId);

// Create a new customer
$newCustomerData = [
    'firstname' => 'Johnnn',
    'lastname' => 'Doeee',
    'email' => 'john.doe3@example.com',
    'password' => 'password1232'
];
if ($api->createCustomer($newCustomerData)) {
    echo "Customer created successfully!\n";
} else {
    echo "Failed to create customer.\n";
}

// Show all customers after creating
$newcustomers = $api->getAllCustomers();

// Example usage of updating a customer
$customerIdToUpdate = 7; // Change to the desired customer ID
$updatedCustomerData = [
    'firstname' => 'Updated',
    'lastname' => 'User',
    'email' => 'updated.user@example.com',
    'password' => 'updpassword123',
    'active' => 1
];

if ($api->updateCustomer($customerIdToUpdate, $updatedCustomerData)) {
    echo "Customer updated successfully!\n";
} else {
    echo "Failed to update customer.\n";
}

// Show a specific customer after updating
$customerId = 7; // Change to the desired customer ID
$customerDetailsUpdated = $api->getCustomer($customerId);

// Delete a customer
$customerIdToDelete = 9; // Change to the desired customer ID
if ($api->deleteCustomer($customerIdToDelete)) {
    echo "Customer deleted successfully!\n";
} else {
    echo "Failed to delete customer.\n";
}

// Show all customers after creating
$deletedcustomers = $api->getAllCustomers();


?>
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
    <?php if ($customers): ?>
        <table>
            <thead>
                <tr>
                    <th>Customer ID</th>
                    <th>Link</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customers->customers->customer as $customer): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($customer['id']); ?></td>
                        <td><a href="<?php echo htmlspecialchars($customer['xlink:href']); ?>">View Customer</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No customers found or failed to fetch customers.</p>
    <?php endif; ?>
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
        <p>Failed to fetch details for customer ID: <?php echo htmlspecialchars($customerId); ?>.</p>
    <?php endif; ?>
    <h1>NEW PrestaShop Customers</h1>
    <?php if ($newcustomers): ?>
        <table>
            <thead>
                <tr>
                    <th>Customer ID</th>
                    <th>Link</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($newcustomers->customers->customer as $customer): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($customer['id']); ?></td>
                        <td><a href="<?php echo htmlspecialchars($customer['xlink:href']); ?>">View Customer</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No customers found or failed to fetch customers.</p>
    <?php endif; ?>
    <?php if ($customerDetails): ?>
        <h2>Updated Specific Customer Details (ID: <?php echo htmlspecialchars($customerId); ?>)</h2>
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
        <p>Failed to fetch details for customer ID: <?php echo htmlspecialchars($customerId); ?>.</p>
    <?php endif; ?>
    <h1>DELETED PrestaShop Customers</h1>
    <?php if ($deletedcustomers): ?>
        <table>
            <thead>
                <tr>
                    <th>Customer ID</th>
                    <th>Link</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($deletedcustomers->customers->customer as $customer): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($customer['id']); ?></td>
                        <td><a href="<?php echo htmlspecialchars($customer['xlink:href']); ?>">View Customer</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No customers found or failed to fetch customers.</p>
    <?php endif; ?>
</body>
</html>
