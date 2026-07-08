<?php
/* =========================================================================
   1. INITIALIZATION, DEPENDENCIES, AND CORE SESSION SETUP
   ========================================================================= */
// Include database connection settings from an external configuration file
include "db.php";
// Start or resume a PHP session to persist quiz data across page reloads
session_start();

// Initialize blank default values for a fresh quiz instance to prevent undefined variable notices
$editMode = false;
$quizData = [
    'id' => '',
    'title' => '',
    'subject' => '',
    'class' => '',
    'status' => 'Active'
];
$savedQuestions = [];

/* =========================================================================
   2. URL ACTION ROUTING & STATE CONTROLLERS (GET REQUESTS)
   ========================================================================= */

// ACTION: "New Quiz" -> Triggered when a teacher explicitly clicks to build a fresh quiz canvas
if (isset($_GET['action']) && $_GET['action'] === 'new_quiz') {
    // Clear out any questions, answers, or mock results currently sitting in the session
    $_SESSION['questions'] = [];
    unset($_SESSION['quiz_answers']);
    unset($_SESSION['quiz_results']);
} 
// ACTION: "Edit Quiz" -> Triggered when loading an existing quiz from the dashboard to edit it
elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit'])) {
    $quizId = intval($_GET['edit']); // Force the ID to an integer for safety
    if ($quizId > 0) {
        // Prepare and execute an SQL statement to fetch core quiz details (title, subject, etc.)
        $quizStmt = $conn->prepare("SELECT * FROM quizzes WHERE id = ?");
        $quizStmt->bind_param("i", $quizId);
        $quizStmt->execute();
        $quizResult = $quizStmt->get_result();
        
        if ($quizResult->num_rows > 0) {
            $editMode = true; // Flip flag to indicate the entire quiz is in edit mode
            $quizData = $quizResult->fetch_assoc(); // Store quiz metadata
            
            // Prepare an SQL statement to grab all questions linked to this specific quiz ID
            $qStmt = $conn->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id ASC");
            if ($qStmt) {
                $qStmt->bind_param("i", $quizId);
                $qStmt->execute();
                $qResult = $qStmt->get_result();
                
                $_SESSION['questions'] = []; // Clear current session array to make room for database records
                // Loop through database records and reconstruct them into the standardized session structure
                while ($row = $qResult->fetch_assoc()) {
                    $_SESSION['questions'][] = [
                        'type' => $row['type'] ?? 'MCQ',
                        'q' => $row['question_text'] ?? $row['q'] ?? '',
                        // Decode JSON strings back into readable PHP arrays for correct answers and options
                        'a' => json_decode($row['correct_answer'] ?? '', true) ?? $row['a'] ?? '',
                        'points' => $row['points'] ?? 1,
                        'time' => $row['time_limit'] ?? $row['time'] ?? 30,
                        'options' => json_decode($row['options'] ?? '', true) ?? []
                    ];
                }
            }
        }
    }
}

// Fallback safety barrier: Ensure the questions array exists in the session so array methods don't crash
if (!isset($_SESSION['questions'])) {
    $_SESSION['questions'] = [];
}

/* =========================================================================
   3. BACKGROUND ASYNC HANDLERS (POST REQUESTS / AJAX)
   ========================================================================= */

// HANDLER: Drag-and-Drop Order Persistence Engine
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'updateOrder') {
    // Read the incoming raw JSON string payload sent from JavaScript fetch()
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (isset($data['order']) && is_array($data['order'])) {
        $newQuestionsArray = [];
        // Map over the new sequential order index sequence sent by the frontend
        foreach ($data['order'] as $oldIndex) {
            $sanitizedIndex = intval($oldIndex); // SANITIZATION: Force index to be a safe integer
            if (isset($_SESSION['questions'][$sanitizedIndex])) {
                // Reconstruct a temporary sorted array using the old indices
                $newQuestionsArray[] = $_SESSION['questions'][$sanitizedIndex];
            }
        }
        // Overwrite the session with the newly ordered question list
        if (!empty($newQuestionsArray)) {
            $_SESSION['questions'] = $newQuestionsArray;
        }
        // Return a clean asynchronous JSON success confirmation flag back to the frontend browser
        echo json_encode(['status' => 'success']);
        exit; // Terminate script immediately to avoid rendering HTML layout strings into an API response
    }
}

/* =========================================================================
   4. UTILITY UTILITIES & BACKEND ENGINE ALGORITHMS
   ========================================================================= */

/**
 * Text Mining Utility: Extracts clean, distinct analytical evaluation keywords 
 * from student text entries by stripping formatting rules and common filler words.
 */
function cleanKeywords($inputData) {
    // List of common words to ignore (stop-words) when grading free-text responses
    $stopWords = [
        "is","are","was","were","a","an","the","for","of","to",
        "and","or","in","on","used","using","that","this","it","as","by","with"
    ];

    if (is_array($inputData)) {
        // SANITIZATION: Strip out symbols and punctuation from each element within an incoming array structure
        $inputData = array_map(function($val) {
            return preg_replace('/[^a-zA-Z0-9\s]/', '', (string)$val);
        }, $inputData);
        $text = implode(' ', $inputData);
    } else {
        $text = (string)$inputData;
    }

    $text = strtolower($text); // Normalize casing to prevent matching failures
    $text = preg_replace('/[^a-z0-9\s]/', ' ', $text); // Convert all leftover odd characters to spaces
    $words = array_filter(explode(' ', $text)); // Break block of text into distinct word tokens
    $keywords = [];

    // Filter out duplicate tokens and skip words that exist in the stop-words array
    foreach ($words as $w) {
        if (!in_array($w, $stopWords)) {
            $keywords[] = $w;
        }
    }

    return array_values(array_unique($keywords)); // Return clean, deduplicated index keys
}

/* =========================================================================
   5. CRUD SUB-ACTIONS (DELETE QUESTION / LOAD QUESTION STATE)
   ========================================================================= */

// SUB-ACTION: Drop single item instance from active forge bank cache
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if (isset($_SESSION['questions'][$id])) {
        unset($_SESSION['questions'][$id]); // Erase target item array allocation slot
        $_SESSION['questions'] = array_values($_SESSION['questions']); // Re-index array indices so they remain sequential (0, 1, 2...)
    }
    // Retain quiz context if editing an entire layout
    $redirectUrl = $_SERVER['PHP_SELF'];
    if (isset($_GET['quiz_id'])) {
        $redirectUrl .= "?edit=" . intval($_GET['quiz_id']);
    }
    header("Location: " . $redirectUrl);
    exit;
}

// Flags to determine if a specific single question is currently pulled up inside the construction form
$isEditingQuestion = false; 
$editId = null;
$editQuestion = null;

// SUB-ACTION: Catch targeted item configuration variables and pull them back up into form builders
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if (isset($_SESSION['questions'][$id])) {
        $isEditingQuestion = true; 
        $editId = $id;
        $editQuestion = $_SESSION['questions'][$id]; // Extract selected item criteria context payloads
    }
}

/* =========================================================================
   6. MAIN FORM CONTROLLER SUBMISSIONS & DATABASE PERSISTENCE
   ========================================================================= */
$quizSavedNotification = false;
$savedQuizName = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ROUTE A: Handle asynchronous AJAX Final Save requests (Only if action is explicitly targeted)
    if (isset($_GET['action']) && $_GET['action'] === 'save_quiz') {
        $rawInput = file_get_contents("php://input");
        $data = json_decode($rawInput, true);
        
        if (!empty($data)) {
            if (!isset($_SESSION['user_id'])) {
                echo json_encode(["success" => false, "message" => "Error: No teacher session found."]);
                exit;
            }
            
            $logged_in_teacher_id = intval($_SESSION['user_id']); 
            $title = filter_var($data['title'] ?? 'Untitled Quiz', FILTER_UNSAFE_RAW);
            $subject = filter_var($data['subject'] ?? 'General', FILTER_UNSAFE_RAW);
            $class = filter_var($data['class'] ?? 'General', FILTER_UNSAFE_RAW);
            $status = filter_var($data['status'] ?? 'Active', FILTER_UNSAFE_RAW);
            $total_points = intval($data['total_points'] ?? 0);
            $questions_count = intval($data['questions_count'] ?? 0);

            if (isset($data['id']) && intval($data['id']) > 0) {
                $qId = intval($data['id']);
                $query = "UPDATE quizzes SET title = ?, subject = ?, total_points = ?, questions_count = ? WHERE id = ? AND teacher_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssiiii", $title, $subject, $total_points, $questions_count, $qId, $logged_in_teacher_id);
            } else {
                $query = "INSERT INTO quizzes (teacher_id, title, subject, class, status, total_points, questions_count, created_at) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("issssii", $logged_in_teacher_id, $title, $subject, $class, $status, $total_points, $questions_count);
            }

            if ($stmt->execute()) {
                $new_id = $conn->insert_id ?: intval($data['id']);
                echo json_encode([
                    "success" => true, 
                    "message" => "Quiz saved successfully!",
                    "data" => ["id" => $new_id]
                ]);
            } else {
                echo json_encode(["success" => false, "message" => "Database save failed."]);
            }
            exit;
        }
    } 
    
    // ROUTE B: Handle traditional HTTP Form submissions ("Add Question" / "Save Changes")
    elseif (isset($_POST['formAction']) && $_POST['formAction'] === 'saveQuestion') {
        $type = $_POST['questionType'] ?? 'MCQ';
        $qText = $_POST['questionText'] ?? '';
        $points = intval($_POST['questionPoints'] ?? 1);
        $time = intval($_POST['questionTime'] ?? 30);
        $options = [];
        $answer = '';

        if ($type === 'MCQ') {
            for ($i = 1; $i <= 4; $i++) {
                if (isset($_POST["option$i"]) && trim($_POST["option$i"]) !== '') {
                    $options[] = $_POST["option$i"];
                }
            }
            $correctRadioIdx = intval($_POST['correctOption'] ?? 1);
            $answer = $options[$correctRadioIdx - 1] ?? '';
        } elseif ($type === 'TF') {
            $answer = $_POST['correctTF'] ?? 'True';
        } elseif ($type === 'Blank') {
            $answer = $_POST['blankAnswer'] ?? '';
        } elseif ($type === 'Text') {
            $rawAnswers = $_POST['answerText'] ?? [];
            $answer = is_array($rawAnswers) ? array_map('strval', $rawAnswers) : [];
        }

        $newQuestion = [
            'type' => $type,
            'q' => $qText,
            'a' => $answer,
            'points' => $points,
            'time' => $time,
            'options' => $options
        ];

        // FIXED: Explicitly handle both creation mode and numerical edit states safely
        if (isset($_POST['editId']) && is_numeric($_POST['editId']) && $_POST['editId'] !== '') {
            $_SESSION['questions'][intval($_POST['editId'])] = $newQuestion;
        } else {
            $_SESSION['questions'][] = $newQuestion;
        }

        $redirectParam = "";
        if (isset($_GET['edit'])) {
            $redirectParam = "?edit=" . intval($_GET['edit']);
        }
        
        header("Location: " . $_SERVER['PHP_SELF'] . $redirectParam);
        exit;
    }
}


