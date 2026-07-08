<?php

session_start();

include "db.php";
/* ================= CHECK FORM ================= */

if (
    isset($_POST['email']) &&
    isset($_POST['password']) &&
    isset($_POST['role'])
) {

    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);

    /* ================= GET USER ================= */

   $sql = "SELECT * FROM users WHERE email='$email'";
$result = $conn->query($sql);

    if ($result->num_rows > 0) {

    $row = $result->fetch_assoc();

    // ROLE CHECK (separate error)
    if ($row['role'] != $role) {

        echo "User not found";
        exit;
    }

 // =========================================================================
    // PASSWORD CHECK & ACTIVE SESSION ASSIGNMENT
    // =========================================================================
  if (password_verify($password, $row['password'])) {

        // Crucial Assignment: Store the raw database ID for relational tables
        $_SESSION['user_id'] = intval($row['id']);

        $_SESSION['user'] = [
            "id"    => intval($row['id']),
            "name"  => $row['full_name'], // Make sure 'name' matches your users table column
            "email" => $row['email'],
            "role"  => $row['role']
        ];

        // Send role string response back to login.js pipeline handler
        echo $row['role'];
        exit;

    } else {
        echo "Invalid account or password";
        exit;
    }
}
else {

    // EMAIL NOT FOUND
    echo "Invalid account or password";
    exit;
}

}

?>

?>

<!-- ================= LOGIN PAGE (login.html) ================= -->

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>QuizVerse - Login</title>

    <!-- CSS -->
    <link rel="stylesheet" href="login.css">

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

        
</head>

<body>

<div class="toast" id="toast"></div>
<header class="navbar">

    <div class="logo">
        <div class="logo-box">
            <img src="quizverse-logo.png" alt="QuizVerse Logo">
        </div>
        <h2>QuizVerse</h2>
    </div>

</header>
 
    <!-- ================= MAIN SECTION ================= -->

    <section class="main-container">

        <!-- LEFT SIDE -->

       <div class="left-side">

    <span class="tag">Smart Learning Platform</span>

    <h1>
        LOGIN TO YOUR <span>ACCOUNT</span>
    </h1>

    <p>
        Access your dashboard, quizzes, results and learning materials securely.
    </p>

</div>
        <!-- ================= LOGIN CARD ================= -->

        <div class="login-card">

            <h2>Login</h2>

            <p class="subtitle">
                Enter your account details
            </p>

            <form id="loginForm" method="POST" action="login.php">

                <!-- EMAIL -->

                <div class="input-group">

                    <label>Email Address <span>*</span></label>

                    <input type="email" id="email" name="email" placeholder="Enter your email">

                    <small class="error"></small>

                </div>

                <!-- PASSWORD -->

                <div class="input-group">

                    <label>Password <span>*</span></label>

                    <div class="password-box">

                       <input type="password" id="password" name="password" placeholder="Enter password">
                        <i class="fa-solid fa-eye togglePassword"></i>

                    </div>

                    <small class="error"></small>

                </div>

                <!-- FORGOT PASSWORD LINK -->

<p class="forgot-link">
    <a href="forgot_password.php">Forgot Password?</a>
</p>

                <!-- ROLE -->

                <div class="input-group">

                    <label>Select Role <span>*</span></label>

                   <select id="role" name="role">

                        <option value="">
                            Choose your role
                        </option>

                        <option value="student">
                            Student
                        </option>

                        <option value="teacher">
                            Teacher
                        </option>

                        <option value="admin">
                            Admin
                        </option>

                    </select>

                    <small class="error"></small>

                </div>

                <!-- BUTTON -->

                <button type="submit" class="login-btn-main">

                    Login

                </button>

               
  <p class="register-link">
    Don't have an account?
    <a href="register.html">Sign Up</a>
</p>
            </form>

        </div>

    </section>

    <script src="login.js"></script>

<footer style="margin-top: 80px; padding: 40px; text-align: center; color: #777; border-top: 1px solid #ddd;">
    <p>&copy; <?=date('Y')?> QuizVerse. All rights reserved.</p>
    <p style="font-size: 13px; margin-top: 8px;">Modern online quiz management system.</p>
</footer>



</body>

</html>