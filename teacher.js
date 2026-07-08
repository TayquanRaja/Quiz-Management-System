// teacher.js  ── Full localStorage + PHP hybrid
// ================================================================
//  HOW IT WORKS:
//  1. Every action FIRST calls teacher.php (real backend).
//  2. On success, PHP response is ALSO mirrored into localStorage
//     so other portals (student, quiz) can read it instantly.
//  3. If PHP is unavailable (team member hasn't set it up yet),
//     it automatically falls back to localStorage only.
//
//  localStorage KEY CONTRACT (share with your whole team):
//  ─────────────────────────────────────────────────────────────
//  "loggedInUser"  → { name, email, role }
//  "quizzes"       → [{ id, title, subject, class, status, ... }]
//  "questions"     → [{ id, quizId, quizTitle, type, question, options, correct, marks }]
//  "materials"     → [{ id, title, subject, class, type, desc, date }]
//  "results"       → [{ id, studentName, quizId, quizTitle, score, total, percentage, date, published }]
//  "students"      → [{ name, email, class }]
//  "feedbacks"     → [{ id, fromTeacher, toStudent, quiz, message, rating, type, date }]
// ================================================================

const API = "teacher.php"; // change path if needed, e.g. "api/teacher.php"

// ─── low-level helpers ──────────────────────────────────────────
function ls(key) {
  try {
    return JSON.parse(localStorage.getItem(key)) || [];
  } catch {
    return [];
  }
}
function lsObj(key) {
  try {
    return JSON.parse(localStorage.getItem(key)) || null;
  } catch {
    return null;
  }
}
function lsSet(key, val) {
  localStorage.setItem(key, JSON.stringify(val));
}

function sanitize(v) {
  return String(v)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}
function today() {
  return new Date().toLocaleDateString("en-GB", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}
function uid() {
  return Date.now() + Math.floor(Math.random() * 1000);
}

// ─── PHP API call (with localStorage fallback) ──────────────────
async function api(action, body = {}) {
  try {
    const res = await fetch(`${API}?action=${action}`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "same-origin",
      body: JSON.stringify(body),
    });
    const json = await res.json();
    if (!json.success) throw new Error(json.message || "API error");
    return { ok: true, data: json.data };
  } catch (e) {
    console.warn(
      `[teacher.js] PHP unavailable for "${action}", using localStorage. (${e.message})`,
    );
    return { ok: false, data: null };
  }
}
//theme
// dropdown toggle
document.getElementById("themeBtn").onclick = () => {
  document.getElementById("themeMenu").classList.toggle("show");
};

document.getElementById("textBtn").onclick = () => {
  document.getElementById("textMenu").classList.toggle("show");
};

// THEME SYSTEM
function setTheme(theme) {
  document.body.setAttribute("data-theme", theme);
  localStorage.setItem("theme", theme);
}

// TEXT SIZE SYSTEM
function setTextSize(size) {
  document.body.setAttribute("data-text", size);
  localStorage.setItem("textSize", size);
}

// LOAD SAVED SETTINGS
window.addEventListener("load", () => {
  const theme = localStorage.getItem("theme") || "light";
  const text = localStorage.getItem("textSize") || "medium";

  document.body.setAttribute("data-theme", theme);
  document.body.setAttribute("data-text", text);
});
// close dropdown when clicking outside
document.addEventListener("click", function (e) {
  if (!e.target.closest(".dropdown")) {
    document
      .querySelectorAll(".dropdown-menu")
      .forEach((m) => m.classList.remove("show"));
  }
});
// ================================================================
//  AUTH GUARD
//  Priority 1: PHP session  (when backend is running)
//  Priority 2: localStorage (when login member has saved user)
//  Priority 3: DEV BYPASS   (when neither exists yet, for testing)
//
//  Set DEV_BYPASS = false once your login page is working.
// ================================================================
// ================================================================
//  AUTH GUARD
// ================================================================
const DEV_BYPASS = false;

let currentUser = null;

// =========================================================================
//  AUTH GUARD REPAIR
// =========================================================================
async function initAuth() {
  // 1. Check live session status from backend
  const r = await api("get_user");
  if (r.ok && r.data) {
    currentUser = r.data;
    lsSet("loggedInUser", currentUser);
  }

  // 2. Read from local persistence if backend script check skipped
  if (!currentUser) {
    currentUser = lsObj("loggedInUser");
  }

  // 3. Strict authentication restriction check
  if (!currentUser || currentUser.role !== "teacher") {
    window.location.href = "register.html";
    return;
  }

  // REMOVED THE FORCED currentUser.id = 1 OVERRIDE ENTIRELY!

  document.getElementById("teacherName").textContent = currentUser.name;
  document.getElementById("bannerName").textContent = currentUser.name;
}