/* =========================================================================
   7. RUNTIME VALUE COMPUTATIONS & DISPLAY ROUTING
   ========================================================================= */
// Calculate total collective score values by tracking point balances across all setup instances
$totalScore = 0;
foreach ($_SESSION['questions'] as $q) {
    $totalScore += (int)($q['points'] ?? 0);
}

// Route UI panels based on current navigation query values (builder canvas or live mockup wizard screens)
$currentView = $_GET['view'] ?? 'builder';
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quiz Forge</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* ================= CSS VARIABLES DICTIONARY ================= */
body {
    --primary: #4C5E3D;               
    --accent: #A36344;                
    --accent-hover: #8B5135;          
    --primary-hover: #3D4C31;         
    --bg-main: #F4ECE1;               
    --card-bg: #FFFFFF;               
    --header-bg: #5B6E4A;             
    --text-main: #2A2421;             
    --text-muted: #82756A;            
    --border-color: #E6DCCE;          
    --success-bg: #E4ECD7;            
    --success-text: #415530;          
    --warning-bg: #F5EADA;            
    --warning-text: #966938;          
    --danger: #923C32;
    --danger-bg: #F9EBEA;
    --danger-hover: #732E26;
}

body[data-theme="light"] {
    --card-bg: #FFFFFF;
    --text-main: #2B2622;
    --text-muted: #7A6F66;
    --border-color: #E6DDD4;
    --header-bg: #556B43; 
    --primary: #556B43;
    --accent: #9C5A3C;
    --accent-hover: #854B30;
    --bg-main: #F4ECE1;
}

body[data-theme="dark"] {
    --card-bg: #1B2433;
    --text-main: #F3F6FA;
    --text-muted: #A7B4C6;
    --border-color: #2E3A4D;
    --header-bg: #243447;
    --primary: #465E7A;
    --accent: #D08770;
    --accent-hover: #BF7A62;
    --bg-main: #0F141C;
    --success-bg: #1E2D24;
    --success-text: #81C784;
    --warning-bg: #2C251B;
    --warning-text: #FFB74D;
    --danger: #E57373;
    --danger-bg: #2C1C1C;
}

body[data-theme="blue"] {
    --card-bg: #0F172A;
    --text-main: #E0F7FF;
    --text-muted: #7DD3FC;
    --border-color: #164E63;
    --header-bg: #0088CC;
    --primary: #00BFFF;
    --accent: #00F5FF;
    --accent-hover: #00D2DD;
    --bg-main: #060B14;
    --success-bg: #06242E;
    --success-text: #38BDF8;
    --warning-bg: #1A2426;
    --warning-text: #38BDF8;
    --danger: #F43F5E;
    --danger-bg: #2A1215;
}

body[data-text="small"] { font-size: 13px; }
body[data-text="medium"] { font-size: 16px; }
body[data-text="large"] { font-size: 19px; }
body[data-text="small"] * { font-size: 90% !important; }
body[data-text="large"] * { font-size: 105% !important; }

body[data-text="small"] .logo h2 { font-size: 24px !important; }
body[data-text="medium"] .logo h2 { font-size: 28px !important; }
body[data-text="large"] .logo h2 { font-size: 32px !important; }
body[data-text="large"] .header-section h1 { font-size: 52px !important; }
body[data-text="small"] .header-section h1 { font-size: 38px !important; }

body[data-text="large"] .profile-icon i,
body[data-text="large"] .icon-btn i { transform: scale(1.15); }

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Poppins', sans-serif;
    background: var(--bg-main);
    color: var(--text-main);
    min-height: 100vh;
    padding: 30px 20px;
    transition: background 0.25s ease, color 0.25s ease;
}

.container { max-width: 1200px; margin: 110px auto 0 auto; }

.header-nav {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 40px;
    background: var(--header-bg); 
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
    z-index: 1000;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    transition: background 0.25s ease;
}

.logo { display: flex; align-items: center; gap: 12px; }
.logo-box {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
}
.logo-box img { width: 24px; height: 24px; border-radius: 50%; }
.logo h2 { font-size: 24px; color: #ffffff; }
.right-nav-group { display: flex; align-items: center; gap: 20px; }
nav { display: flex; align-items: center; gap: 20px; }
nav a {
    text-decoration: none;
    color: rgba(255, 255, 255, 0.8); 
    font-weight: 500;
    font-size: 15px;
    transition: 0.3s;
    display: flex;
    align-items: center;
}
nav a:hover { color: #ffffff; }

.icon-btn:hover { background: #ffffff; color: var(--primary); }

.dropdown-menu {
    position: absolute;
    right: 0;
    top: 45px;
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    min-width: 170px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    display: none;
    flex-direction: column;
    overflow: hidden;
    z-index: 1010;
}
.dropdown-menu.show { display: flex; }
.dropdown-menu button {
    width: 100%;
    padding: 10px 16px;
    background: transparent;
    border: none;
    text-align: left;
    color: var(--text-main);
    font-family: inherit;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: background 0.2s ease;
}
.dropdown-menu button:hover { background: var(--bg-main); }

@media(max-width: 600px) { nav a { display: none; } }

.grid {
    display: grid;
    grid-template-columns: 1.1fr 0.9fr;
    gap: 30px;
    align-items: stretch; 
}
.single-layout { max-width: 800px; margin: 0 auto; }

.card {
    background: var(--card-bg);
    padding: 30px;
    border-radius: 16px;
    border: 1px solid var(--border-color);
    box-shadow: 0 10px 25px rgba(42, 36, 33, 0.02);
    display: flex;
    flex-direction: column;
    transition: background 0.25s ease, border-color 0.25s ease;
}

.form-static-body { flex: 1; margin-bottom: 15px; }
.card-header-wrapper {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--bg-main);
}
.card h2 { font-size: 20px; font-weight: 600; color: var(--text-main); }

.quiz-score-badge {
    font-size: 13px;
    font-weight: 600;
    background: var(--success-bg);
    color: var(--success-text);
    padding: 6px 14px;
    border-radius: 50px;
}

.form-group { margin-bottom: 20px; }
label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 8px; color: var(--text-main); }

input[type="text"], select, textarea, input[type="number"] {
    width: 100%;
    padding: 12px 16px;
    border: 1.5px solid var(--border-color);
    border-radius: 10px;
    font-family: inherit;
    font-size: 14px;
    color: var(--text-main);
    background-color: var(--card-bg);
    transition: all 0.2s ease;
    outline: none;
}
input:focus, select:focus, textarea:focus, input[type="number"]:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(76, 94, 61, 0.15);
}
textarea { resize: vertical; min-height: 100px; }

.mcq-container { margin-top: 10px; }
.mcq-modern {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 4px 14px;
    border: 1.5px solid var(--border-color);
    border-radius: 10px;
    background: var(--card-bg);
    margin-bottom: 12px;
    transition: all 0.2s ease;
}
.mcq-modern:focus-within {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(76, 94, 61, 0.15);
}
.mcq-modern input[type="radio"] { accent-color: var(--accent); width: 18px; height: 18px; cursor: pointer; }
.mcq-modern input[type="text"] { border: none; padding: 10px 0; background: transparent; }
.mcq-modern input[type="text"]:focus { box-shadow: none; }

#tagContainer { margin-top: 12px; display: flex; flex-wrap: wrap; gap: 6px; }
.tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 20px;
    background: var(--bg-main);
    color: var(--text-main);
    font-size: 13px;
    font-weight: 500;
    border: 1px solid var(--border-color);
}
.tag button { background: none; border: none; color: var(--accent); cursor: pointer; font-size: 14px; font-weight: bold; line-height: 1; }

button[type="submit"], .btn-primary {
    display: block;
    text-align: center;
    width: 100%;
    padding: 14px;
    background: var(--accent);
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: background 0.2s ease;
    margin-top: auto; 
}
button[type="submit"]:hover, .btn-primary:hover { background-color: var(--accent-hover); }

.btn-generate { background-color: var(--primary); margin-top: auto; }
.btn-generate:hover { background-color: var(--primary-hover); }

.btn-cancel {
    display: block;
    text-align: center;
    width: 100%;
    padding: 12px;
    background-color: var(--bg-main);
    color: var(--text-muted);
    border-radius: 10px;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    margin-top: 10px;
    transition: background 0.2s ease;
}
.btn-cancel:hover { background-color: var(--border-color); color: var(--text-main); }

