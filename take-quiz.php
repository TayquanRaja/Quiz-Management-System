<?php
include "db.php";
session_start();

// Auth Guard Check: Ensure a valid student session exists
if (!isset($_SESSION['user_id'])) {
    header("Location: register.html");
    exit;
}

$quizId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($quizId <= 0) {
    echo "<div style='padding:30px; text-align:center; font-family:sans-serif;'><h3>❌ Error: Missing or invalid Quiz ID parameter.</h3><a href='student.php'>Return to Dashboard</a></div>";
    exit;
}

// 1. Fetch Quiz Configuration parameters
$quizQuery = $conn->prepare("SELECT * FROM quizzes WHERE id = ? AND status = 'Publish'");
$quizQuery->bind_param("i", $quizId);
$quizQuery->execute();
$quizMeta = $quizQuery->get_result()->fetch_assoc();
$quizQuery->close();

if (!$quizMeta) {
    echo "<div style='padding:30px; text-align:center; font-family:sans-serif;'><h3>❌ Error: Requested quiz is either unavailable or restricted.</h3><a href='student.php'>Return to Dashboard</a></div>";
    exit;
}

// 2. Fetch questions along with their answers safely stored in the database
$questionsList = [];
$qQuery = $conn->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id ASC");
$qQuery->bind_param("i", $quizId);
$qQuery->execute();
$qResult = $qQuery->get_result();

while ($row = $qResult->fetch_assoc()) {
    $optionsArray = !empty($row['options']) ? json_decode($row['options'], true) : [];
    
    // Safety check for option formatting structures
    if (!is_array($optionsArray) && !empty($row['options'])) {
        $optionsArray = explode(",", str_replace(['[', ']', '"'], '', $row['options']));
    }

    $questionsList[] = [
        'id' => $row['id'],
        'type' => $row['type'],
        'question_text' => $row['question_text'],
        'options' => $optionsArray,
        'correct_answer' => $row['correct_answer'], // Retrieved straight from database row arrays
        'points' => intval($row['points']),
        'time_limit' => intval($row['time_limit'] ?? 30)
    ];
}
$qQuery->close();

if (empty($questionsList)) {
    echo "<div style='padding:30px; text-align:center; font-family:sans-serif;'><h3>⚠️ This quiz contains no questions yet.</h3><a href='student.php'>Return to Dashboard</a></div>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Engine - <?= htmlspecialchars($quizMeta['title']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
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
body[data-text="small"] #quizAppMainTitle,
body[data-text="small"] #finalScoreDisplay,
body[data-text="small"] .hero-banner h2 {
  font-size: 18px !important;
}

body[data-text="medium"] #quizAppMainTitle,
body[data-text="medium"] #finalScoreDisplay,
body[data-text="medium"] .hero-banner h2 {
  font-size: 24px !important;
}

body[data-text="large"] #quizAppMainTitle,
body[data-text="large"] #finalScoreDisplay,
body[data-text="large"] .hero-banner h2 {
  font-size: 32px !important;
}
 body[data-theme="dark"] {
  --bg: #0f141c;
  --color-card-bg: #1b2433;
  --color-text-primary: #f3f6fa;
  --color-text-secondary: #a7b4c6;
  --color-border: #2e3a4d;
  --color-sidebar-bg: #243447;
  --color-accent-terracotta: #d08770;
}

/* FORCE APPLY TO WHOLE PAGE */
body[data-theme="dark"] {
  background: var(--bg) !important;
  color: var(--color-text-primary) !important;
}

body[data-theme="dark"] .wrapper,
body[data-theme="dark"] .exam-slide,
body[data-theme="dark"] .hero-banner,
body[data-theme="dark"] .wizard-ctrls,
body[data-theme="dark"] .opt-label,
body[data-theme="dark"] textarea,
body[data-theme="dark"] input,
body[data-theme="dark"] .dropdown-menu {
  background: var(--color-card-bg) !important;
  color: var(--color-text-primary) !important;
  border-color: var(--color-border) !important;
}

/* Topbar + Sidebar */
body[data-theme="dark"] .topbar,
body[data-theme="dark"] .sidebar {
  background: var(--color-sidebar-bg) !important;
}
body[data-theme="blue"] {
  --bg: #060b14;
  --color-card-bg: #0f172a;
  --color-text-primary: #e0f7ff;
  --color-text-secondary: #7dd3fc;
  --color-border: #164e63;
  --color-sidebar-bg: #00bfff;
  --color-accent-terracotta: #00f5ff;
}
body[data-theme="dark"] 
#quizAppMainTitle,
body[data-theme="dark"] 
.hero-banner h2,
body[data-theme="dark"] 
#trackerStepsLabel,
body[data-theme="dark"] 
#pageTitle {
  color: #ffffff !important;
}
/* FORCE FULL PAGE BACKGROUND */
body[data-theme="blue"] {
  background: var(--bg) !important;
  color: var(--color-text-primary) !important;
}

