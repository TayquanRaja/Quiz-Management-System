<?php

// ================= DEVELOPMENT MODE =================
// Turn OFF in production

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ================= DATABASE =================

include "db.php";

// ================= ALLOW ONLY POST =================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    echo "Invalid request";
    exit();
}

// ================= GET FORM DATA =================

$name     = trim($_POST["name"] ?? "");
$email    = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";
$role     = trim($_POST["role"] ?? "");
$security_question = trim($_POST["security_question"] ?? "");
$security_answer = trim($_POST["security_answer"] ?? "");

// ================= REQUIRED FIELDS =================

if (
    empty($name) ||
    empty($email) ||
    empty($password) ||
    empty($role) ||
    empty($security_question) ||
    empty($security_answer)
) {

    echo "All fields are required";
    exit();
}

// ================= NAME VALIDATION =================

if (strlen($name) < 3 || strlen($name) > 50) {

    echo "Name must be between 3 and 50 characters";
    exit();
}

if (!preg_match("/^[A-Za-z\s]+$/", $name)) {

    echo "Only alphabets allowed in name";
    exit();
}

// ================= EMAIL VALIDATION =================

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

    echo "Invalid email format";
    exit();
}

if (strlen($email) > 100) {

    echo "Email too long";
    exit();
}

// ================= PASSWORD VALIDATION =================

$passwordPattern =
'/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{8,}$/';

if (!preg_match($passwordPattern, $password)) {

    echo "Weak password";
    exit();
}

// ================= ROLE VALIDATION =================

$allowedRoles = [
    "student",
    "teacher",
    "admin"
];

if (!in_array($role, $allowedRoles)) {

    echo "Invalid role selected";
    exit();
}

// ================= CHECK DUPLICATE EMAIL =================

$check =
$conn->prepare(
    "SELECT id FROM users WHERE email = ?"
);

if (!$check) {

    echo "Database error";
    exit();
}

$check->bind_param("s", $email);

$check->execute();

$check->store_result();

if ($check->num_rows > 0) {

    echo "Email already exists";

    $check->close();

    exit();
}

$check->close();

// ================= HASH PASSWORD =================

$hashedPassword =
password_hash(
    $password,
    PASSWORD_DEFAULT
);

// ================= INSERT USER =================

$stmt =
$conn->prepare(
    "INSERT INTO users
     (full_name, email, password, role, auth_provider, security_question, security_answer)
    VALUES (?, ?, ?, ?, ?, ?, ?)"
);

if (!$stmt) {

    echo "Database prepare failed";
    exit();
}
$provider = "normal";
$stmt->bind_param(
  "sssssss",
    $name,
    $email,
    $hashedPassword,
    $role,
    $provider,
    $security_question,
    $security_answer
);
// ================= EXECUTE =================

if ($stmt->execute()) {

    echo "success";

} else {

    echo "Registration failed";
}

// ================= CLOSE =================

$stmt->close();

$conn->close();

?>