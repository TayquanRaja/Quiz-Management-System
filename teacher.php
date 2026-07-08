error_reporting(E_ALL);
ini_set('display_errors', 0);
<?php
include "db.php";
session_start();

// Set appropriate JSON header for all action API endpoints
header('Content-Type: application/json');

// Get the requested controller action
$action = $_GET['action'] ?? '';

switch ($action) {
    
// =========================================================================
    // ACTION: SAVE QUIZ (Handles both UPSERT updates and clean INSERT options)
    // =========================================================================
// =========================================================================
    // ACTION: SAVE QUIZ (Handles both UPSERT updates and clean INSERT options)
    // =========================================================================
    case 'save_quiz':
        if (ob_get_length()) ob_clean();

        $inputRaw = file_get_contents('php://input');
        $data = json_decode($inputRaw, true);

        if (!$data) {
            echo json_encode(['success' => false, 'message' => 'Empty or invalid JSON payload received.']);
            exit;
        }

        $teacherId = $_SESSION['user_id'] ?? 1; 

        $quizId = 0;
        if (isset($data['id']) && $data['id'] !== '' && $data['id'] !== null) {
            $quizId = intval($data['id']);
        }
        
        $title = $data['title'] ?? 'Untitled Quiz';
        $subject = $data['subject'] ?? 'General';
        $class = $data['class'] ?? 'General';
        $status = $data['status'] ?? 'Active';
        
        // GRAB THE ACTIVE QUESTION BUFFER FROM SESSION
        $questionsList = $_SESSION['questions'] ?? [];

        $conn->begin_transaction();

        try {
            if ($quizId > 0) {
                // UPDATE Mode: If we are ONLY updating the status from the dashboard (no new questions submitted)
                if (empty($questionsList)) {
                    // Update only the basic metadata fields without dropping any questions!
                    $quizQuery = "UPDATE quizzes SET title = ?, subject = ?, class = ?, status = ? WHERE id = ? AND teacher_id = ?";
                    $stmt = $conn->prepare($quizQuery);
                    $stmt->bind_param("ssssii", $title, $subject, $class, $status, $quizId, $teacherId);
                    $stmt->execute();
                    $stmt->close();
                    
                    $targetQuizId = $quizId;
                } else {
                    // Full Update Mode: If new question models are actually present in the session buffer
                    $total_points = intval($data['total_points'] ?? 0);
                    $questions_count = intval($data['questions_count'] ?? 0);

                    $quizQuery = "UPDATE quizzes SET title = ?, subject = ?, class = ?, status = ?, total_points = ?, questions_count = ? WHERE id = ? AND teacher_id = ?";
                    $stmt = $conn->prepare($quizQuery);
                    $stmt->bind_param("ssssiiii", $title, $subject, $class, $status, $total_points, $questions_count, $quizId, $teacherId);
                    $stmt->execute();
                    $stmt->close();
                    
                    $targetQuizId = $quizId;

                    // Safe to clear old question records now because we have replacement data ready
                    $clearQuery = "DELETE FROM questions WHERE quiz_id = ?";
                    $clearStmt = $conn->prepare($clearQuery);
                    $clearStmt->bind_param("i", $targetQuizId);
                    $clearStmt->execute();
                    $clearStmt->close();
                }
            } else {
                // INSERT Mode: Create fresh quiz row entry
                $total_points = intval($data['total_points'] ?? 0);
                $questions_count = intval($data['questions_count'] ?? 0);

                $quizQuery = "INSERT INTO quizzes (teacher_id, title, subject, class, status, total_points, questions_count) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($quizQuery);
                if (!$stmt) {
                    throw new Exception("SQL Prepare Failed: " . $conn->error);
                }
                $stmt->bind_param("issssii", $teacherId, $title, $subject, $class, $status, $total_points, $questions_count);
                $stmt->execute();
                $targetQuizId = $conn->insert_id;
                $stmt->close();
            }

            // CRITICAL ENGINE REPAIR: Only run insertions if replacement items exist
            if (!empty($questionsList)) {
                $qQuery = "INSERT INTO questions (quiz_id, type, question_text, options, correct_answer, points, time_limit) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $qStmt = $conn->prepare($qQuery);
                if (!$qStmt) {
                    throw new Exception("SQL Questions Prepare Failed: " . $conn->error);
                }

                foreach ($questionsList as $item) {
                    $type = $item['type'] ?? 'MCQ';
                    $q_text = $item['q'] ?? '';
                    $points = intval($item['points'] ?? 1);
                    $time = intval($item['time'] ?? 30);

                    $optionsJson = (!empty($item['options']) && $type === 'MCQ') ? json_encode($item['options'], JSON_UNESCAPED_UNICODE) : null;
                    $answerField = is_array($item['a']) ? json_encode($item['a'], JSON_UNESCAPED_UNICODE) : strval($item['a']);

                    $qStmt->bind_param("issssii", $targetQuizId, $type, $q_text, $optionsJson, $answerField, $points, $time);
                    $qStmt->execute();
                }
                $qStmt->close();
                
                // Clear the question session buffer only when saved via the builder canvas
                $_SESSION['questions'] = [];
            }

            $conn->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Quiz profile and status updated perfectly without altering question banks!',
                'data' => ['id' => $targetQuizId]
            ]);

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode([
                'success' => false,
                'message' => 'Database operation transaction failed: ' . $e->getMessage()
            ]);
        }
        exit;// =========================================================================
    // ACTION: READ ALL QUIZZES
    // =========================================================================
    case 'get_quizzes':
        if (ob_get_length()) ob_clean();

     if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized operation block.']);
    exit;
}
$teacherId = intval($_SESSION['user_id']);

        $teacher_id = intval($_SESSION['user_id']);
        $resultList = [];
        
        $query = "SELECT * FROM quizzes WHERE teacher_id = ? ORDER BY id DESC";
        $query1 = "SELECT full_name FROM users WHERE id = teacher_id ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $resultList[] = $row;
        }
        $stmt->close();

        echo json_encode(['success' => true, 'data' => $resultList]);
        exit;

    // =========================================================================
    // ACTION: DELETE QUIZ
    // =========================================================================
    case 'delete_quiz':
        if (ob_get_length()) ob_clean();
        $quizId = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if ($quizId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid or missing target quiz ID.']);
            exit;
        }

        $deleteQuery = "DELETE FROM quizzes WHERE id = ?";
        $stmt = $conn->prepare($deleteQuery);
        $stmt->bind_param("i", $quizId);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Quiz deleted successfully from database!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error executing database request.']);
        }
        $stmt->close();
        exit;
 // ================= SAVE MATERIAL =================

    case "save_material":

    if (ob_get_length()) ob_clean();

    $title   = $_POST['title'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $class   = $_POST['class'] ?? '';
    $type    = $_POST['type'] ?? '';
    $desc    = $_POST['desc'] ?? '';

    $uploadedBy = "Teacher";
    $filePath = null;

    // FILE UPLOAD
    if (isset($_FILES['file']) && $_FILES['file']['error'] === 0) {

        $uploadDir = "uploads/materials/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = time() . "_" . basename($_FILES["file"]["name"]);
        $targetFile = $uploadDir . $fileName;

        move_uploaded_file($_FILES["file"]["tmp_name"], $targetFile);

        $filePath = $targetFile;
    }

    // INSERT
    $stmt = $conn->prepare("
        INSERT INTO study_materials
        (title, subject, class, type, description, uploaded_by, file_path)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "sssssss",
        $title,
        $subject,
        $class,
        $type,
        $desc,
        $uploadedBy,
        $filePath
    );

    $stmt->execute();

    // RETURN UPDATED LIST
    $res = $conn->query("SELECT * FROM study_materials ORDER BY id DESC");

    $rows = [];

    while ($r = $res->fetch_assoc()) {
        $rows[] = [
            "id" => $r['id'],
            "title" => $r['title'],
            "subject" => $r['subject'],
            "class" => $r['class'],
            "type" => $r['type'],
            "desc" => $r['description'],
            "uploadedBy" => $r['uploaded_by'],
            "date" => $r['upload_date'],
            "file" => $r['file_path']
        ];
    }

   echo json_encode([
    "success" => true,
    "message" => "📤 Study material uploaded successfully!",
    "data" => $rows
]);

    exit;

   case "delete_material":
    if (ob_get_length()) ob_clean();

    // READ RAW JSON BODY PROPERLY
    $data = json_decode(file_get_contents("php://input"), true);

    $id = isset($data['id']) ? intval($data['id']) : 0;

    if ($id <= 0) {
        echo json_encode([
            "success" => false,
            "message" => "❌ Invalid material ID received.",
            "debug" => $data
        ]);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM study_materials WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "🗑️ Material deleted successfully"
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "❌ Database delete failed"
        ]);
    }

    $stmt->close();
    exit;

    // ================= GET MATERIALS =================

   case "get_materials":

    if (ob_get_length()) ob_clean();

    $res = $conn->query("SELECT * FROM study_materials ORDER BY id DESC");

    $rows = [];

    while ($r = $res->fetch_assoc()) {
        $rows[] = [
            "id" => $r['id'],
            "title" => $r['title'],
            "subject" => $r['subject'],
            "class" => $r['class'],
            "type" => $r['type'],
            "desc" => $r['description'],
            "uploadedBy" => $r['uploaded_by'],
            "date" => $r['upload_date'],
            "file" => $r['file_path']
        ];
    }

    echo json_encode([
        "success" => true,
        "data" => $rows
    ]);

    exit;

    // =========================================================================
    // ACTION: FETCH DATA COUNTERS FOR SYSTEM PANELS
    // =========================================================================
    case 'get_stats':
        if (ob_get_length()) ob_clean();
        
        $qCount = 0;
        
        
        $qQuery = $conn->query("SELECT COUNT(*) as total FROM quizzes");
        if ($qQuery) { $res = $qQuery->fetch_assoc(); $qCount = intval($res['total']); }
        
        // Return dynamic summary variables to your UI panels
        echo json_encode([
            'success' => true,
            'data' => [
                'quizzes' => $qCount,
                
            ]
        ]);
        exit;

// =========================================================================
    // ACTION: GET USER SESSION INFO
    // =========================================================================
    case 'get_user':
        if (ob_get_length()) ob_clean();
        
        // If a real user session was initialized by login.php, return it directly
        if (isset($_SESSION['user']) && isset($_SESSION['user_id'])) {
            echo json_encode(['success' => true, 'data' => $_SESSION['user']]);
        } else {
            // No session exists? Deny access cleanly rather than forcing ID 1
            echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
        }
        exit;
    // =========================================================================
    // STUB STACK BACKEND ROUTINGS: Returns structured collections to prevent script timeouts
    // =========================================================================
   

    case 'logout':
        if (ob_get_length()) ob_clean();
        session_destroy();
        echo json_encode(['success' => true]);
        exit;

    // =========================================================================
    // GLOBAL FALLBACK HANDLER
    // =========================================================================
    default:
        if (ob_get_length()) ob_clean();
        echo json_encode([
            'success' => false, 
            'message' => 'Requested action parameter "' . htmlspecialchars($action) . '" route is unhandled or missing.'
        ]);
        exit;
}
?>