<?php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $role  = $_POST['role'];
    $name  = trim($_POST['full_name']);
    $id    = intval($_POST['user_id']); // INT — numeric IDs only
    $email = trim($_POST['email']);
    $pass  = $_POST['password'];

    if (empty($id) || empty($name) || empty($email) || empty($pass) || empty($role)) {
        echo "<script>alert('❌ All fields are required!'); window.history.back();</script>";
        exit();
    }

    // Check if ID already exists
    $checkStmt = $conn->prepare("SELECT user_id FROM User_Table WHERE user_id = ?");
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        echo "<script>alert('❌ Error: ID Number $id is already registered!'); window.history.back();</script>";
    } else {
        $checkStmt->close();

        // Check if email already exists
        $emailCheck = $conn->prepare("SELECT user_id FROM User_Table WHERE email = ?");
        $emailCheck->bind_param("s", $email);
        $emailCheck->execute();
        $emailCheck->store_result();

        if ($emailCheck->num_rows > 0) {
            echo "<script>alert('❌ Error: This email is already registered!'); window.history.back();</script>";
            $emailCheck->close();
        } else {
            $emailCheck->close();

            // Insert with account_status = 'pending' by default
            // Admin must approve before student can log in
            $stmt = $conn->prepare("INSERT INTO User_Table (user_id, name_user, email, password, role, account_status) VALUES (?, ?, ?, ?, ?, 'pending')");
            $stmt->bind_param("issss", $id, $name, $email, $pass, $role);

            if ($stmt->execute()) {
                echo "<script>alert('✅ Registration submitted for $name! Please wait for the librarian to approve your account before logging in.'); window.location='Homepage.php';</script>";
            } else {
                echo "Error: " . $conn->error;
            }
            $stmt->close();
        }
    }
}
?>