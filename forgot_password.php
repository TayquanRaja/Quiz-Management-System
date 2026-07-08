<?php
include "db.php";

$emailError = "";
$securityError = "";
$message = "";
$popupError = "";
$emailValue = "";
$securityAnswerValue = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $emailValue = trim($_POST['email']);
   $securityAnswerValue = trim($_POST['security_answer']);
   $securityAnswer = $securityAnswerValue;

    $hasError = false;

    // EMAIL CHECK
    if ($emailValue == "") {
        $emailError = "Email is required";
        $hasError = true;
    }

    // SECURITY ANSWER CHECK
    if ($securityAnswer == "") {
        $securityError = "Security answer is required";
        $hasError = true;
    }

    if (!$hasError) {

        $check = $conn->query("
            SELECT * FROM users 
            WHERE LOWER(TRIM(email)) = LOWER('$emailValue')
            AND LOWER(TRIM(security_answer)) = LOWER('$securityAnswer')
        ");

        if ($check->num_rows > 0) {

            $token = bin2hex(random_bytes(16));

            $conn->query("
                UPDATE users 
                SET reset_token='$token' 
                WHERE LOWER(TRIM(email))=LOWER('$emailValue')
            ");

            $message = "
<div class='success-box'>
    <i class='fa-solid fa-circle-check'></i>

    <div>
        <p class='success-title'>
            Reset link generated successfully
        </p>

        <a href='reset_password.php?token=$token'>
            Reset Your Password
        </a>
    </div>
</div>
";

        } else {
           $popupError = "Account details not match";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
    <link rel="stylesheet" href="login.css">
    <link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
    html, body {
    height: 100%;
   
}

</style>
</head>

<body class="forgot-page">
<header class="navbar">

    <div class="logo">
        <div class="logo-box">
            <img src="quizverse-logo.png" alt="QuizVerse Logo">
        </div>
        <h2>QuizVerse</h2>
    </div>

</header>

<div class="page-content">
<div class="login-card" style="margin:100px auto; ">

      <!-- BACK BUTTON -->
      <a href="login.php" class="back-arrow">
    <i class="fa-solid fa-arrow-left"></i>
    </a>

    <h2>Forgot Password</h2>

    <p class="subtitle">Enter your email to reset password</p>
     
    <?php if (!empty($popupError)) { ?>

<div class="alert-error-box">
    <?php echo $popupError; ?>
</div>

<?php } ?>



    <form method="POST">

        <!-- EMAIL FIELD -->
        <div class="input-group">
            <label>Email</label>

            <input type="email"
                   name="email"
                   value="<?php echo $emailValue; ?>" >

            <!-- FIELD ERROR -->
            <small class="error">
    <?php echo $emailError; ?>
</small>
        </div>
         
        <!-- SECURITY ANSWER FIELD -->
       <div class="input-group">
        <label>Security Answer</label>

        <input type="text"
           name="security_answer"
           placeholder="Enter your security answer"
           value="<?php echo $securityAnswerValue; ?>">
           
      <small class="error">
    <?php echo $securityError; ?>
</small>
     </div>

        <!-- BUTTON -->
        <button type="submit" class="login-btn-main">
            Send Reset Link
        </button>

    </form>

    <!-- SUCCESS MESSAGE (BELOW FORM, NOT TOP LEFT) -->
    <?php if (!empty($message)) { ?>
        <div style="margin-top:15px;">
            <?php echo $message; ?>
        </div>
    <?php } ?>

</div>

</div>
<footer style="margin-top: auto; padding: 40px; text-align: center; color: #777; border-top: 1px solid #ddd;">
    <p>&copy; <?=date('Y')?> QuizVerse. All rights reserved.</p>
    <p style="font-size: 13px; margin-top: 8px;">Modern online quiz management system.</p>
</footer>
</body>
</html>