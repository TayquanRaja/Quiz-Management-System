// student.js - Handles Published Quiz Directories for Student Portals
function showToast(msg) {
  let t = document.getElementById("toast");

  // agar toast div nahi hai to create kar do
  if (!t) {
    t = document.createElement("div");
    t.id = "toast";
    document.body.appendChild(t);
  }

  t.textContent = msg;
  t.classList.add("show");

  setTimeout(() => {
    t.classList.remove("show");
  }, 3000);
}
document.addEventListener("DOMContentLoaded", () => {
  // LOGIN SUCCESS TOAST
  const msg = localStorage.getItem("loginSuccess");

  if (msg) {
    showToast(msg);
    localStorage.removeItem("loginSuccess");
  }

  // INIT PORTAL
  initStudentQuizPortal();
});

function initStudentQuizPortal() {
  // 1. Safe parsing engine retrieval from shared localStorage Key Contract
  let quizzes = [];
  try {
    quizzes = JSON.parse(localStorage.getItem("quizzes")) || [];
  } catch (e) {
    quizzes = [];
  }

  // 2. CRITICAL FILTER: Strictly show evaluation records whose status context is 'Publish'
  // Matches the updated teacher platform configuration rules
  const publishedQuizzes = quizzes.filter(
    (q) => String(q.status).toLowerCase() === "publish",
  );

  // 3. Group quizzes dynamically based on their respective subject values
  const groupedBySubject = {};
  publishedQuizzes.forEach((quiz) => {
    // Fallback sanitize rule if subject text area was bypassed
    const subjectKey =
      quiz.subject && quiz.subject.trim() !== ""
        ? quiz.subject.trim().toLowerCase()
        : "general";
    if (!groupedBySubject[subjectKey]) {
      groupedBySubject[subjectKey] = [];
    }
    groupedBySubject[subjectKey].push(quiz);
  });

  // Capture UI Control Mountpoints
  const subjectGridArea = document.getElementById("subjectGridArea");
  const quizListArea = document.getElementById("quizListArea");
  const backToSubjectsBtn = document.getElementById("backToSubjectsBtn");
  const quizSectionTitle = document.getElementById("quizSectionTitle");
  const quizSectionDesc = document.getElementById("quizSectionDesc");

  if (!subjectGridArea || !quizListArea || !backToSubjectsBtn) return;

  // Attach Event Handling for Directory Rollbacks
  backToSubjectsBtn.addEventListener("click", () => {
    quizListArea.style.display = "none";
    backToSubjectsBtn.style.display = "none";
    subjectGridArea.style.display = "grid";

    quizSectionTitle.innerHTML = `<i class="fa-solid fa-graduation-cap"></i> Available Assessments`;
    quizSectionDesc.textContent =
      "Select a subject area to explore your assigned evaluation sheets.";
  });

  // 4. Render Step: Rebuild the Subject Boxes Directory Layout
  if (Object.keys(groupedBySubject).length === 0) {
    subjectGridArea.innerHTML = `
            <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: var(--color-text-secondary);">
                <i class="fa-solid fa-folder-open" style="font-size: 40px; margin-bottom: 12px; color: var(--color-border);"></i>
                <h3>No Published Quizzes Found</h3>
                <p>Check back later once your teacher uploads live assessments.</p>
            </div>`;
    return;
  }

  subjectGridArea.innerHTML = "";

  // Icon index catalog to keep visual variety high across custom subject strings
  const subjectIcons = {
    "computer science": "fa-solid fa-laptop-code",
    mathematics: "fa-solid fa-calculator",
    physics: "fa-solid fa-atom",
    chemistry: "fa-solid fa-flask",
    botany: "fa-solid fa-seedling",
    english: "fa-solid fa-book",
    general: "fa-solid fa-folder-open",
  };

  Object.keys(groupedBySubject).forEach((subjectName) => {
    const quizCount = groupedBySubject[subjectName].length;
    const iconClass = subjectIcons[subjectName] || subjectIcons["general"];

    const folderCard = document.createElement("div");
    folderCard.className = "subject-folder-box";
    folderCard.innerHTML = `
            <div class="subject-folder-icon">
                <i class="${iconClass}"></i>
            </div>
            <h3>${subjectName}</h3>
            <span class="quiz-counter-tag">${quizCount} ${quizCount === 1 ? "Quiz Available" : "Quizzes Available"}</span>
        `;

    // 5. Interaction Setup: Reveal inner lists on box clicks
    folderCard.addEventListener("click", () => {
      renderQuizSubmenu(subjectName, groupedBySubject[subjectName]);
    });

    subjectGridArea.appendChild(folderCard);
  });

  // 6. Inline Submenu Render Engine Block
  function renderQuizSubmenu(subjectName, quizzesInSubject) {
    // Toggle view containers visibility
    subjectGridArea.style.display = "none";
    quizListArea.style.display = "grid";
    backToSubjectsBtn.style.display = "inline-flex";

    // Update titles to context
    quizSectionTitle.innerHTML = `<i class="fa-solid fa-folder-open"></i> Quizzes inside: <span style="text-transform: capitalize; color: var(--color-accent-terracotta);">${subjectName}</span>`;
    quizSectionDesc.textContent =
      "Select an evaluation to initialize the assessment runner canvas.";

    quizListArea.innerHTML = "";

    // Premium Gradient styling array modeled exactly from your manager panel updates
    const displayGradients = [
      "linear-gradient(135deg, #4c5e3d, #6e8a57)",
      "linear-gradient(135deg, #a36344, #c48463)",
      "linear-gradient(135deg, #3d4c31, #5c734b)",
    ];

    quizzesInSubject.forEach((quiz, idx) => {
      const assignedGradient = displayGradients[idx % displayGradients.length];
      const cardWrapper = document.createElement("div");
      cardWrapper.className = "modern-gradient-card";

      cardWrapper.innerHTML = `
                <div class="card-top-panel" style="background: ${assignedGradient}; min-height: 100px;">
                    <div class="header-text-group">
                        <h4 style="font-size: 17px !important;">${escapeStrings(quiz.title)}</h4>
                        <p class="card-subtitle">${escapeStrings(quiz.subject)}</p>
                    </div>
                </div>
                <div class="card-bottom-panel">
                    <div class="meta-row-item">
                        <i class="fa-solid fa-layer-group contextual-icon"></i>
                        <span class="meta-data-text">${quiz.questions_count || 0} Questions</span>
                    </div>
                    <div class="meta-row-item">
                        <i class="fa-solid fa-award contextual-icon"></i>
                        <span class="meta-data-text">Evaluation Weight: <b>${quiz.total_points || 0} Pts</b></span>
                    </div>
                    <div style="margin-top: 5px; width: 100%;">
                        <button class="btn-attempt-quiz" onclick="launchQuizAttempt(${quiz.id})">
                            <i class="fa-solid fa-play"></i> Attempt Quiz
                        </button>
                    </div>
                </div>
            `;
      quizListArea.appendChild(cardWrapper);
    });
  }
}