.bank-scroll-area { flex: 1; overflow-y: auto; padding-right: 4px; margin-bottom: 15px; max-height: 680px; }
.bank-item { border: 1px solid var(--border-color); border-radius: 12px; margin-bottom: 12px; background: var(--bg-main); overflow: hidden; transition: all 0.2s ease; cursor: grab; }
.bank-item:active { cursor: grabbing; }
.bank-item.dragging { opacity: 0.4; border: 2px dashed var(--accent); background: var(--card-bg); }

.bank-item-summary { padding: 14px 18px; background: rgba(255, 255, 255, 0.1); user-select: none; transition: background 0.2s ease; display: flex; flex-direction: column; }
.bank-item-summary:hover { background: rgba(255, 255, 255, 0.2); }
.bank-item-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; width: 100%; }

.type-badge { color: var(--success-text); font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; background: var(--success-bg); padding: 3px 8px; border-radius: 6px; }
.bank-question-wrapper { display: flex; align-items: center; justify-content: space-between; gap: 12px; width: 100%; }
.bank-question { font-size: 14px; font-weight: 500; line-height: 1.4; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; }
.points-badge { display: inline-flex; align-items: center; font-size: 11px; font-weight: 600; background: var(--border-color); color: var(--text-main); padding: 2px 8px; border-radius: 999px; white-space: nowrap; }

.bank-item-details { max-height: 0; overflow: hidden; padding: 0 18px; background: var(--card-bg); border-top: 1px solid transparent; transition: max-height 0.25s cubic-bezier(0, 1, 0, 1), padding 0.2s ease; }
.bank-item.expanded .bank-item-details { max-height: 1000px; padding: 16px 18px; border-top-color: var(--border-color); transition: max-height 0.3s ease-in-out, padding 0.2s ease; }
.bank-item.expanded .bank-item-summary { background: var(--border-color); }

.bank-options { list-style: none; margin-bottom: 12px; padding-left: 2px; }
.bank-options li { font-size: 13px; color: var(--text-muted); padding: 4px 0; display: flex; align-items: center; gap: 8px; }
.bank-options li::before { content: "•"; color: var(--primary); font-size: 16px; }
.bank-answer { font-size: 13px; padding-top: 10px; border-top: 1px dashed var(--border-color); }

.item-actions { display: flex; gap: 6px; }
.action-icon-btn { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 6px; background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-muted); transition: all 0.2s ease; cursor: pointer; }
.action-icon-btn:hover { color: var(--primary); border-color: var(--primary); }
.action-icon-btn.delete-btn:hover { color: var(--danger); border-color: var(--danger); }
.action-icon-btn svg { width: 12px; height: 12px; fill: currentColor; }

.empty-state { text-align: center; color: var(--text-muted); padding: 40px 0; font-size: 14px; margin: auto 0; }
.exam-option-label { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border: 1.5px solid var(--border-color); border-radius: 10px; margin-bottom: 10px; cursor: pointer; font-size: 14px; transition: all 0.15s ease; }
.exam-option-label:hover { background: var(--bg-main); border-color: var(--primary); }
.exam-option-label input[type="radio"] { accent-color: var(--accent); width: 18px; height: 18px; }

.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 9999; opacity: 0; pointer-events: none; transition: opacity 0.25s ease; }
.modal-overlay.active { opacity: 1; pointer-events: auto; }
.modal-card { background: var(--card-bg); padding: 30px; border-radius: 16px; max-width: 480px; width: 90%; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); transform: scale(0.95); transition: transform 0.25s ease; border: 1px solid var(--border-color); }
.modal-overlay.active .modal-card { transform: scale(1); }

.modal-icon { width: 56px; height: 56px; background: var(--danger-bg); color: var(--danger); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px auto; }
.modal-icon svg { width: 28px; height: 28px; fill: currentColor; }
.modal-card h3 { font-size: 18px; font-weight: 600; color: var(--text-main); margin-bottom: 10px; }
.modal-card p { font-size: 14px; color: var(--text-muted); margin-bottom: 24px; line-height: 1.5; }
.modal-actions { display: flex; gap: 12px; }
.modal-btn { flex: 1; padding: 12px; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; border: none; transition: background 0.2s ease; }
.modal-btn-cancel { background: var(--bg-main); color: var(--text-main); }
.modal-btn-cancel:hover { background: var(--border-color); }
.modal-btn-confirm { background: var(--danger); color: white; }
.modal-btn-confirm:hover { background: var(--danger-hover); }

@media(max-width:950px){ .grid{ grid-template-columns: 1fr; } }

.header-section h1 { font-size: 48px; line-height: 1.1; color: var(--text-main); margin-bottom: 15px; font-weight: 700; }
.header-section h1 span { color: var(--accent); }
.header-section p { font-size: 15px; line-height: 1.6; color: var(--text-muted); max-width: 700px; margin-bottom: 30px; }

.drag-handle { display: flex; align-items: center; justify-content: center; color: var(--text-muted); margin-right: 8px; cursor: grab; }
.drag-handle svg { width: 16px; height: 16px; fill: currentColor; }

/* ===== EXAM SLIDE PREVIEW REVEAL FIX ===== */
.exam-slide { 
    display: none !important; 
}
.exam-slide.active-slide { 
    display: block !important; 
}
.wizard-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; gap: 12px; }
.btn-wizard { padding: 12px 24px !important; font-size: 14px !important; width: auto !important; }