// ================================================================
//  SIDEBAR  &  NAVIGATION
// ================================================================
const sidebar = document.getElementById("sidebar");
const overlay = document.getElementById("overlay");
const menuBtn = document.getElementById("menuBtn");
const sidebarClose = document.getElementById("sidebarClose");
menuBtn.addEventListener("click", () => {
  sidebar.classList.toggle("collapsed");
  document.getElementById("main").classList.toggle("collapsed");
});
sidebarClose.addEventListener("click", closeSidebar);
overlay.addEventListener("click", closeSidebar);
function closeSidebar() {
  sidebar.classList.remove("open");
  overlay.classList.remove("show");
}

const navItems = document.querySelectorAll(".nav-item[data-section]");
const sections = document.querySelectorAll(".section");
const pageTitle = document.getElementById("pageTitle");
const sectionTitles = {
  dashboard: "Dashboard",
  "create-quiz": "Create Quiz",
  "manage-questions": "Manage Questions",
  "study-material": "Study Material",
};

navItems.forEach((item) => {
  item.addEventListener("click", (e) => {
    e.preventDefault();
    const target = item.dataset.section;

    // --- REMOVE OR COMMENT OUT THIS INTERCEPT BLOCK ---
    // if (target === "create-quiz") {
    //     window.location.href = "quiz.php";
    //     return;
    // }
    // --------------------------------------------------

    navItems.forEach((n) => n.classList.remove("active"));
    item.classList.add("active");

    sections.forEach((s) => s.classList.remove("active"));
    document.getElementById(target).classList.add("active");

    pageTitle.textContent = sectionTitles[target] || "";
    closeSidebar();

    renderMap[target] && renderMap[target]();
  });
});

// logout
document
  .querySelector(".nav-item.logout")
  ?.addEventListener("click", async (e) => {
    e.preventDefault();
    await api("logout"); // Explicitly kills the server session
    localStorage.removeItem("loggedInUser");
    localStorage.removeItem("quizzes"); // Sweeps old quiz cache clear
    window.location.href = "logout.";
  });
// ================================================================
//  TOAST
// ================================================================
function showToast(msg) {
  const t = document.getElementById("toast");
  t.textContent = msg;
  t.classList.add("show");
  setTimeout(() => t.classList.remove("show"), 3000);
}
//stats
fetch("teacher.php?action=get_stats")
  .then((res) => res.json())
  .then((data) => {
    if (data.success) {
      document.getElementById("statQuizzes").innerText = data.data.quizzes;
    }
  });
// ================================================================
//  DASHBOARD
// ================================================================
// --- FIX: FORCE REAL DATABASE DATA INTO PORTAL CACHES ---
async function renderDashboard() {
  // 1. Fetch live tables from teacher.php endpoints
  const [qR, mR, fbR, stuR, resR] = await Promise.all([
    api("get_quizzes"),
    api("get_materials"),
  ]);

  // 2. CRITICAL REPAIR: If database fetch succeeded, override localStorage immediately!
  const quizzes = qR.ok && qR.data ? qR.data : ls("quizzes");
  const materials = mR.ok && mR.data ? mR.data : ls("materials");

  // Save the true database snapshot where the student portal can instantly find it
  lsSet("quizzes", quizzes);
  lsSet("materials", materials);

  // Re-populate system display stats counters
  document.getElementById("statQuizzes").textContent = quizzes.length;

  document.getElementById("statMaterials").textContent = materials.length;

  // Render recent quizzes list view panel
  const ul = document.getElementById("recentQuizList");
  if (ul) {
    const colors = ["orange", "purple", "green", "pink"];
    ul.innerHTML = "";
    if (!quizzes.length) {
      ul.innerHTML = "<li style='color:#aaa'>No quizzes found in database</li>";
    } else {
      [...quizzes]
        .reverse()
        .slice(0, 5)
        .forEach((q, i) => {
          const li = document.createElement("li");
          li.innerHTML = `<span class="dot ${colors[i % 4]}"></span>
                  ${sanitize(q.title)}
                  <span class="badge-sm ${String(q.status).toLowerCase() === "draft" ? "draft" : ""}">${sanitize(q.status || "Active")}</span>`;
          ul.appendChild(li);
        });
    }
  }
}

