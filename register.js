// register.js

// ================= SANITIZATION =================
const emailInput = document.getElementById("email");
const emailLiveError = document.querySelector(".email-live-error");
function sanitizeInput(value){

    return value
        .trim()
        .replace(/</g,"&lt;")
        .replace(/>/g,"&gt;")
        .replace(/"/g,"&quot;")
        .replace(/'/g,"&#039;");
}

// ================= PASSWORD SHOW/HIDE =================

const toggleButtons =
document.querySelectorAll(".togglePassword");

toggleButtons.forEach(button => {

    button.addEventListener("click", () => {

        const input =
        button.previousElementSibling;

        if(input.type === "password"){

            input.type = "text";

            button.classList.remove("fa-eye");
            button.classList.add("fa-eye-slash");

        }

        else{

            input.type = "password";

            button.classList.remove("fa-eye-slash");
            button.classList.add("fa-eye");

        }

    });

});
// ================= LIVE PASSWORD MESSAGE =================

const passwordInput =
document.getElementById("password");

const passwordLiveError =
document.querySelector(".password-live-error");

passwordInput.addEventListener("input", () => {

    const value =
    passwordInput.value;

    let message = "";

    if(value.length > 0){

        if(value.length < 8){

            message =
            "Password must contain at least 8 characters";
        }

        else if(!/[A-Z]/.test(value)){

            message =
            "Add at least one uppercase letter";
        }

        else if(!/[a-z]/.test(value)){

            message =
            "Add at least one lowercase letter";
        }

        else if(!/\d/.test(value)){

            message =
            "Add at least one number";
        }

        else if(!/[@$!%*?&]/.test(value)){

            message =
            "Add at least one special character";
        }

        else{

            message =
            "Strong password ✓";

            passwordLiveError.style.color =
            "#1f7a3e";
        }

        // ERROR COLOR

        if(message !== "Strong password ✓"){

            passwordLiveError.style.color =
            "#c0392b";
        }

        passwordLiveError.innerText =
        message;
    }

    else{

        passwordLiveError.innerText = "";
    }

});
// ================= LIVE EMAIL VALIDATION =================
document.addEventListener("DOMContentLoaded", () => {

    const emailInput = document.getElementById("email");
    const emailLiveError = document.querySelector(".email-live-error");

    if (emailInput && emailLiveError) {

    emailInput.addEventListener("input", () => {

        const value = emailInput.value.trim();
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (value === "") {
            emailLiveError.innerText = "";
            return;
        }

        if (!value.includes("@")) {
            emailLiveError.innerText = "Email must contain @ symbol";
            emailLiveError.style.color = "#c0392b";
        }
        else if (!emailPattern.test(value)) {
            emailLiveError.innerText = "Enter a valid email address";
            emailLiveError.style.color = "#c0392b";
        }
        else {
            emailLiveError.innerText = "Valid email ✓";
            emailLiveError.style.color = "#1f7a3e";
        }
    });

}

});
// ================= FORM VALIDATION =================

const registerForm =
document.getElementById("registerForm");

registerForm.addEventListener("submit", function(e){

    e.preventDefault();

    let isValid = true;

    // INPUTS

    const name =
    document.getElementById("name");

    const email =
    document.getElementById("email");

    const password =
    document.getElementById("password");

    const confirmPassword =
    document.getElementById("confirmPassword");

    const role =
    document.getElementById("role");

     const securityQuestion =
    document.getElementById("security_question");

    const securityAnswer =
    document.getElementById("security_answer");


    const terms =
    document.getElementById("terms");

   

    // SANITIZED VALUES

    const cleanName =
    sanitizeInput(name.value);

    const cleanEmail =
    sanitizeInput(email.value);

    // CLEAR ERRORS

    document.querySelectorAll(".error")
    .forEach(error => {

        error.innerText = "";

    });

    // ================= NAME =================

    if(cleanName === ""){

        showError(name,
            "Full name is required");

        isValid = false;
    }

    else if(cleanName.length < 3){

        showError(name,
            "Minimum 3 characters required");

        isValid = false;
    }

    else if(!/^[A-Za-z\s]+$/
        .test(cleanName)){

        showError(name,
            "Only alphabets allowed");

        isValid = false;
    }

    // ================= EMAIL =================

  const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

if (cleanEmail === "") {
    if (emailLiveError) {
        emailLiveError.innerText = "Email is required";
        emailLiveError.style.color = "#c0392b";
    }
    isValid = false;

} else if (!emailPattern.test(cleanEmail)) {

    if (emailLiveError) {
        emailLiveError.innerText = "Enter a valid email address";
        emailLiveError.style.color = "#c0392b";
    }
    isValid = false;

} else {

    if (emailLiveError) {
        emailLiveError.innerText = "Valid email ✓";
        emailLiveError.style.color = "#1f7a3e";
    }
}
    const passwordPattern =/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;

// ================= PASSWORD =================
if (password.value === "") {

    passwordLiveError.innerText =
    "Password is required";

    passwordLiveError.style.color =
    "#c0392b";

    isValid = false;
}

else if (!passwordPattern.test(password.value)) {

    isValid = false;
}

if (confirmPassword.value === "") {
    showError(confirmPassword, "Confirm password is required");
    isValid = false;
}
else if (confirmPassword.value !== password.value) {
    showError(confirmPassword, "Passwords do not match");
    isValid = false;
}
    // ================= ROLE =================

    if(role.value === ""){

        showError(role,
            "Please select role");

        isValid = false;
    }
     // ================= SECURITY QUESTION =================
    if (securityQuestion.value === "") {

    showError(securityQuestion,
        "Please select a security question");

    isValid = false;
    }

// ================= SECURITY ANSWER =================
    if (securityAnswer.value.trim() === "") {

    showError(securityAnswer,
        "Security answer is required");

    isValid = false;
    }
    // ================= TERMS =================

    if(!terms.checked){

        document.getElementById("termsError")
        .innerText =
        "You must accept terms and conditions";

        isValid = false;
    }

    // ================= SUCCESS =================
// ================= SUCCESS (BACKEND CALL) =================
    // ================= SUCCESS (BACKEND CALL) =================

   // ... (keep all your sanitation, toggle buttons, and validation patterns the same)

    // ================= SUCCESS (BACKEND CALL) =================
    if (isValid) {
        console.log("VALIDATION PASSED - sending request");

        const formData = new FormData();
        formData.append("name", cleanName);
        formData.append("email", cleanEmail);
        formData.append("password", password.value);
        formData.append("role", role.value);
        formData.append("security_question", securityQuestion.value);
        formData.append("security_answer", securityAnswer.value.trim());
        console.log("Attempting backend fetch connection...");

        fetch("./register.php", {
            method: "POST",
            body: formData
        })
        .then(res => {
            if (!res.ok) {
                throw new Error("HTTP error! Status: " + res.status);
            }
            return res.text();
        })
        .then(data => {
            console.log("RAW RESPONSE FROM SERVER:", data); 

            if (data.trim() === "success") {
              showAlert(
    "Registration successful!",
    "success"
);
                registerForm.reset();
            } else {
              showAlert(data, "error");
            }
        })
        .catch(err => {
            console.error("Fetch Execution Error:", err);
        showAlert(
    "Server connection error occurred",
    "error"
);
        });
    } else {
        // Fallback popup so you immediately know validation stopped it locally
        showAlert(
    "Please fix the form errors",
    "error"
);
    }
}); // Closes submit event listener

// ================= ERROR FUNCTION =================


function showError(input, message) {

    let error = null;

    if (input.id === "terms") {
        error = document.getElementById("termsError");
    }
    else {
        const group = input.closest(".input-group");

        if (group) {
            error = group.querySelector(".error");
        }
    }

    if (!error) {
        console.log("ERROR ELEMENT NOT FOUND for:", input.id);
        return;
    }

    error.innerText = message;
}
// ================= ALERT FUNCTION =================
// ================= ALERT FUNCTION =================

function showAlert(message, type){

    const alertBox =
    document.getElementById("alertBox");

    // SAFETY CHECK
    if(!alertBox){

        console.log("alertBox not found");

        return;
    }

    alertBox.innerText = message;

    alertBox.style.display = "block";

    // RESET CLASSES
    alertBox.className = "alert-box";

    // SUCCESS / ERROR
    if(type === "success"){

        alertBox.classList.add("alert-success");

    }

    else{

        alertBox.classList.add("alert-error");

    }

    // AUTO HIDE
    setTimeout(() => {

        alertBox.style.display = "none";

    }, 4000);
}