/* ALL CARDS + QUIZ + WRAPPERS */
body[data-theme="blue"] .wrapper,
body[data-theme="blue"] .exam-slide,
body[data-theme="blue"] .hero-banner,
body[data-theme="blue"] .wizard-ctrls,
body[data-theme="blue"] .opt-label,
body[data-theme="blue"] textarea,
body[data-theme="blue"] input,
body[data-theme="blue"] .dropdown-menu {
  background: var(--color-card-bg) !important;
  color: var(--color-text-primary) !important;
  border: 1px solid rgba(0, 245, 255, 0.25) !important;
  box-shadow: 0 0 10px rgba(0, 245, 255, 0.15) !important;
}
body[data-theme="blue"] 
#quizAppMainTitle,
body[data-theme="blue"] 
.hero-banner h2,
body[data-theme="blue"] 
#trackerStepsLabel,
body[data-theme="blue"] 
#pageTitle {
  color: #00f5ff !important;
  text-shadow: 0 0 8px rgba(0, 245, 255, 0.6);
}
/* NEON TOPBAR + SIDEBAR */
body[data-theme="blue"] .topbar,
body[data-theme="blue"] .sidebar {
  background: rgba(0, 20, 40, 0.92) !important;
  box-shadow: 0 0 20px rgba(0, 245, 255, 0.18) !important;
}

