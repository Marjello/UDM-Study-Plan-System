<?php
session_start();
include('../config/db.php');

$username = $_POST['username'];
$password = $_POST['password'];

$sql = "SELECT * FROM user WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user && hash('sha256', $password) === $user['password']) {
    $_SESSION['user'] = $user['username'];
    header("Location:../dashboard.php");
} else {
    $_SESSION['error'] = "Invalid login credentials.";
    header("Location: login.php");
}
?>
