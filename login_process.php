<?php
include 'db.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id  = isset($_POST['user_id'])  ? intval($_POST['user_id'])  : 0;
    $password = isset($_POST['password']) ? trim($_POST['password'])   : '';
    $role     = isset($_POST['role'])     ? trim($_POST['role'])       : '';

    if (empty($user_id) || empty($password) || empty($role)) {
        die("<script>alert('❌ All fields are required!'); window.location.href='Homepage.php';</script>");
    }

    $sql  = "SELECT * FROM User_Table WHERE user_id = ? AND role = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) die("Query Error: " . $conn->error);

    $stmt->bind_param("is", $user_id, $role);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        if ($row['password'] == $password) {

            // [NEW] Block login if account is still pending admin approval
            if ($row['account_status'] === 'pending') {
                echo "<script>alert('⏳ Your account is pending admin approval. Please wait for the librarian to approve your registration.'); window.location.href='Homepage.php';</script>";
                exit();
            }

            // [NEW] Block login if account was rejected by admin
            if ($row['account_status'] === 'rejected') {
                echo "<script>alert('❌ Your account registration has been rejected. Please contact the librarian.'); window.location.href='Homepage.php';</script>";
                exit();
            }

            // Account is approved — set session and redirect
            $_SESSION['user_id']   = $row['user_id'];
            $_SESSION['role']      = $row['role'];
            $_SESSION['name_user'] = $row['name_user'];

            if ($row['role'] == 'admin') {
                header("Location: Admin.php");
                exit();
            } else {
                header("Location: Student.php");
                exit();
            }
        } else {
            echo "<script>alert('❌ Invalid Password!'); window.location.href='Homepage.php';</script>";
        }
    } else {
        echo "<script>alert('❌ Invalid ID or Role!'); window.location.href='Homepage.php';</script>";
    }

    $stmt->close();
    $conn->close();
}
?>