/* NEON BUTTONS */
body[data-theme="blue"] .btn-wz {
  background: linear-gradient(135deg, #00f5ff, #009dff) !important;
  color: #001018 !important;
}      
body {
            font-family: 'Poppins', sans-serif;
             background: #F4ECE1;
  color: #2A2421;
            min-height: 100vh;
            padding: 30px 20px;
            
        }
        .header-nav {
    position: fixed;
    top: 0;
    
    height: 80px;

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
        .wrapper {
             margin-top: 110px;
            max-width: 750px;
         margin: 110px auto 40px auto;
            background: #ffffff;
            padding: 35px;
            border-radius: 16px;
            border: 1px solid #E6DCCE;
          flex:1;
            box-shadow: 0 10px 25px rgba(42,36,33,0.02);
        }
        h1 {
            font-size: 24px;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #F4ECE1;
            color: #2A2421;
        }
        .meta-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .timer-unit {
            background: #F5EADA;
            font-size: 14px;
            padding: 8px 16px;
            border-radius: 50px;
            color: #966938;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .timer-unit.critical-red {
            background: #F9EBEA;
            color: #923C32;
            animation: pulse 1s infinite alternate;
        }
        @keyframes pulse {
            from { transform: scale(1); } to { transform: scale(1.02); }
        }
        .exam-slide { display: none; }
        .exam-slide.active-slide { display: block; }
        
        .q-title-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 18px;
            gap: 12px;
        }
        .points-badge {
            background: #4C5E3D;
            color: white;
            font-size: 11px;
            padding: 3px 10px;
            border-radius: 999px;
            white-space: nowrap;
        }
        .opt-label {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px;
            border: 1.5px solid #E6DCCE;
            border-radius: 10px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: 0.2s ease;
            font-size: 14px;
        }
        .opt-label:hover {
            background: #F4ECE1;
            border-color: #4C5E3D;
        }
        .opt-label input[type="radio"] {
            accent-color: #A36344;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        textarea, input[type="text"] {
            width: 100%;
            padding: 14px;
            border-radius: 10px;
            border: 1.5px solid #E6DCCE;
            box-sizing: border-box;
            font-family: inherit;
            background-color: #fff;
            outline: none;
            font-size: 14px;
        }
        textarea:focus, input[type="text"]:focus {
            border-color: #4C5E3D;
            box-shadow: 0 0 0 4px rgba(76, 94, 61, 0.15);
        }
        textarea { min-height: 120px; resize: vertical; }
        
        .wizard-ctrls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #E6DCCE;
        }
        .btn-wz {
            padding: 12px 24px;
            background: #4C5E3D;
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-wz:hover { background: #3D4C31; }
        .btn-wz-sec { background: #E6DCCE; color: #2A2421; }
        .btn-wz-sec:hover { background: #dcd0bf; }
        .btn-finish { background-color: #A36344 !important; }
        .btn-finish:hover { background-color: #8B5135 !important; }
        
        /* Evaluation Results Panels Styles */
        .hero-banner {
            text-align: center;
            padding: 30px;
            background: #E4ECD7;
            border: 2px solid #4C5E3D;
            border-radius: 14px;
            margin-bottom: 25px;
            color: #415530;
            display: none;
        }
        .wrong-indicator { border-left: 6px solid #923C32 !important; background: #FFF9F9; margin-bottom: 15px; padding: 15px; border-radius: 8px; }
        .correct-indicator { border-left: 6px solid #415530 !important; background: #F9FFF9; margin-bottom: 15px; padding: 15px; border-radius: 8px; }
        .feedback-node { margin-top: 12px; font-size: 14px; font-weight: 500; }




    </style>
</head>
<body>
 <header class="header-nav">
    <div class="logo">
        <div class="logo-box">
            <img src="quizverse-logo.png" alt="QuizVerse Logo">
        </div>
        <h2 id="pageTitle">QuizVerse</h2>
    </div>

    <div class="right-nav-group">
        <nav>
           <a href="student.php">
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
<div class="wrapper">
    <div id="resultSummaryHero" class="hero-banner">
        <h2 style="font-weight: 700; font-size: 28px; "><i class="fa-solid fa-square-poll-vertical"></i> Quiz Evaluation Finished!</h2>
        <div id="finalScoreDisplay" style="font-weight: 700; margin: 12px 0;">0 / 0</div>
        <p style="font-size: 14px;">Your results have been processed using the database answer references.</p>
        <button class="btn-wz" style="margin-top: 15px; background: #A36344;" onclick="window.location.href='student.php'">Return to Dashboard</button>
    </div>

    <h1 id="quizAppMainTitle"><?= htmlspecialchars($quizMeta['title']) ?></h1>
    
    <div class="meta-header-row" id="globalTimerRow">
        <div class="timer-unit" id="countdownTimerDisplay">
            <i class="fa-regular fa-clock"></i> Question Time Remaining: <b>0s</b>
        </div>
        <span id="trackerStepsLabel" style="font-size: 14px; font-weight: 500; color: #82756a;">Question 1 of <?= count($questionsList) ?></span>
    </div>

    <form id="activeQuizExecutionForm" onsubmit="compileQuizAnswers(event)">
        <div id="runtimeQuestionsContainer">
            <?php foreach ($questionsList as $index => $q): ?>
                <div class="exam-slide <?= $index === 0 ? 'active-slide' : '' ?>" id="block-<?= $index ?>" data-time="<?= $q['time_limit'] ?>">
                    <div class="q-title-row">
                        <span><b>Q<?= ($index + 1) ?>.</b> <?= htmlspecialchars($q['question_text']) ?></span>
                        <span class="points-badge"><?= $q['points'] ?> <?= $q['points'] === 1 ? 'Point' : 'Points' ?></span>
                    </div>

                    <div class="answer-interactive-input-zone">
                        <?php if ($q['type'] === 'MCQ'): ?>
                            <?php foreach ($q['options'] as $option): ?>
                                <label class="opt-label">
                                    <input type="radio" name="ans-<?= $index ?>" value="<?= htmlspecialchars($option) ?>">
                                    <span><?= htmlspecialchars($option) ?></span>
                                </label>
                            <?php endforeach; ?>

                        <?php elseif ($q['type'] === 'TF'): ?>
                            <label class="opt-label">
                                <input type="radio" name="ans-<?= $index ?>" value="True">
                                <span>True</span>
                            </label>
                            <label class="opt-label">
                                <input type="radio" name="ans-<?= $index ?>" value="False">
                                <span>False</span>
                            </label>

                        <?php elseif ($q['type'] === 'Blank'): ?>
                            <input type="text" name="ans-<?= $index ?>" placeholder="Type your missing phrase answer response here...">

                        <?php elseif ($q['type'] === 'Text'): ?>
                            <textarea name="ans-<?= $index ?>" placeholder="Type your context answer here..."></textarea>
                        <?php endif; ?>
                    </div>
                    
                    <div id="feedback-frame-<?= $index ?>" class="feedback-node" style="display: none;"></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="wizard-ctrls" id="wizardToolbarControls">
            <button type="button" class="btn-wz btn-wz-sec" id="btnPrevSlide" onclick="shiftWizardSlide(-1)">Previous</button>
            <button type="button" class="btn-wz" id="btnNextSlide" onclick="shiftWizardSlide(1)">Next Question</button>
        </div>
    </form>
</div>

<script>
    // Inject database values cleanly into runtime context variables
    const questionsPool = <?= json_encode($questionsList) ?>;
    const commonStopWords = ["is","are","was","were","a","an","the","for","of","to","and","or","in","on","used","using","that","this","it","as","by","with"];
    
    let activeSlideIndex = 0;
    let countdownIntervalEngine = null;
    let questionTimeRemaining = 0;
    let trackTimeoutResponses = {};

    function initTimerEngine() {
        if(countdownIntervalEngine) clearInterval(countdownIntervalEngine);
        
        const currentActiveSlideFrame = document.getElementById("block-" + activeSlideIndex);
        if(!currentActiveSlideFrame) return;
        
        // Timer initialized precisely at the database question's explicit time_limit field value
        questionTimeRemaining = parseInt(currentActiveSlideFrame.getAttribute("data-time")) || 30;
        const timerDisplayNode = document.getElementById("countdownTimerDisplay");
        
        function drawTickText() {
            timerDisplayNode.innerHTML = `<i class="fa-regular fa-clock"></i> Question Time Remaining: <b>${questionTimeRemaining}s</b>`;
            if (questionTimeRemaining <= 10) {
                timerDisplayNode.classList.add("critical-red");
            } else {
                timerDisplayNode.classList.remove("critical-red");
            }
        }
        
        drawTickText();
        
        countdownIntervalEngine = setInterval(() => {
            questionTimeRemaining--;
            drawTickText();
            
            if (questionTimeRemaining <= 0) {
                clearInterval(countdownIntervalEngine);
                trackTimeoutResponses[activeSlideIndex] = "__QUIZ_FORGE_TIMED_OUT__";
                lockCurrentInputs();
                
                // If countdown finishes on last item, force evaluation trigger completely
                if (activeSlideIndex < questionsPool.length - 1) {
                    shiftWizardSlide(1);
                } else {
                    compileQuizAnswers();
                }
            }
        }, 1000);
    }

    function lockCurrentInputs() {
        const activeBlock = document.getElementById("block-" + activeSlideIndex);
        if (activeBlock) {
            activeBlock.querySelectorAll("input, textarea").forEach(input => input.disabled = true);
        }
    }

    function showWizardSlide(targetIndex) {
        document.querySelectorAll(".exam-slide").forEach(slide => slide.classList.remove("active-slide"));
        document.getElementById("block-" + targetIndex).classList.add("active-slide");
        
        activeSlideIndex = targetIndex;
        
        // Visibility adjustments
        document.getElementById("btnPrevSlide").style.visibility = (targetIndex === 0) ? "hidden" : "visible";
        document.getElementById("trackerStepsLabel").innerText = `Question ${targetIndex + 1} of ${questionsPool.length}`;
        
        const nextButton = document.getElementById("btnNextSlide");
        
        // CONDITIONAL HANDSHAKE: Swap element details seamlessly if tracking last array index
        if (targetIndex === questionsPool.length - 1) {
            nextButton.innerText = "Finish Quiz";
            nextButton.classList.add("btn-finish");
        } else {
            nextButton.innerText = "Next Question";
            nextButton.classList.remove("btn-finish");
        }
        
        initTimerEngine();
    }

    function shiftWizardSlide(direction) {
        let calculatedIndex = activeSlideIndex + direction;
        if (calculatedIndex >= 0 && calculatedIndex < questionsPool.length) {
            showWizardSlide(calculatedIndex);
        } else if (calculatedIndex === questionsPool.length) {
            clearInterval(countdownIntervalEngine);
            compileQuizAnswers();
        }
    }

    function compileQuizAnswers(event) {
        if (event) event.preventDefault();
        clearInterval(countdownIntervalEngine);
        
        document.getElementById("globalTimerRow").style.display = "none";
        document.getElementById("wizardToolbarControls").style.display = "none";
        document.getElementById("quizAppMainTitle").innerText = "Evaluation Performance Report";

        let studentTotalScoreEarned = 0;
        let quizMaxPossibleScore = 0;

        // Grade user input arrays against database mapped references directly
        questionsPool.forEach((q, idx) => {
            const pointsValue = parseInt(q.points || 1);
            quizMaxPossibleScore += pointsValue;
            
            let studentAnswerString = "";
            const currentBlock = document.getElementById("block-" + idx);

            if (trackTimeoutResponses[idx] === "__QUIZ_FORGE_TIMED_OUT__") {
                studentAnswerString = "__QUIZ_FORGE_TIMED_OUT__";
            } else {
                if (q.type === 'MCQ' || q.type === 'TF') {
                    const selected = currentBlock.querySelector(`input[name="ans-${idx}"]:checked`);
                    studentAnswerString = selected ? selected.value : "";
                } else if (q.type === 'Blank') {
                    const inputField = currentBlock.querySelector(`input[name="ans-${idx}"]`);
                    studentAnswerString = inputField ? inputField.value : "";
                } else if (q.type === 'Text') {
                    const userText = currentBlock.querySelector(`textarea[name="ans-${idx}"]`);
                    studentAnswerString = userText ? userText.value : "";
                }
            }

            let isEvaluationCorrect = false;
            
            // Clean parsing structure checking targets for raw database items
            let databaseAnswerKey = q.correct_answer;
            try {
                // Handle text array conversions safely if JSON keywords are returned from the database table rows
                let complexParse = JSON.parse(databaseAnswerKey);
                databaseAnswerKey = complexParse;
            } catch(e) {}

            if (studentAnswerString !== "__QUIZ_FORGE_TIMED_OUT__" && databaseAnswerKey) {
                if (q.type === 'MCQ' || q.type === 'TF' || q.type === 'Blank') {
                    if (String(studentAnswerString).trim().toLowerCase() === String(databaseAnswerKey).trim().toLowerCase()) {
                        isEvaluationCorrect = true;
                    }
                } else if (q.type === 'Text') {
                    // Keyword extraction validation comparison sequence loops
                    let textCleaningTokens = String(studentAnswerString).toLowerCase().replace(/[^a-z0-9\s]/g, " ").split(" ").filter(w => w && !commonStopWords.includes(w));
                    let baselineKeywords = Array.isArray(databaseAnswerKey) ? databaseAnswerKey.map(v => String(v).toLowerCase()) : [String(databaseAnswerKey).toLowerCase()];
                    let intersectionMatches = textCleaningTokens.filter(word => baselineKeywords.includes(word));
                    
                    if (intersectionMatches.length > 0) {
                        isEvaluationCorrect = true;
                    }
                }
            }

            // Expand all slides simultaneously to present a unified performance report sheet
            currentBlock.classList.add("active-slide"); 
            currentBlock.querySelectorAll("input, textarea").forEach(el => el.disabled = true);
            
            const feedbackPlaceholder = document.getElementById("feedback-frame-" + idx);
            feedbackPlaceholder.style.display = "block";

            let normalizedTargetInfo = Array.isArray(databaseAnswerKey) ? databaseAnswerKey.join(", ") : databaseAnswerKey;

            if (isEvaluationCorrect) {
                studentTotalScoreEarned += pointsValue;
                currentBlock.classList.add("correct-indicator");
                feedbackPlaceholder.innerHTML = `<span style="color:#415530; font-weight:600;"><i class="fa-solid fa-circle-check"></i> Correct!</span> Target criteria validated perfectly.`;
            } else {
                currentBlock.classList.add("wrong-indicator");
                if (studentAnswerString === "__QUIZ_FORGE_TIMED_OUT__") {
                    feedbackPlaceholder.innerHTML = `<span style="color:#923C32; font-weight:600;"><i class="fa-solid fa-triangle-exclamation"></i> Timed Out!</span> Limit exceeded. Expected criteria target: <b>${escapeOutputString(normalizedTargetInfo)}</b>`;
                } else {
                    feedbackPlaceholder.innerHTML = `<span style="color:#923C32; font-weight:600;"><i class="fa-solid fa-circle-xmark"></i> Incorrect Answer.</span> Expected criteria target: <b>${escapeOutputString(normalizedTargetInfo)}</b>`;
                }
            }
        });

      // Show Results Hero Block Summary
        document.getElementById("resultSummaryHero").style.display = "block";
        document.getElementById("finalScoreDisplay").innerText = `${studentTotalScoreEarned} / ${quizMaxPossibleScore} Pts`;
        
        // --- NEW: LIVE DATABASE INSERTION ENGINE ---
      // --- LIVE DATABASE INSERTION ENGINE ---
        const finalPercentage = ((studentTotalScoreEarned / quizMaxPossibleScore) * 100).toFixed(2);
        
        const resultPayload = {
            quiz_id: <?= $quizId ?>,
            score: studentTotalScoreEarned,
            total_points: quizMaxPossibleScore,
            percentage: finalPercentage,
            // FIXED: Dynamically passes the logged-in student session ID instead of an empty property
            student_id: <?= intval($_SESSION['user_id']) ?> 
        };

        // Send data asynchronously to the backend processor
        fetch("save_result.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(resultPayload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                console.log("💾 Result securely stored in MySQL database!");
            } else {
                console.error("❌ Database insertion rejected:", data.message);
            }
        })
        .catch(err => console.error("Network synchronization failed:", err));
        // Keep your localStorage mirror intact as a fallback cache
        let performanceCache = [];
        try { performanceCache = JSON.parse(localStorage.getItem("results")) || []; } catch(e) {}
        performanceCache.push({
            id: Date.now(),
            studentName: "Student Portal User",
            quizId: <?= $quizId ?>,
            quizTitle: "<?= htmlspecialchars($quizMeta['title']) ?>",
            score: studentTotalScoreEarned,
            total: quizMaxPossibleScore,
            percentage: finalPercentage,
            date: new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }),
            published: true
        });
        localStorage.setItem("results", JSON.stringify(performanceCache));
        
        window.scrollTo({ top: 0, behavior: "smooth" });
                        }

    function escapeOutputString(text) {
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    window.addEventListener("DOMContentLoaded", () => {
        showWizardSlide(0);
    });
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


</script>

<footer style=" margin-top:auto;  padding: 40px; text-align: center; color: var(--text-muted); border-top: 1px solid var(--border-color);">
    <p>&copy; <?php echo date('Y'); ?> QuizVerse. All rights reserved.</p>
    <p style="font-size: 13px; margin-top: 8px;">Modern online quiz management system.</p>
</footer>

</body>
</html>
