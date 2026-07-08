document.addEventListener("DOMContentLoaded", function () {
  const textBtn = document.getElementById("textBtn");
  const textMenu = document.getElementById("textMenu");
  const themeBtn = document.getElementById("themeBtn");
  const themeMenu = document.getElementById("themeMenu");

  if (textBtn && textMenu) {
    textBtn.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      textMenu.classList.toggle("show");
      if (themeMenu) themeMenu.classList.remove("show"); // Close theme if open
    });
  }

  if (themeBtn && themeMenu) {
    themeBtn.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      themeMenu.classList.toggle("show");
      if (textMenu) textMenu.classList.remove("show"); // Close text if open
    });
  }

  // Close menus when clicking anywhere else on the page
  document.addEventListener("click", function () {
    if (themeMenu) themeMenu.classList.remove("show");
    if (textMenu) textMenu.classList.remove("show");
  });
});

// Text size scaling functions mapping to body attributes
function setTextSize(sizeName) {
  document.body.setAttribute("data-text", sizeName);
  localStorage.setItem("quizverse-text", sizeName);
}

function setTheme(themeName) {
  document.body.setAttribute("data-theme", themeName);
  localStorage.setItem("quizverse-theme", themeName);
}

// Automatically apply saved preference on load
(function loadSavedPrefs() {
  const savedText = localStorage.getItem("quizverse-text") || "medium";
  const savedTheme = localStorage.getItem("quizverse-theme") || "light";
  document.body.setAttribute("data-text", savedText);
  document.body.setAttribute("data-theme", savedTheme);
})();
