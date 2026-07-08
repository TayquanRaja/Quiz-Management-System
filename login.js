// ================= PASSWORD TOGGLE =================

const togglePassword = document.querySelector(".togglePassword");

if (togglePassword) {
  togglePassword.addEventListener("click", () => {
    const password = document.getElementById("password");

    if (password.type === "password") {
      password.type = "text";
      togglePassword.classList.add("fa-eye-slash");
      togglePassword.classList.remove("fa-eye");
    } else {
      password.type = "password";
      togglePassword.classList.add("fa-eye");
      togglePassword.classList.remove("fa-eye-slash");
    }
  });
}

function showToast(msg) {
  const t = document.getElementById("toast");

  t.textContent = msg;
  t.classList.add("show");

  setTimeout(() => {
    t.classList.remove("show");
  }, 3000);
}
// ================= LOGIN FORM =================

const loginForm = document.getElementById("loginForm");

loginForm.addEventListener("submit", function (e) {
  e.preventDefault();

  const email = document.getElementById("email");
  const password = document.getElementById("password");
  const role = document.getElementById("role");

  let isValid = true;

  document.querySelectorAll(".error").forEach((e) => (e.innerText = ""));

  if (email.value.trim() === "") {
    showError(email, "Email is required");
    isValid = false;
  }

  const passwordValue = password.value.trim();

  const passwordRegex =
    /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;

  if (passwordValue === "") {
    showError(password, "Password is required");
    isValid = false;
  } else if (!passwordRegex.test(passwordValue)) {
    showError(
      password,
      "Password must contain: 8+ characters, uppercase, lowercase, number and special character",
    );
    isValid = false;
  }
  if (role.value === "") {
    showError(role, "Please select role");
    isValid = false;
  }

  if (!isValid) return;

  const formData = new FormData();
  formData.append("email", email.value.trim());
  formData.append("password", password.value.trim());
  formData.append("role", role.value);

  fetch("login.php", {
    method: "POST",
    body: formData,
  })
    .then((res) => res.text())
    .then((data) => {
      const result = data.trim();
      console.log("LOGIN RESPONSE:", result);

      if (result === "teacher" || result === "student" || result === "admin") {
        // Clean slate preparation: Erase any historical caching variables
        localStorage.removeItem("quizzes");
        localStorage.removeItem("questions");

        // Generate a programmatic temporary state profile
        // It will update to the absolute source of truth upon hitting teacher.html -> initAuth()
        const initialUserPayload = {
          name: "Authenticated User",
          email: email.value.trim(),
          role: result,
        };
        localStorage.setItem(
          "loggedInUser",
          JSON.stringify(initialUserPayload),
        );
        localStorage.setItem(
          "loginSuccess",
          `You have successfully logged in to the ${result.toUpperCase()} Portal`,
        );

        if (result === "teacher") {
          window.location.href = "teacher.html";
        } else if (result === "student") {
          window.location.href = "student.php";
        } else {
          window.location.href = "Admin.php";
        }
      } else {
        showToast(result);
      }
    })
    .catch((err) => {
      console.log("LOGIN ERROR:", err);
      alert("Login failed. Please check server or PHP response.");
    });
});

// ================= ERROR =================

function showError(input, message) {
  const group = input.closest(".input-group");
  const error = group.querySelector(".error");
  if (error) error.innerText = message;
}