// ================================================================
//  OPEN QUIZ MODULE
// ================================================================
document.getElementById("openQuizModule")?.addEventListener("click", () => {
  window.location.href = "quiz.php";
});

// ================================================================
//  RENDER MAP
// ================================================================
// ================================================================
//  RENDER MAP
// ================================================================
const renderMap = {
  dashboard: renderDashboard,

  // UPDATED: Now dynamically draws items inside your new Quiz Dashboard surface!
  "create-quiz": () => {
    renderQuizDashboardList();
  },

  "manage-questions": async () => {
    renderQuestionTable();
  },
  "study-material": renderMaterials,
};

// ================= LOGIN SUCCESS MESSAGE =================
window.addEventListener("DOMContentLoaded", () => {
  console.log("teacher.js loaded");

  const msg = localStorage.getItem("loginSuccess");

  console.log("MSG:", msg);

  if (msg) {
    showToast(msg);
    localStorage.removeItem("loginSuccess");
  }
});

function renderQuizDashboardList() {
  const quizzes = ls("quizzes");
  const displaySurface = document.querySelector(".quiz-dashboard-surface");

  if (!displaySurface) return;

  if (quizzes.length === 0) {
    displaySurface.innerHTML = `
            <div class="empty-quiz-state">
                <a href="quiz.php" class="centered-add-btn" title="Forge New Quiz"><i class="fa-solid fa-plus"></i></a>
                <h3>No Quizzes Created Yet</h3>
                <p>Click the plus button above to launch the Quiz Forge builder.</p>
            </div>`;
    return;
  }

  const dynamicGradients = [
    "linear-gradient(135deg, #4c5e3d, #6e8a57)",
    "linear-gradient(135deg, #a36344, #c48463)",
    "linear-gradient(135deg, #3d4c31, #5c734b)",
    "linear-gradient(135deg, #b87d65, #d4a373)",
  ];

  let gridHTML = `
        <div style="width: 100%; display: flex; flex-direction: column; gap: 25px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h3 style="color: var(--color-text-primary); margin:0; font-weight: 600;">Active Question Sheets</h3>
                <a href="quiz.php?action=new_quiz" class="btn-primary" style="padding: 10px 20px; font-size:13px; text-decoration:none; border-radius:10px;"><i class="fa-solid fa-plus"></i> Add Another Quiz</a>
            </div>
            <div class="quiz-card-grid">`;

  quizzes.forEach((quiz, index) => {
    const assignedGradient = dynamicGradients[index % dynamicGradients.length];
    const isPublished = String(quiz.status).toLowerCase() === "publish";

    gridHTML += `
            <div class="modern-gradient-card">
                <div class="card-top-panel" style="background: ${assignedGradient};">
                    <div class="header-text-group">
                        <h4>${sanitize(quiz.title)}</h4>
                        <p class="card-subtitle">${sanitize(quiz.subject || "Computer Science")}</p>
                    </div>
                    
                    <div class="card-top-right-actions">
                        ${
                          isPublished
                            ? `
                        <div class="card-pill-badge published">
                            <span class="badge-tick-circle"><i class="fa-solid fa-check"></i></span>
                            PUBLISHED
                        </div>
                        `
                            : ""
                        }

                        <div class="card-dropdown">
                            <button class="icon-card-action contextual-menu-btn" title="Options" onclick="toggleCardMenu(event, ${quiz.id})">
                                <i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>
                            <div class="dropdown-menu card-menu" id="cardMenu-${quiz.id}">
                                <button type="button" onclick="editQuiz(${quiz.id})"><i class="fa-solid fa-pen"></i>Edit</button>
                                
                                ${
                                  isPublished
                                    ? `
                                <button type="button" onclick="unpublishQuiz(${quiz.id})"><i class="fa-solid fa-eye-slash"></i>Unpublish</button>
                                `
                                    : `
                                <button type="button" onclick="publishQuiz(${quiz.id})"><i class="fa-solid fa-globe"></i>Publish</button>
                                `
                                }
                                
                                <button type="button" onclick="deleteQuiz(${quiz.id})" class="text-danger"><i class="fa-solid fa-trash"></i>Delete</button>
                            </div>
                        </div>
                    </div>
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
                </div>
            </div>`;
  });

  gridHTML += `</div></div>`;
  displaySurface.innerHTML = gridHTML;
}
// --- NEW ACTION HANDLERS FOR QUIZ CARDS ---
function toggleCardMenu(event, quizId) {
  event.stopPropagation();

  // Close any other open card menus first
  document.querySelectorAll(".card-menu").forEach((menu) => {
    if (menu.id !== `cardMenu-${quizId}`) {
      menu.classList.remove("show");
    }
  });

  const targetMenu = document.getElementById(`cardMenu-${quizId}`);
  if (targetMenu) {
    targetMenu.classList.toggle("show");
  }
}

