<?php
include "db.php";
session_start();

// Auth Guard Check: Ensure a valid student session exists before rendering data
if (!isset($_SESSION['user_id']) ||
    $_SESSION['user']['role'] !== 'student'
){
    header("Location: login.php");
    exit;
}

$student_id = intval($_SESSION['user_id']);
$student_name = $_SESSION['user']['name'] ?? 'Student';

// Fetch only quizzes that have a status of 'Publish' matching the database configuration
$publishedQuizzes = [];
$query = "SELECT * FROM quizzes WHERE status = 'Publish' ORDER BY id DESC";
$result = $conn->query($query);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $publishedQuizzes[] = $row;
    }
}

// Group the published quizzes by subject on the backend server side
$groupedQuizzes = [];
foreach ($publishedQuizzes as $quiz) {
    $subject = (!empty($quiz['subject']) && trim($quiz['subject']) !== '') ? strtolower(trim($quiz['subject'])) : 'general';
    $groupedQuizzes[$subject][] = $quiz;
}

// --- FETCH COMPLETED ATTEMPT RECORDS FOR THIS LOGGED-IN USER ---
$attemptedQuizIds = [];
$attemptQuery = $conn->prepare("SELECT DISTINCT quiz_id FROM results WHERE student_id = ?");
if ($attemptQuery) {
    $attemptQuery->bind_param("i", $student_id);
    $attemptQuery->execute();
    $attemptResult = $attemptQuery->get_result();
    while ($row = $attemptResult->fetch_assoc()) {
        $attemptedQuizIds[] = intval($row['quiz_id']);
    }
    $attemptQuery->close();
}

// --- FETCH STUDY MATERIALS / LECTURE SLIDES FROM DATABASE ---
$studyMaterialsList = [];
$materialsQuery = "SELECT * FROM study_materials ORDER BY id DESC";
$materialsResult = $conn->query($materialsQuery);

if ($materialsResult) {
    while ($row = $materialsResult->fetch_assoc()) {
        $studyMaterialsList[] = [
            "id" => $row['id'],
            "title" => $row['title'],
            "subject" => $row['subject'],
            "class" => $row['class'],
            "type" => $row['type'],
            "desc" => $row['description'],
            "uploadedBy" => $row['uploaded_by'],
            "date" => date("d M Y", strtotime($row['upload_date'])),
            "file" => $row['file_path']
        ];
    }
}

