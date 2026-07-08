<?php
// Ensure active system data connection utilities are included safely
include "db.php";

$message_sent = false;
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_msg'])) {
    // Collect and sanitize form inputs
    $name = trim($_POST['contact_name'] ?? '');
    $email = trim($_POST['contact_email'] ?? '');
    $message = trim($_POST['contact_message'] ?? '');

    // Server-side validation check parameters
    if (!empty($name) && !empty($email) && !empty($message)) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            
            // Insert clean parameters using data type binding structures
            $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $email, $message);
            
            if ($stmt->execute()) {
                $message_sent = true;
                echo "<script>alert('✅ Message received successfully! Our support agents will contact you shortly.'); window.location.href='about-contact.php';</script>";
                exit;
            } else {
                echo "<script>alert('❌ Database Error: Unable to record submission entry.');</script>";
            }
            $stmt->close();
            
        } else {
            echo "<script>alert('❌ Formatting Error: Please provide a valid email structure.');</script>";
        }
    } else {
        echo "<script>alert('❌ Missing Parameters: All form text input inputs are required.');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuizVerse - About & Contact</title>
    <link rel="stylesheet" href="about-contact.css">
     <link
      href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
      rel="stylesheet"
    />
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    />
</head>
<body>

<header class="header-nav">
    <div class="topbar-left">
        <div class="logo">
            <div class="logo-box">
                <img src="quizverse-logo.png" alt="QuizVerse Logo" onerror="this.style.display = 'none'">
            </div>
            <h2>QuizVerse</h2>
        </div>

        <nav class="topbar-nav-links">
            <a href="teacher.html" class="simple-nav-icon" title="Home">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
            </a>
            <a href="about-contact.php" class="simple-nav-icon" title="Contact Us">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                    <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
            </a>

            <div class="header-dropdown-container">
                <a href="#" class="simple-nav-icon" id="themeBtn" title="Change Theme">
                    <i class="fa-regular fa-moon" style="font-size: 20px"></i>
                </a>
                <div class="dropdown-menu" id="themeMenu">
                    <button onclick="setTheme('light')"><i class="fa-solid fa-sun"></i> Light Default</button>
                    <button onclick="setTheme('dark')"><i class="fa-solid fa-moon"></i> Dark Onyx</button>
                    <button onclick="setTheme('blue')"><i class="fa-solid fa-droplet"></i> Blue Fluorescent</button>
                </div>
            </div>

          <div class="header-dropdown-container">
    <a href="#" class="simple-nav-icon" id="textBtn" title="Text Size Scaling">
        <i class="fa-solid fa-text-height" style="font-size: 19px"></i>
    </a>
    <div class="dropdown-menu" id="textMenu">
        <button onclick="setTextSize('small')">A- Small Scale</button>
        <button onclick="setTextSize('medium')">A Default Medium</button>
        <button onclick="setTextSize('large')">A+ Large Scale</button>
    </div>
</div>
        </nav>
    </div>
</header>

    <div class="container">
    <div class="header-section">
        <h1>ABOUT & <span>CONTACT</span></h1>
        <p>Get to know the team behind QuizVerse or reach out to us with any questions.</p>
    </div>


       <div class="grid-layout">
            <section class="card">
                <h2>About Us ✨</h2>
                <p>QuizVerse is a modern quiz platform designed to make learning interactive, engaging, and efficient for students and admins.</p>
                <div class="feature">💡 Easy Quiz Creation</div>
                <div class="feature">👥 User Management</div>
                <div class="feature">📈 Performance Analytics</div>
            </section>

            <section class="card">
                <h2>Contact Us ✉️</h2>
    
    <form id="contactForm" method="POST" action="about-contact.php">
        <input type="text" name="contact_name" placeholder="Your Name" required maxlength="100">
        <input type="email" name="contact_email" placeholder="Your Email" required maxlength="150">
        <textarea name="contact_message" rows="5" placeholder="Your Message" required></textarea>
        <button type="submit" name="submit_msg">Send Message</button>
    </form>
            </section>
        </div>
    </div>

    <script src="about-contact.js"></script>
    <footer style="margin-top: 80px; padding: 40px; text-align: center; color: #777; border-top: 1px solid #ddd;">
    <p>&copy; <?=date('Y')?> QuizVerse. All rights reserved.</p>
    <p style="font-size: 13px; margin-top: 8px;">Modern online quiz management system.</p>
</footer>
</body>
</html>