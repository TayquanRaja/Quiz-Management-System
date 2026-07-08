<?php

include "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    echo "Invalid request";
    exit();
}

$name  = trim($_POST["google_name"] ?? "");
$email = trim($_POST["google_email"] ?? "");
$uid   = trim($_POST["google_uid"] ?? "");

if (
    empty($name) ||
    empty($email) ||
    empty($uid)
) {

    echo "Missing Google data";
    exit();
}

$check =
$conn->prepare(
    "SELECT id FROM users WHERE email=?"
);

$check->bind_param("s", $email);

$check->execute();

$check->store_result();

if($check->num_rows > 0){

    echo "Email already exists";

    $check->close();

    exit();
}

$check->close();

$role = trim($_POST["google_role"] ?? "");
$allowedRoles = [
    "student",
    "teacher"
];

if(!in_array($role, $allowedRoles)){

    echo "Invalid role";
    exit();
}
$password =
password_hash($uid, PASSWORD_DEFAULT);

$provider = "google";

$stmt =
$conn->prepare(
    "INSERT INTO users
    (full_name,email,password,role,auth_provider)
    VALUES (?,?,?,?,?)"
);

if(!$stmt){

    echo "Prepare failed: " . $conn->error;
    exit();
}

$stmt->bind_param(
    "sssss",
    $name,
    $email,
    $password,
    $role,
    $provider
);

if($stmt->execute()){

    echo "success";

}
else{

    echo "Database insert failed: " . $stmt->error;

}

$stmt->close();

$conn->close();

?>