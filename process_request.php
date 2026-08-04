<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: Homepage.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $request_id  = intval($_POST['request_id']);
    $action      = $_POST['action'];
    if ($action == 'approve') {
        $book_id     = intval($_POST['book_id']);
        $user_id = $_POST['user_id'];

        // Update request status to approved
        $stmt = $conn->prepare("UPDATE Borrow_Request_Table SET status = 'approved' WHERE request_id = ?");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $stmt->close();

        // Create transaction (14 days borrow period)
        $trans_stmt = $conn->prepare("INSERT INTO Transaction_Table (book_id, user_id, issue_date, due_date, return_date) VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 14 DAY), NULL)");
        $trans_stmt->bind_param("is", $book_id, $user_id);
        $trans_stmt->execute();
        $trans_stmt->close();

        // Update book status to Borrowed
        $update_stmt = $conn->prepare("UPDATE Books_Table SET track_status = 'Borrowed' WHERE book_id = ?");
        $update_stmt->bind_param("i", $book_id);
        $update_stmt->execute();
        $update_stmt->close();

        echo "<script>alert('✅ Request approved! Book borrowed for 14 days.'); window.location.href='student.php';</script>";

    } else if ($action == 'reject') {
        $stmt = $conn->prepare("UPDATE Borrow_Request_Table SET status = 'rejected' WHERE request_id = ?");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $stmt->close();

        echo "<script>alert('❌ Request rejected.'); window.location.href='student.php';</script>";
    }
}
?>