// Keep track of which card index/id is actively under management
// Global tracking variable
let activeQuizIdTarget = null;

function editQuiz(id) {
  const quizzes = ls("quizzes");
  const targetQuiz = quizzes.find((q) => String(q.id) === String(id));
  if (!targetQuiz) return;

  activeQuizIdTarget = id;

  // Redirect to the quiz forge and pass the quiz ID through the URL parameters
  window.location.href = `quiz.php?edit=${id}`;
}

// =========================================================================
//  UNIFIED DELETE SYSTEM (OPEN MODAL, HIT BACKEND, ERASE FRONTEND)
// =========================================================================

function deleteQuiz(id) {
  // 1. Hide the three-dot dropdown menu right away
  document.querySelectorAll(".card-menu").forEach((menu) => {
    menu.classList.remove("show");
  });

  // 2. Set our global tracking anchor to the quiz ID we want to delete
  activeQuizIdTarget = id;

  // 3. Reveal the beautifully styled custom modal pop-up box
  const targetModal = document.getElementById("customModalOverlay");
  if (targetModal) {
    targetModal.classList.add("show");
  }
}

function closeCustomModal() {
  // Hide the custom modal pop-up box safely
  const targetModal = document.getElementById("customModalOverlay");
  if (targetModal) {
    targetModal.classList.remove("show");
  }
  // Clear out the tracking anchor so we don't accidentally delete something else later
  activeQuizIdTarget = null;
}

// THE WIRE FIX: This handles the click, purges the DB, and sweeps the UI grid card clean
document.addEventListener("DOMContentLoaded", () => {
  const confirmBtn = document.getElementById("confirmDeleteBtn");

  if (confirmBtn) {
    // Remove any previous click listeners to prevent double-firing bugs
    confirmBtn.replaceWith(confirmBtn.cloneNode(true));

    // Grab the fresh clean button node instance
    document
      .getElementById("confirmDeleteBtn")
      .addEventListener("click", async () => {
        if (!activeQuizIdTarget) return;

        const targetedId = activeQuizIdTarget;
        console.log(`Starting real-time deletion for Quiz ID: ${targetedId}`);

        try {
          // 1. Send request to teacher.php to drop rows from the MySQL database tables
          const response = await fetch(
            `teacher.php?action=delete_quiz&id=${targetedId}`,
            {
              method: "GET",
            },
          );

          if (!response.ok)
            throw new Error(`HTTP Error Code: ${response.status}`);

          const data = await response.json();

          // FIX: Clean comparison against boolean or string values from backend payload
          if (data.success === true || String(data.success) === "true") {
            // 2. Erase from LocalStorage arrays completely so other portal views stay synced
            let quizzes = [];
            try {
              quizzes = JSON.parse(localStorage.getItem("quizzes")) || [];
            } catch (e) {
              quizzes = [];
            }
            quizzes = quizzes.filter(
              (q) => String(q.id) !== String(targetedId),
            );
            localStorage.setItem("quizzes", quizzes);

            // 3. Re-fetch current database records to automatically adjust stats & clean dashboards
            const updateCheck = await fetch("teacher.php?action=get_quizzes");
            if (updateCheck.ok) {
              const freshData = await updateCheck.json();
              if (freshData.success) {
                localStorage.setItem("quizzes", JSON.stringify(freshData.data));
              }
            }

            // 4. UI Layer cleanup: Instantly rebuilds the grid views without reloading the page
            renderQuizDashboardList();
            renderDashboard();

            // 5. Close the modal frame smoothly
            closeCustomModal();
            showToast("🗑️ Quiz permanently deleted from backend server.");
          } else {
            alert(
              "❌ Database Transaction Rejected: " +
                (data.message || "Unknown error context."),
            );
          }
        } catch (err) {
          console.warn(
            "Server connection offline. Running fallback client-side storage cleanup...",
            err,
          );

          // Standalone Dev Fallback Environment Mode (So it works even without an active XAMPP/WAMP database)
          let quizzes = [];
          try {
            quizzes = JSON.parse(localStorage.getItem("quizzes")) || [];
          } catch (e) {
            quizzes = [];
          }
          quizzes = quizzes.filter((q) => String(q.id) !== String(targetedId));
          localStorage.setItem("quizzes", JSON.stringify(quizzes));

          renderQuizDashboardList();
          renderDashboard();
          closeCustomModal();
          showToast("⚠️ Server Offline. Changes forced locally.");
        }
      });
  }
});

