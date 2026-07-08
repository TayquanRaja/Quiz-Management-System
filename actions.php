<?php
// actions.php
header('Content-Type: application/json');
require_once 'db.php'; // Uses your mysqli database connection ($conn)

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'fetch_all':
        // Includes security questions and answers in our fetch block query
        $sql = "SELECT id, full_name, email, role, security_question, security_answer FROM users ORDER BY id DESC";
        $result = $conn->query($sql);

        if ($result) {
            $users = [];
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $users]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        break;

    case 'add':
        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $role = trim($data['role'] ?? 'student');
        $security_question = trim($data['security_question'] ?? '');
        $security_answer = trim($data['security_answer'] ?? '');

        if (empty($name) || empty($email) || empty($security_answer)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            exit;
        }

        // Email uniqueness validation
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $checkStmt->store_result();
        
        if ($checkStmt->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Email is already registered.']);
            $checkStmt->close();
            exit;
        }
        $checkStmt->close();

        $dummyPassword = password_hash("123456", PASSWORD_BCRYPT);
        $authProvider = 'normal';

        // Bind parameters safely 
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, security_question, security_answer, created_at, auth_provider) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)");
        $stmt->bind_param("sssssss", $name, $email, $dummyPassword, $role, $security_question, $security_answer, $authProvider);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User added successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => $stmt->error]);
        }
        $stmt->close();
        break;

    case 'update':
        $id = intval($data['id'] ?? 0);
        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $role = trim($data['role'] ?? 'student');
        $security_question = trim($data['security_question'] ?? '');
        $security_answer = trim($data['security_answer'] ?? '');

        if (!$id || empty($name) || empty($email) || empty($security_answer)) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters provided.']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, security_question = ?, security_answer = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $name, $email, $role, $security_question, $security_answer, $id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User updated successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => $stmt->error]);
        }
        $stmt->close();
        break;

    case 'delete':
        $id = intval($data['id'] ?? 0);

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid user ID key.']);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User removed successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => $stmt->error]);
        }
        $stmt->close();
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid structural operation context action.']);
        break;
}

$conn->close();
?>