function buildPerformanceGraphs(results) {
  Object.values(Chart.instances).forEach((chart) => chart.destroy());
  // 1. Subject-wise average
  let subjectMap = {};
  let performanceRanges = { high: 0, medium: 0, low: 0 };

  results.forEach((r) => {
    let sub = r.subject.toLowerCase();
    let percent = parseFloat(r.percentage);

    // subject avg
    if (!subjectMap[sub]) {
      subjectMap[sub] = { total: 0, count: 0 };
    }
    subjectMap[sub].total += percent;
    subjectMap[sub].count++;

    // performance grouping
    if (percent >= 80) performanceRanges.high++;
    else if (percent >= 50) performanceRanges.medium++;
    else performanceRanges.low++;
  });

  let subjects = Object.keys(subjectMap);
  let avgScores = subjects.map(
    (s) => subjectMap[s].total / subjectMap[s].count,
  );

  // CALL CHARTS
  renderCharts(subjects, avgScores, performanceRanges);
}
// Global Launcher Handshake Helper
function launchQuizAttempt(quizId) {
  console.log(
    `Initializing execution canvas tracking framework for Quiz ID: ${quizId}`,
  );
  // Change coordinates to match your actual quiz layout interface engine file path
  window.location.href = `take-quiz.php?id=${quizId}`;
}

// Direct String Sanitizer (sanitization)
function escapeStrings(val) {
  return String(val)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}
function renderCharts(subjects, avgScores, performanceRanges) {
  // 1. Subject Chart
  new Chart(document.getElementById("subjectChart"), {
    type: "bar",
    data: {
      labels: subjects,
      datasets: [
        {
          label: "Average Score",
          data: avgScores,
        },
      ],
    },
  });

  // 2. Performance Pie Chart
  new Chart(document.getElementById("performanceChart"), {
    type: "pie",
    data: {
      labels: ["High", "Medium", "Low"],
      datasets: [
        {
          data: [
            performanceRanges.high,
            performanceRanges.medium,
            performanceRanges.low,
          ],
        },
      ],
    },
  });

  // 3. Quiz Progress Doughnut (Attempt vs Pending)
  function getQuizStats() {
    const total = Object.keys(localizedQuizRepository).reduce(
      (sum, key) => sum + localizedQuizRepository[key].length,
      0,
    );

    const attempted = completedQuizIdsList.length;
    const pending = total - attempted;

    return { total, attempted, pending };
  }

  const stats = getQuizStats();

  new Chart(document.getElementById("progressChart"), {
    type: "doughnut",
    data: {
      labels: ["Attempted", "Pending", "Total Quizzes"],
      datasets: [
        {
          data: [stats.attempted, stats.pending, stats.total],
        },
      ],
    },
    options: {
      plugins: {
        legend: {
          position: "bottom",
        },
      },
    },
  });
}
function filterResults() {
  let subject = document.getElementById("subjectFilter").value;
  let performance = document.getElementById("performanceFilter").value;

  let rows = document.querySelectorAll("#resultsSection tbody tr");

  rows.forEach((row) => {
    // Get cells safely
    let cells = row.getElementsByTagName("td");

    if (cells.length < 4) return; // skip empty row (like "no results")

    let rowSubject = cells[1].innerText.trim().toLowerCase();
    let percentageText = cells[3].innerText.replace("%", "").trim();
    let percentage = parseFloat(percentageText);

    // SUBJECT FILTER
    let subjectMatch = subject === "all" || rowSubject === subject;

    // PERFORMANCE FILTER
    let performanceMatch = true;

    if (performance === "high") {
      performanceMatch = percentage >= 80;
    } else if (performance === "medium") {
      performanceMatch = percentage >= 50 && percentage < 80;
    } else if (performance === "low") {
      performanceMatch = percentage < 50;
    }

    // FINAL DECISION
    if (subjectMatch && performanceMatch) {
      row.style.display = "";
    } else {
      row.style.display = "none";
    }
  });

  buildPerformanceGraphs(personalResults);
}
function toggleSidebar() {
  document.querySelector(".sidebar").classList.toggle("active");
}