// --- FIXED SERVER PUBLISHING HANDSHAKE WITH IMMEDIATE ACTION RE-RENDER ---
// --- FIXED SERVER PUBLISHING HANDSHAKE WITH IMMEDIATE ACTION RE-RENDER ---
async function publishQuiz(id) {
  const quizzes = ls("quizzes");
  const targetQuiz = quizzes.find((q) => String(q.id) === String(id));

  if (!targetQuiz) {
    showToast("❌ Unable to locate requested quiz profile records.");
    return;
  }

  // STRUCTURE THE PAYLOAD FOR THE BACKEND TRANSITION
  const publishPayload = {
    id: parseInt(targetQuiz.id, 10),
    title: targetQuiz.title,
    subject: targetQuiz.subject || "Computer Science",
    class: targetQuiz.class || "General",
    status: "Publish", // MODIFIED: Changed from 'Active' to 'Publish'
    total_points: parseInt(targetQuiz.total_points, 10) || 0,
    questions_count: parseInt(targetQuiz.questions_count, 10) || 0,
  };

  showToast("🚀 Publishing quiz to server...");

  try {
    // 1. Submit update payload directly to teacher.php handler route
    const response = await fetch("teacher.php?action=save_quiz", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(publishPayload),
    });

    if (!response.ok) throw new Error(`HTTP Error Status: ${response.status}`);
    const result = await response.json();

    // 2. Sync Local Storage State using the updated 'Publish' keyword
    const updatedQuizzes = quizzes.map((q) =>
      String(q.id) === String(id) ? { ...q, status: "Publish" } : q,
    );
    localStorage.setItem("quizzes", JSON.stringify(updatedQuizzes));

    // 3. Close open menu dropdowns
    document.querySelectorAll(".card-menu").forEach((menu) => {
      menu.classList.remove("show");
    });

    // 4. Force immediate UI visual updates
    renderQuizDashboardList();
    if (typeof renderDashboard === "function") {
      renderDashboard();
    }

    // 5. Present success notification popup
    showToast(`\u2705 Success! "${targetQuiz.title}" has been published.`);
  } catch (err) {
    console.warn(
      "Server connection error. Forcing offline local mirror update...",
      err,
    );

    const updatedQuizzes = quizzes.map((q) =>
      String(q.id) === String(id) ? { ...q, status: "Publish" } : q,
    );
    localStorage.setItem("quizzes", JSON.stringify(updatedQuizzes));

    document.querySelectorAll(".card-menu").forEach((menu) => {
      menu.classList.remove("show");
    });

    renderQuizDashboardList();
    if (typeof renderDashboard === "function") {
      renderDashboard();
    }
    showToast(
      `\u26A0\uFE0F Server offline. "${targetQuiz.title}" published locally.`,
    );
  }
}
async function unpublishQuiz(id) {
  const quizzes = ls("quizzes");
  const targetQuiz = quizzes.find((q) => String(q.id) === String(id));

  if (!targetQuiz) {
    showToast("❌ Unable to locate requested quiz profile records.");
    return;
  }

  // Structure payload reverting status back to Draft state
  const unpublishPayload = {
    id: parseInt(targetQuiz.id, 10),
    title: targetQuiz.title,
    subject: targetQuiz.subject || "Computer Science",
    class: targetQuiz.class || "General",
    status: "Draft",
    total_points: parseInt(targetQuiz.total_points, 10) || 0,
    questions_count: parseInt(targetQuiz.questions_count, 10) || 0,
  };

  showToast("🔒 Reverting quiz status to Draft...");

  try {
    // 1. Post transaction parameters over to database switchboard handlers
    const response = await fetch("teacher.php?action=save_quiz", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(unpublishPayload),
    });

    if (!response.ok) throw new Error(`HTTP Error Status: ${response.status}`);
    await response.json();

    // 2. Clear out view status variables locally
    const updatedQuizzes = quizzes.map((q) =>
      String(q.id) === String(id) ? { ...q, status: "Draft" } : q,
    );
    localStorage.setItem("quizzes", JSON.stringify(updatedQuizzes));

    // 3. Clear floating interactive popovers layout visibility tracking flags
    document.querySelectorAll(".card-menu").forEach((menu) => {
      menu.classList.remove("show");
    });

    // 4. Force synchronous re-renders across matching list surface spaces
    renderQuizDashboardList();
    if (typeof renderDashboard === "function") {
      renderDashboard();
    }

    showToast(`\u2705 "${targetQuiz.title}" has been unpublished.`);
  } catch (err) {
    console.warn(
      "Server connection error. Forcing offline local fallback change...",
      err,
    );

    const updatedQuizzes = quizzes.map((q) =>
      String(q.id) === String(id) ? { ...q, status: "Draft" } : q,
    );
    localStorage.setItem("quizzes", JSON.stringify(updatedQuizzes));

    document.querySelectorAll(".card-menu").forEach((menu) => {
      menu.classList.remove("show");
    });

    renderQuizDashboardList();
    if (typeof renderDashboard === "function") {
      renderDashboard();
    }
    showToast(`\u26A0\uFE0F Server offline. Changed to Draft locally.`);
  }
}
// ================================================================
//  BOOT
// ================================================================

