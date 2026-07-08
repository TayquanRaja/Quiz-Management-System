let users = [];
let deleteId = null;
let editId = null;

/* ================= POPUP ================= */
function showPopup(title, message) {
  document.getElementById("popupTitle").innerText = title;
  document.getElementById("popupMessage").innerText = message;
  document.getElementById("popup").style.display = "flex";
}

function closePopup() {
  document.getElementById("popup").style.display = "none";
}

/* ================= VALIDATION ================= */
function validateName(name) {
  return /^[A-Za-z\s]{3,}$/.test(name);
}

function validateEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

/* ================= FETCH USERS FROM DATABASE ================= */
async function loadUsers() {
  try {
    const response = await fetch("actions.php?action=fetch_all");
    const result = await response.json();
    if (result.success) {
      users = result.data;
      renderTable();
    } else {
      console.error(result.message);
    }
  } catch (error) {
    console.error("Error connecting to backend API:", error);
  }
}

/* ================= ADD USER ================= */
async function addUser() {
  let name = document.getElementById("name").value.trim();
  let email = document.getElementById("email").value.trim();
  let role = document.getElementById("role").value;
  let security_question = document.getElementById("securityQuestion").value;
  let security_answer = document.getElementById("securityAnswer").value.trim();

  if (!name || !email || !security_answer) {
    showPopup(
      "Error",
      "All fields are required, including the security answer.",
    );
    return;
  }

  if (!validateName(name)) {
    showPopup("Invalid Name", "Name must be at least 3 letters");
    return;
  }

  if (!validateEmail(email)) {
    showPopup("Invalid Email", "Enter correct email format");
    return;
  }

  try {
    const response = await fetch("actions.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        action: "add",
        name,
        email,
        role,
        security_question,
        security_answer,
      }),
    });
    const result = await response.json();

    if (result.success) {
      document.getElementById("name").value = "";
      document.getElementById("email").value = "";
      document.getElementById("securityAnswer").value = "";
      loadUsers();
      showPopup("Success", result.message);
    } else {
      showPopup("Error", result.message);
    }
  } catch (error) {
    showPopup("Error", "Could not connect to backend server.");
  }
}

/* ================= RENDER TABLE ================= */
function renderTable() {
  let table = document.getElementById("userTable");
  table.innerHTML = "";

  if (users.length === 0) {
    table.innerHTML = `<tr><td colspan="7" style="text-align:center; color: var(--color-text-secondary); padding: 30px;">No records available. Add users above.</td></tr>`;
    document.getElementById("userCount").innerText = "0";
    return;
  }

  users.forEach((u) => {
    let roleBadge =
      u.role === "admin"
        ? "[Admin]"
        : u.role === "teacher"
          ? "[Teacher]"
          : "[Student]";

    let displayQuestion = u.security_question
      ? u.security_question
      : "None Set";
    let displayAnswer = u.security_answer ? u.security_answer : "None Set";

    table.innerHTML += `
        <tr>
            <td>${u.id}</td>
            <td>${escapeHtml(u.full_name || u.name || "")}</td>
            <td>${escapeHtml(u.email || "")}</td>
            <td><small style="color: var(--color-accent-terracotta); font-weight:600; font-size:12px;">${roleBadge}</small></td>
            <td>${escapeHtml(displayQuestion)}</td>
            <td>${escapeHtml(displayAnswer)}</td>
            <td>
                <button onclick="openEdit(${u.id})" title="Edit User">
                    <i class="fa-solid fa-user-pen"></i>
                </button>
                <button class="danger" onclick="openDelete(${u.id})" title="Delete User">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </td>
        </tr>`;
  });

  document.getElementById("userCount").innerText = users.length;
}

