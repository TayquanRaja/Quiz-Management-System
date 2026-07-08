<?php
include "db.php";

$token = $_GET['token'];

$check = $conn->query("SELECT * FROM users WHERE reset_token='$token'");

if ($check->num_rows == 0) {
    die("Invalid or expired reset link");
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $password = trim($_POST['password']);

    // PASSWORD VALIDATION
    $passwordRegex =
    "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/";

    if ($password == "") {

        $error = "Password is required";
    }

    else if (!preg_match($passwordRegex, $password)) {

        $error = "Password must contain: 8+ characters, uppercase, lowercase, number and special character";
    }

    else {

        $newPass = password_hash($password, PASSWORD_DEFAULT);

        $conn->query("
            UPDATE users 
            SET password='$newPass', reset_token=NULL 
            WHERE reset_token='$token'
        ");

         
    
        $success = "Password updated successfully";
         
    }
}
?>

<!DOCTYPE html>
<html>

<head>

    <title>Reset Password</title>

    <link rel="stylesheet" href="login.css">

    <!-- FONT AWESOME -->
    <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  
  <style>
    html, body {
    height: 100%;
   
}
</style>
</head>

<body class="reset-page">
 <header class="navbar">

    <div class="logo">
        <div class="logo-box">
            <img src="quizverse-logo.png" alt="QuizVerse Logo">
        </div>
        <h2>QuizVerse</h2>
    </div>

</header>
<div class="page-content">
<div class="login-card" style="margin:100px auto;">
   <!-- BACK BUTTON -->
      <a href="forgot_password.php" class="back-arrow">
    <i class="fa-solid fa-arrow-left"></i>
    </a>

    <h2>Reset Password</h2>
        <p class="subtitle">Please enter your new password.</p>
    <form method="POST">

        <div class="input-group">

            <label>New Password</label>

            <!-- PASSWORD BOX -->
            <div class="password-box">

               <input type="password"
       name="password"
       id="password"
       placeholder="Enter new password"
       value="<?php 
if ($success == "") {
    echo isset($_POST['password']) ? $_POST['password'] : '';
}
?>">
                <!-- EYE ICON -->
                <i class="fa-solid fa-eye togglePassword"></i>

            </div>

            <!-- ERROR -->
            <small class="error">
                <?php echo $error; ?>
            </small>

        </div>

        <button type="submit" class="login-btn-main">
            Update Password
        </button>

    </form>

    <!-- SUCCESS MESSAGE -->
    <?php if ($success != "") { ?>

     <div class="success-box">
    <i class="fa-solid fa-circle-check"></i>

    <div>
        Your password has been reset successfully.
    </div>
</div>

    <?php } ?>

</div>
</div>
<!-- PASSWORD TOGGLE JS -->
<script>

const togglePassword = document.querySelector(".togglePassword");

togglePassword.addEventListener("click", () => {

    const password = document.getElementById("password");

    if (password.type === "password") {

        password.type = "text";

        togglePassword.classList.remove("fa-eye");
        togglePassword.classList.add("fa-eye-slash");

    } 
    
    else {

        password.type = "password";

        togglePassword.classList.remove("fa-eye-slash");
        togglePassword.classList.add("fa-eye");
    }
});

<?php if ($success != "") { ?>
    setTimeout(() => {
        window.location.href = "login.php?reset=success";
    }, 2000);
<?php } ?>
</script>
 <footer style="margin-top:auto; padding: 40px; text-align: center; color: #777; border-top: 1px solid #ddd;">
    <p>&copy; <?=date('Y')?> QuizVerse. All rights reserved.</p>
    <p style="font-size: 13px; margin-top: 8px;">Modern online quiz management system.</p>
</footer>
</body>
</html>