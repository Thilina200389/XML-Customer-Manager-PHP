<?php
header('Content-Type: application/json');

$xmlFile = 'customer.xml';

// Helper function to load XML
function loadXml($file) {
    if (!file_exists($file)) {
        die(json_encode(['status' => 'error', 'message' => 'XML file not found']));
    }
    return simplexml_load_file($file);
}

// Helper to save XML
function saveXml($xml, $file) {
    // Formatting for pretty print
    $dom = new DOMDocument("1.0");
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml->asXML());
    $dom->save($file);
}

$xml = loadXml($xmlFile);
$customers = [];

// Convert XML object to array for easier navigation by index
foreach ($xml->customer as $cust) {
    $customers[] = [
        'id' => (string)$cust['id'],
        'name' => (string)$cust->name,
        'telno' => (string)$cust->telno,
        'email' => (string)$cust->email,
        'street' => (string)$cust->address->street,
        'city' => (string)$cust->address->city,
        'state' => (string)$cust->address->state,
        'zip' => (string)$cust->address->zip
    ];
}

$action = $_POST['action'] ?? '';
$currentIndex = isset($_POST['currentIndex']) ? (int)$_POST['currentIndex'] : 0;
$total = count($customers);

//NAVIGATION LOGIC 
if ($action === 'navigate') {
    $direction = $_POST['direction'];
    $newIndex = $currentIndex;

    if ($direction === 'first') $newIndex = 0;
    elseif ($direction === 'last') $newIndex = $total - 1;
    elseif ($direction === 'next') $newIndex++;
    elseif ($direction === 'prev') $newIndex--;

    // Bounds check
    if ($newIndex < 0) {
        echo json_encode(['status' => 'error', 'message' => 'Already at first record.']);
        exit;
    }
    if ($newIndex >= $total) {
        echo json_encode(['status' => 'error', 'message' => 'Already at last record.']);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'data' => $customers[$newIndex],
        'index' => $newIndex
    ]);
    exit;
}


//SEARCH

if ($action === 'search') {
    $searchTerm = strtolower($_POST['name']);
    $foundIndices = []; // Array to store all matches
    
    foreach ($customers as $index => $c) {
        // substring check (optional) or exact match
        // Using strpos allows partial matches 
        if (strpos(strtolower($c['name']), $searchTerm) !== false) {
            $foundIndices[] = $index;
        }
    }

    if (count($foundIndices) > 0) {
        echo json_encode([
            'status' => 'success',
            'message' => count($foundIndices) . ' record(s) found.',
            'foundIndices' => $foundIndices, // Return ALL matching indices
            'firstIndex' => $foundIndices[0],
            'data' => $customers[$foundIndices[0]]
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Customer not found.']);
    }
    exit;
}

//ADD NEW
if ($action === 'add') {
    // Generate new ID
    $maxId = 0;
    foreach ($xml->customer as $c) {
        $id = (int)$c['id'];
        if ($id > $maxId) $maxId = $id;
    }
    $newId = ($maxId > 0) ? $maxId + 1 : 12000;

    $newCust = $xml->addChild('customer');
    $newCust->addAttribute('id', $newId);
    $newCust->addChild('name', $_POST['name']);
    $newCust->addChild('telno', $_POST['telno']);
    $newCust->addChild('email', $_POST['email']);
    $addr = $newCust->addChild('address');
    $addr->addChild('street', $_POST['street']);
    $addr->addChild('city', $_POST['city']);
    $addr->addChild('state', $_POST['state']);
    $addr->addChild('zip', $_POST['zip']);

    saveXml($xml, $xmlFile);

    // Return the new data to update UI
    echo json_encode([
        'status' => 'success',
        'message' => 'New customer added.',
        'data' => [
            'id' => $newId, 'name' => $_POST['name'], 'telno' => $_POST['telno'],
            'email' => $_POST['email'], 'street' => $_POST['street'],
            'city' => $_POST['city'], 'state' => $_POST['state'], 'zip' => $_POST['zip']
        ],
        'index' => $total // New index is at the end
    ]);
    exit;
}

//UPDATE
if ($action === 'update') {
    $targetId = $_POST['id'];
    $targetNode = null;

    foreach ($xml->customer as $cust) {
        if ((string)$cust['id'] == $targetId) {
            $targetNode = $cust;
            break;
        }
    }

    if ($targetNode) {
        $targetNode->name = $_POST['name'];
        $targetNode->telno = $_POST['telno'];
        $targetNode->email = $_POST['email'];
        $targetNode->address->street = $_POST['street'];
        $targetNode->address->city = $_POST['city'];
        $targetNode->address->state = $_POST['state'];
        $targetNode->address->zip = $_POST['zip'];

        saveXml($xml, $xmlFile);
        echo json_encode(['status' => 'success', 'message' => 'Record updated successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Record to update not found.']);
    }
    exit;
}

//DELETE
if ($action === 'delete') {
    $targetId = $_POST['id'];
    $indexToDelete = -1;
    $i = 0;

    foreach ($xml->customer as $cust) {
        if ((string)$cust['id'] == $targetId) {
            $dom = dom_import_simplexml($cust);
            $dom->parentNode->removeChild($dom);
            $indexToDelete = $i;
            break;
        }
        $i++;
    }

    if ($indexToDelete !== -1) {
        saveXml($xml, $xmlFile);
        echo json_encode(['status' => 'success', 'message' => 'Record deleted.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Could not delete record.']);
    }
    exit;
}
?>