/* ================= EDIT MODAL OPEN ================= */
function openEdit(dbId) {
  editId = dbId;

  // Find the user data directly from our client state array
  let targetUser = users.find((u) => u.id == dbId);

  if (!targetUser) {
    showPopup("Error", "User data context could not be located.");
    return;
  }

  // Populate data inputs using clean unescaped properties
  let nameValue = targetUser.full_name || targetUser.name || "";
  let emailValue = targetUser.email || "";
  let roleValue = targetUser.role || "student";
  let questionValue =
    targetUser.security_question || "What is your pet's name?";
  let answerValue = targetUser.security_answer || "";

  document.getElementById("editName").value = nameValue;
  document.getElementById("editEmail").value = emailValue;
  document.getElementById("editRole").value = roleValue.toLowerCase();
  document.getElementById("editSecurityQuestion").value = questionValue;
  document.getElementById("editSecurityAnswer").value =
    answerValue === "None Set" ? "" : answerValue;

  // Render modal box on-screen
  document.getElementById("editModal").style.display = "flex";
}

/* ================= SAVE CHANGES ================= */
async function saveUser() {
  let updatedName = document.getElementById("editName").value.trim();
  let updatedEmail = document.getElementById("editEmail").value.trim();
  let updatedRole = document.getElementById("editRole").value;
  let updatedQuestion = document.getElementById("editSecurityQuestion").value;
  let updatedAnswer = document
    .getElementById("editSecurityAnswer")
    .value.trim();

  if (!updatedName || !updatedEmail || !updatedAnswer) {
    showPopup("Error", "Fields cannot be blank.");
    return;
  }

  try {
    const response = await fetch("actions.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        action: "update",
        id: editId,
        name: updatedName,
        email: updatedEmail,
        role: updatedRole,
        security_question: updatedQuestion,
        security_answer: updatedAnswer,
      }),
    });
    const result = await response.json();

    if (result.success) {
      document.getElementById("editModal").style.display = "none";
      loadUsers();
      showPopup("Updated", result.message);
    } else {
      showPopup("Error", result.message);
    }
  } catch (error) {
    showPopup("Error", "Could not persist database modifications.");
  }
}

function closeEdit() {
  document.getElementById("editModal").style.display = "none";
}

/* ================= DELETE ================= */
function openDelete(dbId) {
  deleteId = dbId;
  document.getElementById("confirmModal").style.display = "flex";
}

async function confirmDelete() {
  try {
    const response = await fetch("actions.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "delete", id: deleteId }),
    });
    const result = await response.json();

    if (result.success) {
      closeConfirm();
      loadUsers();
      showPopup("Deleted", result.message);
    } else {
      showPopup("Error", result.message);
    }
  } catch (error) {
    showPopup("Error", "Could not complete delete execution logic.");
  }
}

function closeConfirm() {
  document.getElementById("confirmModal").style.display = "none";
}

function escapeHtml(str) {
  if (!str) return "";
  return str
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

/* ================= INITIALIZATION & CORE EVENTS ================= */
document.addEventListener("DOMContentLoaded", function () {
  const themeBtn = document.getElementById("themeBtn");
  const themeMenu = document.getElementById("themeMenu");
  const textBtn = document.getElementById("textBtn");
  const textMenu = document.getElementById("textMenu");

  if (themeBtn && themeMenu) {
    themeBtn.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      themeMenu.classList.toggle("show");
      if (textMenu) textMenu.classList.remove("show");
    });
  }

  if (textBtn && textMenu) {
    textBtn.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      textMenu.classList.toggle("show");
      if (themeMenu) themeMenu.classList.remove("show");
    });
  }

  document.addEventListener("click", function (e) {
    if (!e.target.closest(".header-dropdown-container")) {
      if (themeMenu) themeMenu.classList.remove("show");
      if (textMenu) textMenu.classList.remove("show");
    }
  });

  loadUsers();
});

function setTheme(themeName) {
  document.body.setAttribute("data-theme", themeName);
  localStorage.setItem("quizverse-theme", themeName);
}

function setTextSize(sizeName) {
  document.body.setAttribute("data-text", sizeName);
  localStorage.setItem("quizverse-text", sizeName);
}

(function () {
  const savedTheme = localStorage.getItem("quizverse-theme") || "light";
  const savedText = localStorage.getItem("quizverse-text") || "medium";
  document.body.setAttribute("data-theme", savedTheme);
  document.body.setAttribute("data-text", savedText);
})();