// ================================================================
//  STUDY MATERIAL
// ================================================================
function handleFile(input) {
  if (input.files.length > 0)
    document.getElementById("fileName").textContent =
      "📎 " + input.files[0].name;
}

async function uploadMaterial() {
  const title = document.getElementById("matTitle").value.trim();
  const subject = document.getElementById("matSubject").value;
  const file = document.getElementById("fileInput").files[0];

  if (!title || !subject) {
    showToast("⚠️ Please fill required fields.");
    return;
  }

  // IMPORTANT: use FormData for file upload
  const formData = new FormData();

  formData.append("action", "save_material");
  formData.append("title", title);
  formData.append("subject", subject);
  formData.append("class", document.getElementById("matClass").value || "—");
  formData.append("type", document.getElementById("matType").value);
  formData.append("desc", document.getElementById("matDesc").value.trim());

  // 🔥 REAL FILE UPLOAD
  if (file) {
    formData.append("file", file);
  }

  try {
    const res = await fetch("teacher.php?action=save_material", {
      method: "POST",
      body: formData,
    });

    const json = await res.json();

    if (!json.success) throw new Error(json.message);

    lsSet("materials", json.data);

    renderMaterials(json.data);

    showToast("📤 File uploaded successfully!");
  } catch (e) {
    showToast("❌ Upload failed: " + e.message);
  }
}
async function renderMaterials(materials) {
  if (!materials) {
    const r = await api("get_materials");
    materials = r.ok ? r.data : ls("materials");
    lsSet("materials", materials);
  }

  const grid = document.getElementById("materialGrid");
  grid.innerHTML = "";

  // 🔥 newest first
  materials = materials.reverse();

  materials.forEach((m) => {
    const card = document.createElement("div");
    card.className = "material-card";

    card.innerHTML = `
      <div class="mat-top">
        <h4>${sanitize(m.title)}</h4>
        <span class="tag">${sanitize(m.type)}</span>
      </div>

      <p class="meta">${sanitize(m.subject)} • ${sanitize(m.class)}</p>

      <small>📅 ${m.date} ${m.uploadedBy ? "by " + sanitize(m.uploadedBy) : ""}</small>

      <div class="actions">
        ${m.file ? `<a href="${m.file}" target="_blank">📎 Open</a>` : ""}
        <button onclick="deleteMaterial(${m.id})">🗑 Delete</button>
      </div>
    `;

    grid.appendChild(card);
  });

  document.getElementById("statMaterials").textContent = materials.length;
}
async function deleteMaterial(id) {
  console.log("DELETE CLICKED ID:", id);

  showToast("⚠️ Deleting material...");

  try {
    const res = await fetch("teacher.php?action=delete_material", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ id: Number(id) }),
    });

    const json = await res.json();

    console.log("SERVER RESPONSE:", json);

    if (!json.success) {
      showToast(json.message);
      return;
    }

    // remove locally
    let mats = ls("materials") || [];
    mats = mats.filter((m) => String(m.id) !== String(id));
    lsSet("materials", mats);

    await renderMaterials(mats);
    await renderDashboard();

    showToast("🗑️ Deleted successfully");
  } catch (err) {
    console.error(err);
    showToast("❌ Request failed");
  }
}

// ================================================================
//  RENDER MAP
// ================================================================

// ================================================================
//  BOOT
// ================================================================
(async () => {
  await initAuth();
  await renderDashboard();
})();