// --- SECURE BACKEND FETCH: STUDENT PERSONAL QUIZ RESULTS ---
$personalResultsList = [];
$resultsQuery = $conn->prepare("
    SELECT r.*, q.title AS quiz_title, q.subject AS quiz_subject 
    FROM results r 
    LEFT JOIN quizzes q ON r.quiz_id = q.id 
    WHERE r.student_id = ? 
    ORDER BY r.submitted_at DESC
");
if ($resultsQuery) {
    $resultsQuery->bind_param("i", $student_id);
    $resultsQuery->execute();
    $resultsResult = $resultsQuery->get_result();
    while ($row = $resultsResult->fetch_assoc()) {
        $personalResultsList[] = [
            "id" => $row['id'],
            "quiz_title" => $row['quiz_title'] ?? ('Quiz #' . $row['quiz_id']),
            "subject" => $row['quiz_subject'] ?? 'General',
            "score" => $row['score'],
            "total_points" => $row['total_points'],
            "percentage" => $row['percentage']
        ];
    }
    $resultsQuery->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuizVerse - Student Portal Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="student.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>

<body data-theme="light" data-text="medium">

    <div class="overlay" id="overlay"></div>

    <aside class="sidebar" id="sidebar">
         <div class="close-btn" onclick="toggleSidebar()">
        <i class="fa fa-times"></i>
    </div>
        <div class="sidebar-header">
            <div class="logo-box">
                 <img src="quizverse-logo.png" alt="QuizVerse Logo" onerror="this.style.display='none'">
            </div>
            <h2>QuizVerse</h2>
            
        
        </div>

        <div class="student-profile">
            <div class="avatar">
                <i class="fa-solid fa-user"></i>
            </div>
            <div>
                <h4><?= htmlspecialchars($student_name) ?></h4>
                <span>Student</span>
            </div>
        </div>

        <nav class="sidebar-nav">
            
        <a href="#" class="nav-item active">
                <i class="fa-solid fa-house"></i>
                Dashboard
            </a>

            <a href="#slides" class="nav-item">
                <i class="fa-solid fa-file-powerpoint"></i>
                Lectures / Slides
            </a>
            <a href="#results" class="nav-item">
                <i class="fa-solid fa-square-poll-vertical"></i>
                Results
            </a>

            <a href="#performance" class="nav-item">
    <i class="fa-solid fa-chart-pie"></i>
    Performance
</a>
            <a href="logout.php" class="nav-item logout">
                <i class="fa-solid fa-right-from-bracket"></i>
                Logout
            </a>
        </nav>
    </aside>

    <main class="main">

        <header class="topbar">
        <div class="topbar-left">
         <div class="menu-toggler" onclick="toggleSidebar()">
    <i class="fa fa-bars"></i>
</div> 
          <h2 id="pageTitle" style="color: white">Dashboard</h2>

          <nav class="topbar-nav-links">
            <a href="teacher.php" class="simple-nav-icon" title="Home">
              <svg
                viewBox="0 0 24 24"
                width="20"
                height="20"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              >
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
              </svg>
            </a>
            <a
              href="about-contact.php"
              class="simple-nav-icon"
              title="Contact Us"
            >
              <svg
                viewBox="0 0 24 24"
                width="20"
                height="20"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              >
                <path
                  d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"
                ></path>
                <polyline points="22,6 12,13 2,6"></polyline>
              </svg>
            </a>

            <div class="dropdown">
              <a
                href="#"
                class="simple-nav-icon"
                id="themeBtn"
                title="Change Theme"
              >
                <i class="fa-regular fa-moon" style="font-size: 20px"></i>
              </a>
              <div class="dropdown-menu" id="themeMenu">
                <button onclick="setTheme('light')">
                  <i class="fa-solid fa-sun"></i> Light Default
                </button>
                <button onclick="setTheme('dark')">
                  <i class="fa-solid fa-moon"></i> Dark Onyx
                </button>
                <button onclick="setTheme('blue')">
                  <i class="fa-solid fa-droplet"></i> Blue Fluorescent
                </button>
              </div>
            </div>

            <div class="dropdown">
              <a
                href="#"
                class="simple-nav-icon"
                id="textBtn"
                title="Text Size Scaling"
              >
                <i class="fa-solid fa-text-height" style="font-size: 19px"></i>
              </a>
              <div class="dropdown-menu" id="textMenu">
                <button onclick="setTextSize('small')">A- Small Scale</button>
                <button onclick="setTextSize('medium')">
                  A Default Medium
                </button>
                <button onclick="setTextSize('large')">A+ Large Scale</button>
              </div>
            </div>
          </nav>
        </div>
      </header>
        <!-- ================= MAIN DASHBOARD SECTION ================= -->
        <section class="section active" id="dashboardSection">
            <div class="welcome-banner">
                <div>
                    <h2>Welcome Back 👋</h2>
                    <p>Continue your learning journey and track your progress.</p>
                </div>
                <div class="banner-icon">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>
            </div>

            <div class="student-quiz-container">
                <div class="portal-section-header">
                    <h2 id="sectionTitleHeader"><i class="fa-solid fa-graduation-cap"></i> Available Subjects</h2><br>
                    <p id="sectionDescHeader">Welcome, <?= htmlspecialchars($student_name) ?>! Select a subject box to reveal its published quizzes.</p>
                    
                    <button id="backDirectoryBtn" class="btn-back">
                        <i class="fa-solid fa-arrow-left"></i> Back to Subjects
                    </button>
                </div>

                <div id="subjectBoxContainer" class="subject-box-grid">
                    <?php if (empty($groupedQuizzes)): ?>
                        <div style="grid-column: 1/-1; text-align: center; padding: 50px 20px; color: var(--color-text-secondary);">
                            <i class="fa-solid fa-folder-open" style="font-size: 48px; margin-bottom: 14px; opacity: 0.5;"></i>
                            <h3>No Live Quizzes Available</h3>
                            <p style="margin-top: 6px;">There are currently no published assessment question sheets assigned to your portal.</p>
                        </div>
                    <?php else: ?>
                        <?php 
                        $iconMap = [
                            "computer science" => "fa-solid fa-laptop-code",
                            "mathematics" => "fa-solid fa-calculator",
                            "physics" => "fa-solid fa-atom",
                            "chemistry" => "fa-solid fa-flask",
                            "botany" => "fa-solid fa-seedling",
                            "english" => "fa-solid fa-book",
                            "general" => "fa-solid fa-folder-open"
                        ];
                        
                        foreach ($groupedQuizzes as $subjectName => $quizzes): 
                            $count = count($quizzes);
                            $displayIcon = $iconMap[$subjectName] ?? $iconMap["general"];
                        ?>
                            <div class="subject-folder-box" onclick="openSubjectDirectory('<?= addslashes($subjectName) ?>')">
                                <div class="subject-folder-icon">
                                    <i class="<?= $displayIcon ?>"></i>
                                </div>
                                <h3><?= htmlspecialchars($subjectName) ?></h3>
                                <span class="quiz-counter-tag"><?= $count ?> <?= $count === 1 ? 'Quiz Available' : 'Quizzes Available' ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div id="innerQuizCardsContainer" class="quiz-card-grid" style="display: none;"></div>
            </div>
        </section>

        <!-- ================= LECTURES / SLIDES TEXT SECTION (MATCHES IMAGE_899000.PNG) ================= -->
        <section class="section" id="slidesSection" style="display: none;">
            <div class="portalsectionheader">
                <h2><i class="fa-solid fa-file-invoice"></i> Lectures & Resource Materials</h2>
                <p>Access presentation slides, and manuals uploaded by your instructors.</p>
            </div>

            <div class="minimal-text-resource-grid">
                <?php if (empty($studyMaterialsList)): ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 40px 0; color: var(--color-text-secondary);">
                        <i class="fa-solid fa-box-open" style="font-size: 40px; margin-bottom: 12px; opacity: 0.5;"></i>
                        <h3>No Materials Uploaded Yet</h3>
                        <p>Check back later once your teacher distributes lecture files.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($studyMaterialsList as $mat): ?>
                        <div class="minimal-text-item">
                            <div class="top-row">
                             <div class="material-icon">
                            <i class="fa-solid fa-file-powerpoint"></i>
                             </div>
                            <div class="meta-header-line">
                                <?= htmlspecialchars($mat['type']) ?>  <?= htmlspecialchars($mat['date']) ?>
                            </div>
                            </div>
                            <h3><?= htmlspecialchars($mat['title']) ?></h3>
                            <div class="details-line">
                                <b>Subject:</b> <?= htmlspecialchars($mat['subject']) ?> | <b>Class:</b> <?= htmlspecialchars($mat['class']) ?>
                            </div>
                            <?php if (!empty($mat['desc'])): ?>
                                <div class="desc-line"><?= htmlspecialchars($mat['desc']) ?></div>
                            <?php endif; ?>
                            <div class="link-line">
                                <?php if (!empty($mat['file'])): ?>
                                    <a href="<?= htmlspecialchars($mat['file']) ?>" target="_blank">
                                        <i class="fa-solid fa-download"></i> View / Download File
                                    </a>
                                <?php else: ?>
                                    <span style="color: #82756a;"><i class="fa-solid fa-circle-exclamation"></i> Reference Text Only</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- ================= PERSONAL RESULTS SECURE VIEW (MATCHES IMAGE_899445.PNG) ================= -->
        <section class="section" id="resultsSection" style="display: none;">
            <div class="portalsectionheader">
        <h2>
            <i class="fa-solid fa-chart-line"></i>
            Quiz Results
        </h2>
        <p>
            Check your quiz results, scores, and percentage achievements.
        </p>
    </div>
    <!-- FILTER BAR -->
<div class="results-filter-bar">

    <!-- Subject Filter (Dynamic) -->
    <select id="subjectFilter" onchange="filterResults()">
        <option value="all">All Subjects</option>
        <?php
        $uniqueSubjects = [];
        foreach ($personalResultsList as $res) {
            $uniqueSubjects[strtolower($res['subject'])] = true;
        }
        foreach ($uniqueSubjects as $sub => $v) {
            echo "<option value='$sub'>" . ucfirst($sub) . "</option>";
        }
        ?>
    </select>

    <!-- Performance Filter -->
    <select id="performanceFilter" onchange="filterResults()">
        <option value="all">All Results</option>
        <option value="high">High (80%+)</option>
        <option value="medium">Medium (50–79%)</option>
        <option value="low">Low (&lt;50%)</option>
    </select>

</div>
            <div class="quiz-table-wrap clean-minimal-box">
                <table class="minimal-data-table">
                    <thead>
                        <tr>
                            <th>Quiz Title</th>
                            <th>Subject Area</th>
                            <th>Obtained Score</th>
                            <th>Percentage Metric</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($personalResultsList)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: var(--color-text-secondary); padding: 40px 20px;">
                                    <i class="fa-solid fa-square-poll-vertical" style="font-size: 38px; margin-bottom: 10px; opacity: 0.4;"></i>
                                    <p>No recorded exam metrics found for your account.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($personalResultsList as $res): ?>
                                <tr>
                                    <td class="text-bold-title"><?= htmlspecialchars($res['quiz_title']) ?></td>
                                    <td style="text-transform: capitalize; color: #7a6f66;"><?= htmlspecialchars($res['subject']) ?></td>
                                    <td>
                                        <span class="score-fraction"><?= htmlspecialchars($res['score']) ?></span> 
                                        <span style="color: #82756a;">/ <?= htmlspecialchars($res['total_points']) ?> Pts</span>
                                    </td>
                                    <td class="percentage-metric-cell"><?= number_format($res['percentage'], 1) ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ================= PERFORMANCE SECTION ================= -->
<section class="section" id="performanceSection" style="display:none;">
    <div class="portalsectionheader">
        <h2><i class="fa-solid fa-chart-pie"></i> Performance Analytics</h2>
        <p>Your subject-wise and overall performance analysis</p>
    </div>

    <div class="performance-graphs">
    <div class="chart-box">
        <canvas id="subjectChart"></canvas>
    </div>

    <div class="chart-box">
        <canvas id="performanceChart"></canvas>
    </div>

    <div class="chart-box">
        <canvas id="progressChart"></canvas>
    </div>
</div>
</section>

    </main>

    <div class="toast" id="toast"></div>

<script>
    const localizedQuizRepository = <?= json_encode($groupedQuizzes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const completedQuizIdsList = <?= json_encode($attemptedQuizIds) ?>;
     const personalResults = <?= json_encode($personalResultsList) ?>;
    const subjectBoxContainer = document.getElementById("subjectBoxContainer");
    const innerQuizCardsContainer = document.getElementById("innerQuizCardsContainer");
    const backDirectoryBtn = document.getElementById("backDirectoryBtn");
    const sectionTitleHeader = document.getElementById("sectionTitleHeader");
    const sectionDescHeader = document.getElementById("sectionDescHeader");

    

    /* --- PORTAL NAVIGATION CONTROLLER SWAP LOGIC --- */
    const navLinksCollection = document.querySelectorAll(".sidebar-nav .nav-item");
    const dashboardViewSection = document.getElementById("dashboardSection"); 
    const slidesViewSection = document.getElementById("slidesSection");
    const resultsViewSection = document.getElementById("resultsSection");
    const performanceViewSection =
    document.getElementById("performanceSection");

    navLinksCollection.forEach(link => {
        link.addEventListener("click", function(e) {
            const targetHref = this.getAttribute("href");
            
            dashboardViewSection.style.setProperty("display", "none", "important");
            slidesViewSection.style.setProperty("display", "none", "important");
            resultsViewSection.style.setProperty("display", "none", "important");
            performanceViewSection.style.setProperty(
    "display",
    "none",
    "important"
);
            navLinksCollection.forEach(n => n.classList.remove("active"));
            this.classList.add("active");

            if (targetHref === "#slides") {
                e.preventDefault();
                slidesViewSection.style.setProperty("display", "block", "important");
                document.getElementById("pageTitle").textContent = "Lectures / Slides";
                closeMenu();
            } else if (targetHref === "#results") {
                e.preventDefault();
                resultsViewSection.style.setProperty("display", "block", "important");
                document.getElementById("pageTitle").textContent = "Results";
                closeMenu();
            } 
            else if (targetHref === "#performance") {
    e.preventDefault();

    performanceViewSection.style.setProperty(
        "display",
        "block",
        "important"
    );

    document.getElementById("pageTitle").textContent =
        "Performance Analytics";

    setTimeout(() => {
        buildPerformanceGraphs(personalResults);
    }, 200);

    closeMenu();
}else if (targetHref === "#" || targetHref === "") {
                e.preventDefault();
                dashboardViewSection.style.setProperty("display", "block", "important");
                document.getElementById("pageTitle").textContent = "Dashboard";
                closeMenu();
            }
        });
    });

    /* --- ACCESSIBILITY AND INTERACTION UTILITIES --- */
    function setTheme(theme) {
        document.body.setAttribute("data-theme", theme);
        localStorage.setItem("theme", theme);
    }

    function setTextSize(size) {
        document.body.setAttribute("data-text", size);
        localStorage.setItem("textSize", size);
    }

    window.addEventListener("load", () => {
        const theme = localStorage.getItem("theme") || "light";
        const text = localStorage.getItem("textSize") || "medium";
        document.body.setAttribute("data-theme", theme);
        document.body.setAttribute("data-text", text);
    });

    document.getElementById("themeBtn").onclick = (e) => { e.stopPropagation(); document.getElementById("themeMenu").classList.toggle("show"); };
    document.getElementById("textBtn").onclick = (e) => { e.stopPropagation(); document.getElementById("textMenu").classList.toggle("show"); };

    document.addEventListener("click", function (e) {
        if (!e.target.closest(".dropdown")) {
            document.querySelectorAll(".dropdown-menu").forEach((m) => m.classList.remove("show"));
        }
    });

    /* --- SUBJECT FOLDER DIRECTORY SLIDER TRAVERSAL LOGIC --- */
    if (backDirectoryBtn) {
        backDirectoryBtn.addEventListener("click", () => {
            innerQuizCardsContainer.style.setProperty("display", "none", "important");
            backDirectoryBtn.style.setProperty("display", "none", "important");
            subjectBoxContainer.style.setProperty("display", "grid", "important");
            sectionTitleHeader.innerHTML = `<i class="fa-solid fa-graduation-cap"></i> Available Subjects`;
            sectionDescHeader.textContent = "Welcome, <?= htmlspecialchars($student_name) ?>! Select a subject box to reveal its published quizzes.";
        });
    }

    function openSubjectDirectory(subjectKey) {
        const quizCollection = localizedQuizRepository[subjectKey] || [];
        if (quizCollection.length === 0) return;

        subjectBoxContainer.style.setProperty("display", "none", "important");
        innerQuizCardsContainer.style.setProperty("display", "grid", "important");
        backDirectoryBtn.style.setProperty("display", "inline-flex", "important");

        sectionTitleHeader.innerHTML = `<i class="fa-solid fa-folder-open"></i> Subject Area: <span style="text-transform: capitalize; color: var(--color-accent-terracotta);">${subjectKey}</span>`;
        sectionDescHeader.textContent = "Select an active evaluation sheet block below to launch the assessment runner.";

        innerQuizCardsContainer.innerHTML = "";
        const dynamicGradients = ["linear-gradient(135deg, #4c5e3d, #6e8a57)", "linear-gradient(135deg, #a36344, #c48463)", "linear-gradient(135deg, #3d4c31, #5c734b)"];

        quizCollection.forEach((quiz, index) => {
            const assignedBg = dynamicGradients[index % dynamicGradients.length];
            const hasBeenAttempted = completedQuizIdsList.includes(parseInt(quiz.id));
            
            const cardNode = document.createElement("div");
            cardNode.className = `modern-gradient-card ${hasBeenAttempted ? 'card-marked-done' : ''}`;
            cardNode.innerHTML = `
                <div class="card-top-panel" style="background: ${assignedBg};">
                    <div class="header-text-group">
                        <h4>${escapeOutput(quiz.title)}</h4>
                        <p class="card-subtitle">${escapeOutput(quiz.subject || 'General')}</p>
                    </div>
                    ${hasBeenAttempted ? `<span class="completed-badge-indicator"><i class="fa-solid fa-circle-check"></i> Attempted</span>` : ''}
                </div>
                <div class="card-bottom-panel">
                    <div class="meta-row-item">
                        <i class="fa-solid fa-layer-group contextual-icon"></i>
                        <span class="meta-data-text">${quiz.questions_count || 0} Questions</span>
                    </div>
                    <div class="meta-row-item">
                        <i class="fa-solid fa-award contextual-icon"></i>
                        <span class="meta-data-text">Weight: <b>${quiz.total_points || 0} Pts</b></span>
                    </div>
                    <button class="btn-attempt" onclick="launchEvaluationModule(${quiz.id})">
                        <i class="fa-solid fa-play"></i> ${hasBeenAttempted ? 'Retake Quiz' : 'Attempt Quiz'}
                    </button>
                </div>
            `;
            innerQuizCardsContainer.appendChild(cardNode);
        });
    }

    function launchEvaluationModule(quizId) { window.location.href = `take-quiz.php?id=${quizId}`; }
    function escapeOutput(str) { return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;"); }
</script>
   <script src="student.js"></script>
    <footer
      style="
        margin-top: 80px;
        padding: 40px;
        text-align: center;
        color: var(--color-text-secondary);
        border-top: 1px solid var(--color-border);
      "
    >
      <p>&copy;  QuizVerse. All rights reserved.</p>
      <p style="font-size: 13px; margin-top: 8px">
        Modern online quiz management system.
      </p>
    </footer>
</body>
</html>