.toast-notice { background-color: var(--primary); color: #FFFFFF; padding: 14px 24px; border-radius: 12px; position: fixed; bottom: 30px; right: 30px; box-shadow: 0 10px 20px rgba(0,0,0,0.15); z-index: 99999; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 10px; animation: slideUp 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
@keyframes slideUp { from { transform: translateY(50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

.naming-input-field { width: 100%; padding: 12px; border: 1.5px solid var(--border-color); border-radius: 10px; font-family: inherit; font-size: 14px; outline: none; margin-top: 5px; margin-bottom: 15px; box-sizing: border-box; background: var(--card-bg); color: var(--text-main); }
.naming-input-field:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(76, 94, 61, 0.15); }
</style>
<script>
    (function() {
        const theme = localStorage.getItem("theme") || "light";
        const text = localStorage.getItem("textSize") || "medium";
        document.documentElement.setAttribute("data-theme", theme);
    })();
</script>
</head>

<body data-theme="light" data-text="medium">

<?php if ($quizSavedNotification): ?>
    <div class="toast-notice" id="toastBox">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
        <span>Quiz layout "<b><?=htmlspecialchars($savedQuizName, ENT_QUOTES, 'UTF-8')?></b>" saved successfully to database pipeline!</span>
    </div>
    <script>setTimeout(() => { document.getElementById("toastBox").style.display='none'; }, 4000);</script>
<?php endif; ?>

<header class="header-nav">
    <div class="logo">
        <div class="logo-box">
            <img src="quizverse-logo.png" alt="QuizVerse Logo">
        </div>
        <h2 id="pageTitle">QuizVerse</h2>
    </div>

    <div class="right-nav-group">
        <nav>
           <a href="teacher.html">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
            </a>
           <a href="about-contact.php">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                    <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
            </a>
        </nav>

        <div class="dropdown" style="position: relative !important; display: inline-block !important;">
            <button id="themeBtn" type="button" class="simple-nav-icon" style="background: none !important; border: none !important; color: white !important; cursor: pointer !important; padding: 0 !important; display: inline-flex !important; align-items: center !important; transition: 0.3s !important;">
                <i class="fa-regular fa-moon" style="font-size: 20px !important; color: #F4ECE1 !important"></i>
            </button>
            <div class="dropdown-menu" id="themeMenu">
                <button type="button" onclick="setTheme('light')"><i class="fa-solid fa-sun"></i> Light</button>
                <button type="button" onclick="setTheme('dark')"><i class="fa-solid fa-moon"></i> Dark</button>
                <button type="button" onclick="setTheme('blue')"><i class="fa-solid fa-droplet"></i> Blue Fluorescent</button>
            </div>
        </div>

        <div class="dropdown" style="position: relative !important; display: inline-block !important;">
            <button id="textBtn" type="button" class="simple-nav-icon" style="background: none !important; border: none !important; color: white !important; cursor: pointer !important; padding: 0 !important; display: inline-flex !important; align-items: center !important; transition: 0.3s !important;">
                <i class="fa-solid fa-text-height" style="font-size: 19px !important; -webkit-text-stroke: 2px #F4ECE1 !important; color: transparent !important;fill:none; stroke:currentColor; stroke-width:2"></i>
            </button>
            <div class="dropdown-menu" id="textMenu">
                <button type="button" onclick="setTextSize('small')">A- Small</button>
                <button type="button" onclick="setTextSize('medium')">A Medium</button>
                <button type="button" onclick="setTextSize('large')">A+ Large</button>
            </div>
        </div>
    </div>
</header>

<div class="container">
    <div class="header-section">
        <?php if($currentView === 'exam'): ?>
            <h1>PREVIEW YOUR <span>QUIZ</span></h1>
            <p>Review and test your quiz</p>
        <?php else: ?>
            <h1>CREATE YOUR <span>QUIZ</span></h1>
            <p>Use the form below to add questions and build your quiz.</p>
        <?php endif; ?>
    </div>

    <?php if($currentView === 'builder'): ?>
    <div class="grid" max="10">
        <div class="card">
            <div class="card-header-wrapper">
                <h2><?=$isEditingQuestion ? 'Edit Question #'.intval($editId+1) : 'Create Question'?></h2>
            </div>
            
            <form method="POST" action="?<?= isset($_GET['edit']) ? 'edit=' . intval($_GET['edit']) : '' ?>">
                <input type="hidden" name="formAction" value="saveQuestion">
                <input type="hidden" name="editId" value="<?=htmlspecialchars($editId ?? '', ENT_QUOTES, 'UTF-8')?>">

                <div class="form-static-body">
                    <div class="form-group">
                        <label for="type">Question Type</label>
                        <select name="questionType" id="type">
                            <option value="MCQ" <?=($isEditingQuestion && $editQuestion['type'] === 'MCQ') ? 'selected' : ''?>>Multiple Choice (MCQ)</option>
                            <option value="TF" <?=($isEditingQuestion && $editQuestion['type'] === 'TF') ? 'selected' : ''?>>True / False</option>
                            <option value="Blank" <?=($isEditingQuestion && $editQuestion['type'] === 'Blank') ? 'selected' : ''?>>Fill in the Blanks</option>
                            <option value="Text" <?=($isEditingQuestion && $editQuestion['type'] === 'Text') ? 'selected' : ''?>>Short Text / Keywords</option>
                        </select>
                    </div>

                    <div class="form-group" ">
                        <label>Question Prompt</label>
                        <textarea name="questionText" id="questionPromptArea" placeholder="Enter question here..." required><?=htmlspecialchars($isEditingQuestion ? $editQuestion['q'] : '', ENT_QUOTES, 'UTF-8')?></textarea>
                    </div>

                    <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 0;">
                       <div class="form-group">
                            <label>Points Value</label>
                            <select name="questionPoints">
                                <?php for($i = 1; $i <= 10; $i++): 
                                    $isPointsSelected = false;
                                    
                                    // Verify we are editing a valid question with points assigned before selecting it
                                    if ($isEditingQuestion && !empty($editQuestion) && isset($editQuestion['points'])) {
                                        $isPointsSelected = ((int)$editQuestion['points'] === $i);
                                    }
                                ?>
                                <option value="<?=$i?>" <?=$isPointsSelected ? 'selected' : ''?>><?=$i?> <?=$i === 1 ? 'Point' : 'Points'?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Time Limit (Seconds)</label>
                            <input type="number" name="questionTime" min="5" max="600" placeholder="e.g. 30" value="<?=htmlspecialchars($isEditingQuestion ? ($editQuestion['time'] ?? '30') : '30', ENT_QUOTES, 'UTF-8')?>" required>
                        </div>
                    </div>

                    <div id="mcqBox" class="form-group" style="<?=($isEditingQuestion && $editQuestion['type'] !== 'MCQ') ? 'display:none;' : ''?>">
                        <label>Answer Options & Correct Target</label>
                        <div class="mcq-container">
                            <?php for($i = 1; $i <= 4; $i++): 
                                $val = '';
                                $isChecked = ($i === 1);
                                
                                if($isEditingQuestion && !empty($editQuestion) && $editQuestion['type'] === 'MCQ') {
                                    $optionsArray = isset($editQuestion['options']) ? $editQuestion['options'] : [];
                                    $val = isset($optionsArray[$i-1]) ? $optionsArray[$i-1] : '';
                                    $isChecked = (isset($optionsArray[$i-1]) && $optionsArray[$i-1] === $editQuestion['a']);
                                }
                            ?>
                            <div class="mcq-modern">
                                <input type="radio" name="correctOption" value="<?=$i?>" <?=$isChecked?'checked':''?> title="Mark as correct">
                                <input type="text" name="option<?=$i?>" placeholder="Option <?=$i?>" value="<?=htmlspecialchars($val, ENT_QUOTES, 'UTF-8')?>">
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div id="tfBox" class="form-group" style="<?=($isEditingQuestion && $editQuestion['type'] === 'TF') ? 'display:block;' : 'display:none;'?>">
                        <label>Select Correct Evaluation Target</label>
                        <div class="mcq-container">
                            <?php 
                                $isTrueChecked = true;
                                $isFalseChecked = false;
                                
                                if ($isEditingQuestion && !empty($editQuestion) && $editQuestion['type'] === 'TF') {
                                    $isTrueChecked = ($editQuestion['a'] === 'True');
                                    $isFalseChecked = ($editQuestion['a'] === 'False');
                                }
                            ?>
                            <label class="exam-option-label">
                                <input type="radio" name="correctTF" value="True" <?=$isTrueChecked ? 'checked' : ''?>>
                                <span>True</span>
                            </label>
                            <label class="exam-option-label">
                                <input type="radio" name="correctTF" value="False" <?=$isFalseChecked ? 'checked' : ''?>>
                                <span>False</span>
                            </label>
                        </div>
                    </div>

                    <div id="blankBox" class="form-group" style="<?=($isEditingQuestion && $editQuestion['type'] === 'Blank') ? 'display:block;' : 'display:none;'?>">
                        <label for="blankAnswer">Correct Phrase Target</label>
                        <?php 
                            $blankVal = '';
                            if ($isEditingQuestion && !empty($editQuestion) && $editQuestion['type'] === 'Blank') {
                                $blankVal = $editQuestion['a'];
                            }
                        ?>
                        <input type="text" name="blankAnswer" id="blankAnswer" placeholder="Enter the exact missing word/phrase" value="<?=htmlspecialchars($blankVal, ENT_QUOTES, 'UTF-8')?>">
                    </div>
                    
                    <div id="textBox" class="form-group" style="<?=($isEditingQuestion && $editQuestion['type'] === 'Text') ? 'display:block;' : 'display:none;'?>">
                        <label style="color: var(--text-main);" for="keywordInput">Accepted Evaluation Keywords</label>
                        <input type="text" id="keywordInput" placeholder="Type a word and press Enter">
                        <div id="tagContainer"></div>
                        <div id="hiddenKeywords"></div>
                    </div>
                </div>

                <button type="submit">
                    <?= $isEditingQuestion ? 'Save Changes' : 'Add Question' ?>
                </button>
                
                <?php if($isEditingQuestion): ?>
                    <a href="<?= $_SERVER['PHP_SELF'] . (isset($_GET['edit']) ? "?edit=" . intval($_GET['edit']) : "") ?>" class="btn-cancel">Cancel Edit</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card">
            <div class="card-header-wrapper" style="display: flex; justify-content: space-between; align-items: center; gap: 15px; ">
                <h2>Question Bank (<span id="bankCount"><?=count($_SESSION['questions'])?></span>)</h2>
                <div style="display: flex; align-items: center; gap: 15px;">
                    <?php if(!empty($_SESSION['questions'])): ?>
                        <span class="quiz-score-badge"max:10; style="cursor: pointer;" onclick="triggerNamingFlow('DOWNLOAD')" title="Export Quiz">🏆 Total: <b><?=intval($totalScore)?> Pts</b></span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if(empty($_SESSION['questions'])): ?>
                <div class="empty-state">
                    <p style="margin-bottom: 10px; font-size: 16px;">✨ The Question Bank is Empty</p>
                    <p style="font-size: 13px; font-weight: normal; color: var(--text-muted);">Add your first question using the builder panel to open the interactive bank feed.</p>
                </div>
            <?php else: ?>
                <div class="bank-scroll-area" id="dragContainerArea ">
                    <?php foreach($_SESSION['questions'] as $index => $q): ?>
                        <?php 
                           $parentQueryParam = $editMode ? "&quiz_id=" . intval($quizData['id']) : ""; 
                           $editQuestionLink = $editMode ? "?edit=" . intval($quizData['id']) . "&action=edit&id=" . intval($index) : "?action=edit&id=" . intval($index);
                        ?>
                        <div class="bank-item" draggable="true" data-original-index="<?=intval($index)?>" id="accordion-item-<?=intval($index)?>" style="<?=($editId === $index) ? 'border-color: var(--primary); background: var(--success-bg);' : ''?>">
                            
                            <div class="bank-item-summary">
                                <div class="bank-item-header">
                                    <div style="display:flex; align-items:center;">
                                        <div class="drag-handle">
                                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                                <path d="M11 18c0 1.1-.9 2-2 2s-2-.9-2-2 .9-2 2-2 2 .9 2 2zm-2-4c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm0-6c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm6 10c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2zm0-4c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2zm0-6c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2z"/>
                                            </svg>
                                        </div>
                                        <span class="type-badge">
                                            <?php 
                                                if($q['type'] === 'MCQ') echo 'MCQ';
                                                elseif($q['type'] === 'TF') echo 'True/False';
                                                elseif($q['type'] === 'Blank') echo 'Fill in Blank';
                                                else echo 'Short Text';
                                            ?>
                                        </span>
                                    </div>
                                    <div class="item-actions">
                                        <a href="<?= $editQuestionLink ?>" class="action-icon-btn edit-action-link" title="Edit Question"><svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg></a>
                                        <button type="button" class="action-icon-btn delete-btn" title="Delete Question" onclick="openDeleteModal(<?=intval($index)?>, '<?= $parentQueryParam ?>')"><svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg></button>
                                    </div>
                                </div>
                                <div class="bank-question-wrapper" onclick="toggleAccordion(this)">
                                    <div class="bank-question"><b>#<span class="row-num-label"><?=intval($index + 1)?></span>.</b> <span class="prompt-text-node"><?=htmlspecialchars($q['q'], ENT_QUOTES, 'UTF-8')?></span></div>
                                    <div style="display:flex; gap:6px; align-items:center;">
                                        <span class="points-badge" style="background:var(--warning-bg); color:var(--warning-text);"><?=htmlspecialchars($q['time'] ?? '30', ENT_QUOTES, 'UTF-8')?>s</span>
                                        <span class="points-badge"><?=intval($q['points'])?> Pts</span>
                                    </div>
                                </div>
                            </div>

                            <div class="bank-item-details max=10">
                                <p style="font-size: 13px; font-weight: 600; margin-bottom: 8px; color: var(--text-main);">Full Prompt text:</p>
                                <p style="font-size: 14px; color: var(--text-muted); margin-bottom: 12px; line-height: 1.5; white-space: normal; word-break: break-word;"><?=htmlspecialchars($q['q'], ENT_QUOTES, 'UTF-8')?></p>

                                <?php if($q['type'] == "MCQ"): ?>
                                    <ul class="bank-options">
                                        <?php foreach($q['options'] as $opt): ?>
                                            <li><?=htmlspecialchars($opt, ENT_QUOTES, 'UTF-8')?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <div class="bank-answer">
                                        🔑 <b>Correct Answer:</b> <span style="color: var(--success-text); font-weight: 500;"><?=htmlspecialchars($q['a'], ENT_QUOTES, 'UTF-8')?></span>
                                    </div>
                                <?php elseif($q['type'] == "TF"): ?>
                                    <div class="bank-answer" style="border:none; padding:0;">
                                        🔑 <b>Correct Status:</b> <span style="color: var(--success-text); font-weight: 500;"><?=htmlspecialchars($q['a'], ENT_QUOTES, 'UTF-8')?></span>
                                    </div>
                                <?php elseif($q['type'] == "Blank"): ?>
                                    <div class="bank-answer" style="border:none; padding:0;">
                                        🔑 <b>Expected Replacement String:</b> <span style="color: var(--success-text); font-weight: 600;"><?=htmlspecialchars($q['a'], ENT_QUOTES, 'UTF-8')?></span>
                                    </div>
                                <?php else: ?>
                                    <div class="bank-answer" style="border:none; padding:0;">
                                        <p style="margin-bottom: 6px; font-size:13px;">🎯 <b>Target Keywords:</b></p>
                                        <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                                            <?php foreach($q['a'] as $kw): ?>
                                                <span class="tag" style="background: var(--bg-main); color: var(--text-main); padding: 2px 10px; font-size: 11px;"><?=htmlspecialchars($kw, ENT_QUOTES, 'UTF-8')?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="btn-primary btn-generate" onclick="window.location.href='?view=exam<?= $editMode ? "&edit=" . intval($quizData['id']) : "" ?>'">Preview Quiz Layout</button>
            <?php endif; ?>
        </div>
    </div>

    <?php elseif($currentView === 'exam'): ?>
    <div class="single-layout">
        <div class="card">
            <div class="card-header-wrapper">
                <h2>Quiz Preview</h2>
                <button type="button" onclick="window.location.href='?<?= $editMode ? "edit=" . intval($quizData['id']) : "" ?>'" style="background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); font-size: 12px; font-weight: 600; padding: 6px 14px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    Exit Preview
                </button>
            </div>

            <div id="liveExamWizardForm">
               <div class="form-static-body">
                    <?php foreach($_SESSION['questions'] as $index => $q): ?>
                        <div class="exam-slide <?=$index === 0 ? 'active-slide' : ''?>" id="slide-<?=intval($index)?>">
                            <div class="bank-item" style="background: var(--card-bg); border-color: var(--border-color); margin-bottom: 12px; padding:22px; cursor:default;">
                                <div class="bank-question-wrapper" style="margin-bottom:16px;">
                                    <div class="bank-question" style="font-size:16px; font-weight:600; white-space: normal; overflow: visible;">Q<?=intval($index+1)?>. <?=htmlspecialchars($q['q'], ENT_QUOTES, 'UTF-8')?></div>
                                    <div style="display:flex; gap:6px;">
                                        <span class="points-badge" style="background: var(--bg-main); color: var(--text-muted);">⏱️ <?=htmlspecialchars($q['time'] ?? '30', ENT_QUOTES, 'UTF-8')?>s</span>
                                        <span class="points-badge" style="background: var(--primary); color: white;"><?=intval($q['points'])?> Pts</span>
                                    </div>
                                </div>

                                <?php if($q['type'] === 'MCQ'): ?>
                                    <div>
                                        <?php foreach($q['options'] as $option): ?>
                                            <label class="exam-option-label">
                                                <input type="radio" disabled>
                                                <span><?=htmlspecialchars($option, ENT_QUOTES, 'UTF-8')?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php elseif($q['type'] === 'TF'): ?>
                                    <div>
                                        <label class="exam-option-label">
                                            <input type="radio" disabled>
                                            <span>True</span>
                                        </label>
                                        <label class="exam-option-label">
                                            <input type="radio" disabled>
                                            <span>False</span>
                                        </label>
                                    </div>
                                <?php elseif($q['type'] === 'Blank'): ?>
                                    <div class="form-group" style="margin-bottom:0;">
                                        <input type="text" placeholder="Mock student type-in entry sandbox..." disabled>
                                    </div>
                                <?php else: ?>
                                    <div class="form-group" style="margin-bottom:0;">
                                        <textarea placeholder="Mock student full-text structural response textarea layout..." disabled></textarea>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="wizard-footer">
                    <button type="button" class="btn-primary btn-secondary btn-wizard" id="prevSlideBtn" onclick="navigateWizard(-1)">Previous</button>
                    <span id="wizardStepTracker" style="font-size:14px; font-weight:500; color:var(--text-muted);">Question 1 of <?=count($_SESSION['questions'])?></span>
                    <button type="button" class="btn-primary btn-wizard" id="nextSlideBtn" onclick="navigateWizard(1)">Next Question</button>
                </div>
            </div> 
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 20px; border-top: 1px solid var(--border-color); padding-top: 20px; position: relative; z-index: 999;">
                <button type="button" class="btn-primary" onclick="triggerNamingFlow('SAVE')" style="background-color: var(--primary); margin: 0; padding: 12px; font-size: 14px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                    Save Quiz
                </button>
                <button type="button" class="btn-primary" onclick="triggerNamingFlow('DOWNLOAD')" style="background-color: var(--accent); margin: 0; padding: 12px; font-size: 14px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    Download Quiz
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="modal-overlay" id="deleteModalOverlay">
    <div class="modal-card">
        <div class="modal-icon">
            <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
        </div>
        <h3>Delete Question?</h3>
        <p>Are you sure you want to drop this question from the forge? This action cannot be undone.</p>
        <div class="modal-actions">
            <button class="modal-btn modal-btn-cancel" onclick="closeDeleteModal()">Cancel</button>
            <button class="modal-btn modal-btn-confirm" id="confirmDeleteBtn">Delete</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="quizNamingModalOverlay">
    <div class="modal-card" style="max-width: 480px; text-align: left; display: flex; flex-direction: column; gap: 16px;">
        <div style="text-align: center; display: flex; flex-direction: column; gap: 4px;">
            <h3 style="font-size: 22px; font-weight: 600; margin: 0; color: var(--text-main);">Update Quiz Parameters</h3>
            <p style="font-size: 13px; color: var(--text-muted); line-height: 1.4; margin: 0;">Modify setup parameters or edit the question sheets for this evaluation.</p>
        </div>
        
        <div style="display: flex; flex-direction: column; gap: 6px;">
            <label style="font-weight: 500; font-size: 14px; color: var(--text-main); margin: 0;">Quiz Title <span style="color: #c94a29;">*</span></label>
            <input type="text" id="quizTitlePromptInput" class="naming-input-field" placeholder="e.g. History Quiz" style="margin: 0;" required>
        </div>
        
        <div style="display: flex; flex-direction: column; gap: 6px;">
            <label style="font-weight: 500; font-size: 14px; color: var(--text-main); margin: 0;">Subject</label>
            <input type="text" id="quizSubjectPromptInput" class="naming-input-field" placeholder="e.g. Computer Science" style="margin: 0;">
        </div>

        <div id="formatSelectionWrapper" style="display: flex; flex-direction: column; gap: 6px;">
            <label style="font-weight: 500; font-size: 14px; color: var(--text-main); margin: 0;">Export Format</label>
            <select id="quizDownloadFormatSelect" style="margin: 0;">
                <option value="html">Interactive Standalone HTML</option>
                <option value="docx">Microsoft Word (.docx Template)</option>
            </select>
        </div>
        
        <div class="modal-actions" style="margin-top: 10px; justify-content: center; gap: 14px;">
            <button class="modal-btn modal-btn-cancel" onclick="closeNamingModal()" style="padding: 12px 28px; background: #f2ebd9; color: var(--text-main); font-weight: 600; border-radius: 10px; font-size: 14px;">Cancel</button>
            <button class="modal-btn modal-btn-confirm" id="confirmNamingActionBtn" style="padding: 12px 28px; background: var(--accent); color: white; font-weight: 600; border-radius: 10px; font-size: 14px; border: none;">Save Changes</button>
        </div>
    </div>
</div>

<script>
// SANITIZATION: Used hexadecimal parameter flags during json representation mappings to render tags inert
let keywords = <?= ($isEditingQuestion && !empty($editQuestion) && isset($editQuestion['type']) && $editQuestion['type'] === 'Text') ? json_encode($editQuestion['a'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) : '[]' ?>;
let rawQuestionsData = <?=json_encode($_SESSION['questions'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS)?>;
let activeEditingQuizId = <?= ($editMode && isset($quizData['id'])) ? intval($quizData['id']) : 'null' ?>;
let currentActiveFlowTarget = 'SAVE';

const typeSelect = document.getElementById("type");
const input = document.getElementById("keywordInput");
const container = document.getElementById("tagContainer");
const hidden = document.getElementById("hiddenKeywords");
const deleteModalOverlay = document.getElementById("deleteModalOverlay");
const confirmDeleteBtn = document.getElementById("confirmDeleteBtn");
const questionPromptArea = document.getElementById("questionPromptArea");
const dragContainerArea = document.getElementById("dragContainerArea");
const quizNamingModalOverlay = document.getElementById("quizNamingModalOverlay");
const quizTitlePromptInput = document.getElementById("quizTitlePromptInput");
const confirmNamingActionBtn = document.getElementById("confirmNamingActionBtn");
const formatSelectionWrapper = document.getElementById("formatSelectionWrapper");
const quizDownloadFormatSelect = document.getElementById("quizDownloadFormatSelect");

// Dropdown Toggles
document.getElementById("themeBtn").onclick = (e) => {
    e.stopPropagation();
    document.getElementById("textMenu").classList.remove("show");
    document.getElementById("themeMenu").classList.toggle("show");
};

document.getElementById("textBtn").onclick = (e) => {
    e.stopPropagation();
    document.getElementById("themeMenu").classList.remove("show");
    document.getElementById("textMenu").classList.toggle("show");
};

function setTheme(theme) {
    document.body.setAttribute("data-theme", theme);
    localStorage.setItem("theme", theme);
}

function setTextSize(size) {
    document.body.setAttribute("data-text", size);
    localStorage.setItem("textSize", size);
}

document.addEventListener("click", function(e) {
    if (!e.target.closest(".dropdown")) {
        document.querySelectorAll(".dropdown-menu").forEach(m => m.classList.remove("show"));
    }
});

window.addEventListener("DOMContentLoaded", () => {
    const theme = localStorage.getItem("theme") || "light";
    const text = localStorage.getItem("textSize") || "medium";
    document.body.setAttribute("data-theme", theme);
    document.body.setAttribute("data-text", text);
});

function triggerNamingFlow(targetFlow) {
    if (!quizNamingModalOverlay || !quizTitlePromptInput) return;
    currentActiveFlowTarget = targetFlow;
    
    quizTitlePromptInput.value = "<?=htmlspecialchars($quizData['title'] ?? '', ENT_QUOTES, 'UTF-8')?>" || "My Quiz Sheet";
    document.getElementById("quizSubjectPromptInput").value = "<?=htmlspecialchars($quizData['subject'] ?? 'Computer Science', ENT_QUOTES, 'UTF-8')?>"; 
    
    if (targetFlow === 'DOWNLOAD') {
        formatSelectionWrapper.style.display = "flex";
        confirmNamingActionBtn.innerText = "Download Quiz"; 
    } else {
        formatSelectionWrapper.style.display = "none";
        confirmNamingActionBtn.innerText = "Save Changes";   
    }
    
    quizNamingModalOverlay.classList.add("active");
    quizTitlePromptInput.focus();
}

function closeNamingModal() {
    if (quizNamingModalOverlay) quizNamingModalOverlay.classList.remove("active");
}

if (confirmNamingActionBtn) {
    confirmNamingActionBtn.addEventListener("click", () => {
        const structuralNamedTitle = quizTitlePromptInput.value.trim() || "Untitled Quiz";
        const structuralNamedSubject = document.getElementById("quizSubjectPromptInput").value.trim() || "General";
        
        closeNamingModal();
        
      if (currentActiveFlowTarget === 'SAVE') {
            let totalPoints = 0;
            if (Array.isArray(rawQuestionsData)) {
                rawQuestionsData.forEach(q => { totalPoints += parseInt(q.points || 0); });
            }

            const quizPayload = {
                id: activeEditingQuizId ? parseInt(activeEditingQuizId) : null, 
                title: structuralNamedTitle,
                subject: structuralNamedSubject,
                class: "General",
                status: "Active",
                total_points: totalPoints,
                questions_count: rawQuestionsData.length
            };

            fetch("quiz.php?action=save_quiz", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(quizPayload)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    let quizzes = [];
                    try { 
                        quizzes = JSON.parse(localStorage.getItem("quizzes")) || []; 
                    } catch(e) { 
                        quizzes = []; 
                    }
                    
                    if (activeEditingQuizId) {
                        quizzes = quizzes.map(q => {
                            if (String(q.id) === String(activeEditingQuizId)) {
                                return {
                                    ...q,
                                    title: quizPayload.title,
                                    subject: quizPayload.subject,
                                    total_points: quizPayload.total_points,
                                    questions_count: quizPayload.questions_count
                                };
                            }
                            return q;
                        });
                    } else {
                        quizPayload.id = data.data.id || Date.now();
                        quizzes.push(quizPayload);
                    }
                    
                    localStorage.setItem("quizzes", JSON.stringify(quizzes));
                    window.location.href = "teacher.html";
                } else {
                    alert("❌ Database compilation error: " + data.message);
                }
            })
            .catch(err => {
                console.error("Network synchronization failed, saving local mirror state:", err);
                let quizzes = [];
                try { quizzes = JSON.parse(localStorage.getItem("quizzes")) || []; } catch(e) {}
                
                if (activeEditingQuizId) {
                    quizzes = quizzes.map(q => String(q.id) === String(activeEditingQuizId) ? { ...q, ...quizPayload } : q);
                } else {
                    quizPayload.id = Date.now();
                    quizzes.push(quizPayload);
                }
                localStorage.setItem("quizzes", JSON.stringify(quizzes));
                window.location.href = "teacher.html";
            });
            
        } else if (currentActiveFlowTarget === 'DOWNLOAD') {
            const selectedFormat = quizDownloadFormatSelect.value;
            if (selectedFormat === 'html') {
                executeExportDownloadFlow(structuralNamedTitle);
            } else if (selectedFormat === 'docx') {
                executeDocxDownloadFlow(structuralNamedTitle); 
            }
        }
    });
}

function escapeHtmlStrings(text) {
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function executeExportDownloadFlow(assignedQuizTitle) {
    if(rawQuestionsData.length === 0) return alert("Add questions to the bank before exporting.");

    const escapedData = btoa(encodeURIComponent(JSON.stringify(rawQuestionsData)));
    
    let htmlContent = '<!DOCTYPE html>\n<html lang="en">\n<head>\n<meta charset="UTF-8">\n';
    htmlContent += '<meta name="viewport" content="width=device-width, initial-scale=1.0">\n';
    htmlContent += '<title>' + escapeHtmlStrings(assignedQuizTitle) + '</title>\n';
    htmlContent += '<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">\n';
    htmlContent += '<style>\n';
    htmlContent += 'body { font-family: "Poppins", sans-serif; background-color: #F4ECE1; color: #2A2421; min-height: 100vh; padding: 30px 20px; margin:0; }\n';
    htmlContent += '.wrapper { max-width: 750px; margin: 0 auto; background: white; padding: 30px; border-radius: 16px; border: 1px solid #E6DCCE; box-shadow: 0 10px 25px rgba(42,36,33,0.02); }\n';
    htmlContent += 'h1 { margin-bottom: 24px; padding-bottom: 10px; border-bottom: 2px solid #F4ECE1; font-size: 24px; color: #2A2421; }\n';
    htmlContent += '.item { border: 1px solid #E6DCCE; border-radius: 12px; padding: 20px; margin-bottom: 20px; background: #fff; }\n';
    htmlContent += '.q-title-row { display: flex; justify-content: space-between; font-weight: 600; margin-bottom:12px; align-items:center; }\n';
    htmlContent += '.points { background: #E6DCCE; font-size: 11px; padding: 2px 8px; border-radius: 999px; color: #2A2421; }\n';
    htmlContent += '.timer-unit { background: #F5EADA; font-size: 12px; padding: 6px 14px; border-radius: 50px; color: #966938; font-weight:700; transition: all 0.2s; }\n';
    htmlContent += '.timer-unit.critical-red { background: #F9EBEA; color: #923C32; }\n';
    htmlContent += '.opt { display: flex; align-items: center; gap: 12px; padding: 12px; border: 1.5px solid #E6DCCE; border-radius: 10px; margin-bottom: 8px; cursor: pointer; transition: 0.2s ease; }\n';
    htmlContent += '.opt:hover { background: #F4ECE1; border-color: #4C5E3D; }\n';
    htmlContent += 'textarea, input[type="text"] { width: 100%; min-height: 45px; padding: 12px; border-radius: 10px; border: 1.5px solid #E6DCCE; box-sizing: border-box; font-family:inherit; background-color:#fff; outline:none; }\n';
    htmlContent += 'textarea { min-height: 90px; resize: vertical; }\n';
    htmlContent += 'textarea:focus, input[type="text"]:focus { border-color: #4C5E3D; }\n';
    htmlContent += 'button { width: 100%; padding: 14px; background: #A36344; color: white; border: none; border-radius: 10px; font-size: 16px; font-weight:600; cursor:pointer; transition: background 0.2s; }\n';
    htmlContent += 'button:hover { background: #8B5135; }\n';
    htmlContent += '.hero { text-align: center; padding: 24px; background: #E4ECD7; border: 1.5px solid #4C5E3D; border-radius: 12px; margin-bottom: 24px; display:none; color: #415530; }\n';
    htmlContent += '.wrong-indicator { border-left: 5px solid #923C32 !important; }\n';
    htmlContent += '.correct-indicator { border-left: 5px solid #415530 !important; }\n';
    htmlContent += '.exam-slide { display: none; }\n';
    htmlContent += '.active-slide { display: block; }\n';
    htmlContent += '.wizard-ctrls { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; }\n';
    htmlContent += '.btn-wz { width: auto; padding: 10px 24px; background: #4C5E3D; color:white; border:none; border-radius:10px; font-weight:600; cursor:pointer; }\n';
    htmlContent += '.btn-wz-sec { background: #E6DCCE; color: #2A2421; }\n';
    htmlContent += '</style>\n</head>\n<body>\n';
    htmlContent += '<div class="wrapper">\n<div id="resultHero" class="hero">\n';
    htmlContent += '<h2 style="margin:0; color:#415530;">Quiz Complete!</h2>\n';
    htmlContent += '<div id="finalScore" style="font-size:36px; font-weight:700; margin:10px 0;">0 / 0</div>\n</div>\n';
    htmlContent += '<h1 id="mainTitle">' + escapeHtmlStrings(assignedQuizTitle) + '</h1>\n';
    htmlContent += '<div id="globalTimerDisplay" class="timer-unit" style="margin-bottom:20px; text-align:center; display:inline-block;">Question Time Remaining: <b>0s</b></div>\n';
    htmlContent += '<form id="quizForm" onsubmit="gradeQuiz(event)">\n';
    htmlContent += '<div id="questionsContainer"></div>\n';
    htmlContent += '<div class="wizard-ctrls" id="ctrlsArea">\n';
    htmlContent += '<button type="button" class="btn-wz btn-wz-sec" id="pBtn" onclick="moveSlide(-1)">Previous</button>\n';
    htmlContent += '<span id="tracker" style="font-size:14px; font-weight:500;"></span>\n';
    htmlContent += '<button type="button" class="btn-wz" id="nBtn" onclick="moveSlide(1)">Next Question</button>\n';
    htmlContent += '</div>\n</form>\n</div>\n';
    
    htmlContent += '<script>\n';
    htmlContent += 'const questions = JSON.parse(decodeURIComponent(atob("' + escapedData + '")));\n';
    htmlContent += 'const stopWords = ["is","are","was","were","a","an","the","for","of","to","and","or","in","on","used","using","that","this","it","as","by","with"];\n';
    htmlContent += 'let curSlide = 0; let slideTimer = null; let timeLeft = 0; let exportSlideAnswers = {};\n';
    htmlContent += 'function draw() {\n';
    htmlContent += '  const container = document.getElementById("questionsContainer");\n';
    htmlContent += '  questions.forEach((q, idx) => {\n';
    htmlContent += '    let html = \'<div class="item exam-slide" id="block-\'+idx+\'" data-time="\'+(q.time || 30)+\'"><div class="q-title-row"><span>Q\'+(idx+1)+\'. \'+escapeStringNode(q.q)+\'</span><span class="points">\'+q.points+\' Pts</span></div>\';\n';
    htmlContent += '    if(q.type === "MCQ") {\n';
    htmlContent += '      q.options.forEach(opt => {\n';
    htmlContent += '        html += \'<label class="opt"><input type="radio" name="ans-\'+idx+\'" value="\'+escapeStringNode(opt)+\'"> \'+escapeStringNode(opt)+\'</label>\';\n';
    htmlContent += '      });\n';
    htmlContent += '    } else if(q.type === "TF") {\n';
    htmlContent += '      html += \'<label class="opt"><input type="radio" name="ans-\'+idx+\'" value="True"> True</label>\';\n';
    htmlContent += '      html += \'<label class="opt"><input type="radio" name="ans-\'+idx+\'" value="False"> False</label>\';\n';
    htmlContent += '    } else if(q.type === "Blank") {\n';
    htmlContent += '      html += \'<input type="text" name="ans-\'+idx+\'" placeholder="Type missing phrase...">\';\n';
    htmlContent += '    } else {\n';
    htmlContent += '      html += \'<textarea name="ans-\'+idx+\'" placeholder="Type your response here..."></textarea>\';\n';
    htmlContent += '    }\n';
    htmlContent += '    html += \'<div id="feedback-\'+idx+\'" style="margin-top:10px; font-size:13px; display:none;"></div></div>\';\n';
    htmlContent += '    container.innerHTML += html;\n';
    htmlContent += '  });\n';
    htmlContent += '  showSlide(0);\n';
    htmlContent += '}\n';
    htmlContent += 'function escapeStringNode(str) { return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/\'/g, "&#039;"); }\n';
    htmlContent += 'function startTimer() {\n';
    htmlContent += '  if(slideTimer) clearInterval(slideTimer);\n';
    htmlContent += '  const activeSlideEl = document.getElementById("block-" + curSlide);\n';
    htmlContent += '  if(!activeSlideEl) return;\n';
    htmlContent += '  timeLeft = parseInt(activeSlideEl.getAttribute("data-time"));\n';
    htmlContent += '  const display = document.getElementById("globalTimerDisplay");\n';
    htmlContent += '  function updateDisplay() {\n';
    htmlContent += '    display.innerHTML = "Question Time Remaining: <b>" + timeLeft + "s</b>";\n';
    htmlContent += '    if(timeLeft <= 10) display.classList.add("critical-red"); else display.classList.remove("critical-red");\n';
    htmlContent += '  }\n';
    htmlContent += '  updateDisplay();\n';
    htmlContent += '  slideTimer = setInterval(() => {\n';
    htmlContent += '    timeLeft--; updateDisplay();\n';
    htmlContent += '    if(timeLeft <= 0) {\n';
    htmlContent += '      clearInterval(slideTimer);\n';
    htmlContent += '      exportSlideAnswers[curSlide] = "__QUIZ_FORGE_TIMED_OUT__";\n';
    htmlContent += '      lockCurrentSlideInputs();\n';
    htmlContent += '      if(curSlide < questions.length - 1) moveSlide(1); else gradeQuiz(new Event("submit"));\n';
    htmlContent += '    }\n';
    htmlContent += '  }, 1000);\n';
    htmlContent += '}\n'; 
    htmlContent += 'function lockCurrentSlideInputs() {\n';
    htmlContent += '  const block = document.getElementById("block-" + curSlide);\n';
    htmlContent += '  if(block) block.querySelectorAll("input, textarea").forEach(el => el.disabled = true);\n';
    htmlContent += '}\n';
    htmlContent += 'function showSlide(idx) {\n';
    htmlContent += '  document.querySelectorAll(".exam-slide").forEach(s => s.classList.remove("active-slide"));\n';
    htmlContent += '  document.getElementById("block-"+idx).classList.add("active-slide");\n';
    htmlContent += '  curSlide = idx;\n';
    htmlContent += '  document.getElementById("pBtn").style.visibility = idx === 0 ? "hidden" : "visible";\n';
    htmlContent += '  document.getElementById("nBtn").innerText = idx === questions.length - 1 ? "Submit Exam" : "Next Question";\n';
    htmlContent += '  document.getElementById("tracker").innerText = "Question " + (idx+1) + " of " + questions.length;\n';
    htmlContent += '  startTimer();\n';
    htmlContent += '}\n';
    htmlContent += 'function moveSlide(dir) {\n';
    htmlContent += '  let target = curSlide + dir;\n';
    htmlContent += '  if(target >= 0 && target < questions.length) showSlide(target);\n';
    htmlContent += '  else if (target === questions.length) { clearInterval(slideTimer); gradeQuiz(new Event("submit")); }\n';
    htmlContent += '}\n';
    htmlContent += 'function gradeQuiz(e) {\n';
    htmlContent += '  if(e) e.preventDefault(); clearInterval(slideTimer);\n';
    htmlContent += '  document.getElementById("globalTimerDisplay").style.display = "none";\n';
    htmlContent += '  let earned = 0, possible = 0;\n';
    htmlContent += '  questions.forEach((q, idx) => {\n';
    htmlContent += '    const points = parseInt(q.points); possible += points;\n';
    htmlContent += '    let userAns = "";\n';
    htmlContent += '    if (exportSlideAnswers[idx] === "__QUIZ_FORGE_TIMED_OUT__") {\n';
    htmlContent += '      userAns = "__QUIZ_FORGE_TIMED_OUT__";\n';
    htmlContent += '    } else {\n';
    htmlContent += '      const blockEl = document.getElementById("block-"+idx);\n';
    htmlContent += '      if(q.type === "MCQ" || q.type === "TF") {\n';
    htmlContent += '        const selected = blockEl.querySelector(\'input[name="ans-\'+idx+\'"]:checked\');\n';
    htmlContent += '        userAns = selected ? selected.value : "";\n';
    htmlContent += '      } else if (q.type === "Blank") {\n';
    htmlContent += '        const inp = blockEl.querySelector(\'input[name="ans-\'+idx+\'"]\');\n';
    htmlContent += '        userAns = inp ? inp.value : "";\n';
    htmlContent += '      } else {\n';
    htmlContent += '        const txt = blockEl.querySelector(\'textarea[name="ans-\'+idx+\'"]\');\n';
    htmlContent += '        userAns = txt ? txt.value : "";\n';
    htmlContent += '      }\n';
    htmlContent += '    }\n';
    htmlContent += '    let isCorrect = false;\n';
    htmlContent += '    if (userAns !== "__QUIZ_FORGE_TIMED_OUT__") {\n';
    htmlContent += '      if(q.type === "MCQ" || q.type === "TF" || q.type === "Blank") {\n';
    htmlContent += '        if(userAns.trim().toLowerCase() === (Array.isArray(q.a) ? q.a[0] : q.a).trim().toLowerCase()) isCorrect = true;\n';
    htmlContent += '      } else {\n';
    htmlContent += '        let words = userAns.toLowerCase().replace(/[^a-z0-9\\s]/g, " ").split(" ").filter(w => w && !stopWords.includes(w));\n';
    htmlContent += '        let matches = words.filter(w => q.a.includes(w));\n';
    htmlContent += '        if(matches.length > 0 && q.a.length > 0) isCorrect = true;\n';
    htmlContent += '      }\n';
    htmlContent += '    }\n';
    htmlContent += '    const cardBlock = document.getElementById("block-"+idx);\n';
    htmlContent += '    const feedback = document.getElementById("feedback-"+idx);\n';
    htmlContent += '    cardBlock.classList.add("active-slide");\n';
    htmlContent += '    cardBlock.querySelectorAll("input, textarea").forEach(el => el.disabled = true);\n';
    htmlContent += '    if(isCorrect) {\n';
    htmlContent += '      earned += points; cardBlock.classList.add("correct-indicator");\n';
    htmlContent += '      feedback.innerHTML = \'<span style="color:#415530; font-weight:600;">✓ Correct!</span>\';\n';
    htmlContent += '    } else {\n';
    htmlContent += '      cardBlock.classList.add("wrong-indicator");\n';
    htmlContent += '      let targetInfo = (q.type === "MCQ" || q.type === "TF" || q.type === "Blank") ? q.a : q.a.join(", ");\n';
    htmlContent += '      if (userAns === "__QUIZ_FORGE_TIMED_OUT__") {\n';
    htmlContent += '        feedback.innerHTML = \'<span style="color:#923C32; font-weight:600;">✗ Failed (Time Out).</span> Expected target: <b>\'+escapeStringNode(targetInfo)+\'</b>\';\n';
    htmlContent += '      } else {\n';
    htmlContent += '        feedback.innerHTML = \'<span style="color:#923C32; font-weight:600;">✗ Failed (Incorrect Answer).</span> Expected target: <b>\'+escapeStringNode(targetInfo)+\'</b>\';\n';
    htmlContent += '      }\n';
    htmlContent += '    }\n';
    htmlContent += '    feedback.style.display = "block";\n';
    htmlContent += '  });\n';
    htmlContent += '  document.getElementById("resultHero").style.display = "block";\n';
    htmlContent += '  document.getElementById("finalScore").innerText = earned + " / " + possible;\n';
    htmlContent += '  document.getElementById("ctrlsArea").style.display = "none";\n';
    htmlContent += '  document.getElementById("mainTitle").innerText = "Evaluation Report";\n';
    htmlContent += '  window.scrollTo({top: 0, behavior: "smooth"});\n';
    htmlContent += '}\n';
    htmlContent += 'draw();\n';
    htmlContent += '<\/script>\n</body>\n</html>';
    
    const blob = new Blob([htmlContent], { type: 'text/html' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = assignedQuizTitle.toLowerCase().replace(/[^a-z0-9]/g, "-") + ".html";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

function executeDocxDownloadFlow(assignedQuizTitle) {
    if (rawQuestionsData.length === 0) return alert("Add questions to the bank before exporting.");

    let docxContent = `QUIZ TITLE: ${assignedQuizTitle.toUpperCase()}\n`;
    docxContent += `Subject: ${document.getElementById("quizSubjectPromptInput").value.trim() || "General"}\n`;
    docxContent += `Total Assessment Weight: ${document.getElementById("bankCount") ? document.getElementById("bankCount").innerText : rawQuestionsData.length} Items\n`;
    docxContent += `========================================================================\n\n`;

    rawQuestionsData.forEach((q, idx) => {
        docxContent += `Q${idx + 1}. [${q.type}] ${q.q} (${q.points} Pts / Time Limit: ${q.time}s)\n`;
        
        if (q.type === "MCQ" && Array.isArray(q.options)) {
            q.options.forEach((opt, oIdx) => {
                docxContent += `   [ ] ${String.fromCharCode(65 + oIdx)}) ${opt}\n`;
            });
            docxContent += `\n   *Expected Answer Option Target: ${q.a}\n`;
        } else if (q.type === "TF") {
            docxContent += `   [ ] True\n   [ ] False\n`;
            docxContent += `\n   *Expected Status Evaluation Target: ${q.a}\n`;
        } else if (q.type === "Blank") {
            docxContent += `   Fill-in String Field Box: _________________________\n`;
            docxContent += `\n   *Expected Match Target Token: ${q.a}\n`;
        } else {
            docxContent += `   Full Text Structural Free-Response Workspace Area:\n\n\n\n`;
            docxContent += `   *Required Evaluation Keyphrases: ${Array.isArray(q.a) ? q.a.join(", ") : q.a}\n`;
        }
        docxContent += `\n------------------------------------------------------------------------\n\n`;
    });

    const blob = new Blob([docxContent], { type: 'application/msword' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = assignedQuizTitle.toLowerCase().replace(/[^a-z0-9]/g, "-") + "-sheet.doc";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

function toggleAccordion(wrapperElement) {
    const parentCard = wrapperElement.closest('.bank-item');
    if(!parentCard || parentCard.classList.contains('dragging')) return;

    document.querySelectorAll('.bank-item').forEach(item => {
        if(item !== parentCard) item.classList.remove('expanded');
    });
    parentCard.classList.toggle('expanded');
}

function openDeleteModal(index, queryContext) {
    if(!deleteModalOverlay) return;
    deleteModalOverlay.classList.add("active");
    confirmDeleteBtn.onclick = function() {
        window.location.href = `?action=delete&id=${index}` + queryContext;
    };
}

function closeDeleteModal() {
    if(!deleteModalOverlay) return;
    deleteModalOverlay.classList.remove("active");
}

if(deleteModalOverlay) {
    deleteModalOverlay.addEventListener("click", function(e) {
        if (e.target === deleteModalOverlay) closeDeleteModal();
    });
}

function render(){
    if(!container || !hidden) return;
    container.innerHTML = "";
    hidden.innerHTML = "";

    keywords.forEach((k, i) => {
        let tag = document.createElement("span");
        tag.className = "tag";
        tag.innerHTML = k + " <button type='button' onclick='removeKw(" + i + ")'>&times;</button>";
        container.appendChild(tag);

        let inp = document.createElement("input");
        inp.type = "hidden";
        inp.name = "answerText[]";
        inp.value = k;
        hidden.appendChild(inp);
    });
}

function addKeyword(){
    if(!input) return;
    const value = input.value.trim();
    if(value !== ""){
        keywords.push(value);
        input.value = "";
        render();
    }
}

function removeKw(i){
    keywords.splice(i, 1);
    render();
}

if(input) {
    input.addEventListener("keydown", function(e){
        if(e.key === "Enter"){
            e.preventDefault(); 
            addKeyword();
        }
    });
}

function updateRequiredFields() {
    if(!typeSelect) return;
    const val = typeSelect.value;
    
    document.getElementById("mcqBox").style.display = (val === "MCQ") ? "block" : "none";
    document.getElementById("tfBox").style.display = (val === "TF") ? "block" : "none";
    document.getElementById("blankBox").style.display = (val === "Blank") ? "block" : "none";
    document.getElementById("textBox").style.display = (val === "Text") ? "block" : "none";
    
    // Toggle requirements cleanly
    if(document.getElementsByName("option1")[0]) {
        document.getElementsByName("option1")[0].required = (val === "MCQ");
        document.getElementsByName("option2")[0].required = (val === "MCQ");
    }
    
    const blankInp = document.getElementById("blankAnswer");
    if(blankInp) blankInp.required = (val === "Blank");

    if(questionPromptArea) {
        if (val === "Blank") {
            questionPromptArea.placeholder = "Enter question here... Use underscores like '____' to indicate fields.";
        } else {
            questionPromptArea.placeholder = "Enter question here...";
        }
    }
}

if(typeSelect) {
    typeSelect.addEventListener("change", updateRequiredFields);
    updateRequiredFields();
}

if(keywords.length > 0) {
    render();
}

/* ================= DRAG AND DROP PERSISTENCE ENGINE ================= */
function getActiveSortedOrder() {
    const cards = document.querySelectorAll("#dragContainerArea .bank-item");
    return Array.from(cards).map(card => parseInt(card.getAttribute("data-original-index")));
}

if (dragContainerArea) {
    dragContainerArea.addEventListener("dragstart", e => {
        const targetCard = e.target.closest(".bank-item");
        if (targetCard) targetCard.classList.add("dragging");
    });

    dragContainerArea.addEventListener("dragend", e => {
        const targetCard = e.target.closest(".bank-item");
        if (targetCard) {
            targetCard.classList.remove("dragging");
            rebuildLabelsAndIndices();
            
            const currentSorting = getActiveSortedOrder();
            fetch("?action=updateOrder", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ order: currentSorting })
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') console.log("Order state preserved successfully.");
            })
            .catch(err => console.error("Session preservation sync error: ", err));
        }
    });

    dragContainerArea.addEventListener("dragover", e => {
        e.preventDefault();
        const draggingCard = document.querySelector(".bank-item.dragging");
        if (!draggingCard) return;

        const dynamicSiblings = Array.from(document.querySelectorAll("#dragContainerArea .bank-item:not(.dragging)"));
        let nextSibling = dynamicSiblings.find(sibling => {
            const box = sibling.getBoundingClientRect();
            return e.clientY <= box.top + box.height / 2;
        });

        if (nextSibling) {
            dragContainerArea.insertBefore(draggingCard, nextSibling);
        } else {
            dragContainerArea.appendChild(draggingCard);
        }
    });
}

function rebuildLabelsAndIndices() {
    const totalRowElements = document.querySelectorAll("#dragContainerArea .bank-item");
    totalRowElements.forEach((row, sequentialIdx) => {
        const labelNode = row.querySelector(".row-num-label");
        if (labelNode) labelNode.innerText = (sequentialIdx + 1);
    });
}

/* ================= LIVE APPLICATION STEP WIZARD ENGINE ================= */
let activeWizardSlideIndex = 0;

function renderWizardState() {
    const slides = document.querySelectorAll(".exam-slide");
    if(slides.length === 0) return;

    slides.forEach(slide => slide.classList.remove("active-slide"));
    const currentActiveSlide = document.getElementById('slide-' + activeWizardSlideIndex);
    if(currentActiveSlide) currentActiveSlide.classList.add("active-slide");

    document.getElementById("prevSlideBtn").style.visibility = (activeWizardSlideIndex === 0) ? "hidden" : "visible";
    document.getElementById("nextSlideBtn").innerText = (activeWizardSlideIndex === slides.length - 1) ? "Finish Preview" : "Next Question";
    document.getElementById("wizardStepTracker").innerText = `Question ${activeWizardSlideIndex + 1} of ${slides.length}`;
}

function navigateWizard(direction) {
    const slides = document.querySelectorAll(".exam-slide");
    let calculatedIndex = activeWizardSlideIndex + direction;

    if (calculatedIndex >= 0 && calculatedIndex < slides.length) {
        activeWizardSlideIndex = calculatedIndex;
        renderWizardState();
    } else if (calculatedIndex === slides.length) {
        triggerNamingFlow('SAVE');
    }
}

document.addEventListener("DOMContentLoaded", () => {
    if(document.querySelector(".exam-slide")) {
        renderWizardState();
    }
});
</script>

<footer style="margin-top: 80px; padding: 40px; text-align: center; color: var(--text-muted); border-top: 1px solid var(--border-color);">
    <p>&copy; <?php echo date('Y'); ?> QuizVerse. All rights reserved.</p>
    <p style="font-size: 13px; margin-top: 8px;">Modern online quiz management system.</p>
</footer>
</body>
</html>