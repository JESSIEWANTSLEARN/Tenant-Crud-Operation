<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: Homepage.php");
    exit();
}

$feedback = '';
$feedback_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_book') {
        $title  = trim($_POST['title']);
        $author = trim($_POST['author']);
        $genre  = trim($_POST['genre']);
        if ($title && $author) {
            $stmt = $conn->prepare("INSERT INTO Books_Table (title, author, genre, track_status) VALUES (?, ?, ?, 'Available')");
            $stmt->bind_param("sss", $title, $author, $genre);
            $stmt->execute() ? ($feedback = "✅ Book '$title' added successfully!") : ($feedback = "❌ Error: " . $conn->error);
            $feedback_type = $stmt->affected_rows > 0 ? 'success' : 'error';
            $stmt->close();
        }
    }

    if ($action === 'edit_book') {
        $id = intval($_POST['book_id']); $title = trim($_POST['title']);
        $author = trim($_POST['author']); $genre = trim($_POST['genre']); $status = trim($_POST['track_status']);
        $stmt = $conn->prepare("UPDATE Books_Table SET title=?, author=?, genre=?, track_status=? WHERE book_id=?");
        $stmt->bind_param("ssssi", $title, $author, $genre, $status, $id);
        $stmt->execute(); $feedback = "✅ Book updated!"; $feedback_type = 'success'; $stmt->close();
    }

    if ($action === 'delete_book') {
        $id = intval($_POST['book_id']);
        $check = $conn->query("SELECT COUNT(*) as c FROM Transaction_Table WHERE book_id=$id AND return_date IS NULL")->fetch_assoc();
        if ($check['c'] > 0) { $feedback = "❌ Cannot delete: book has active borrow transactions."; $feedback_type = 'error'; }
        else {
            $conn->query("DELETE FROM Borrow_Request_Table WHERE book_id=$id");
            $conn->query("DELETE FROM Fines_table WHERE transaction_id IN (SELECT transaction_id FROM Transaction_Table WHERE book_id=$id)");
            $conn->query("DELETE FROM Transaction_Table WHERE book_id=$id");
            $conn->query("DELETE FROM Books_Table WHERE book_id=$id");
            $feedback = "✅ Book deleted."; $feedback_type = 'success';
        }
    }

    if ($action === 'approve') {
        $req_id = intval($_POST['request_id']); $book_id = intval($_POST['book_id']);
        $user_id = intval($_POST['user_id']); $due_days = 14;
        $bk = $conn->query("SELECT track_status FROM Books_Table WHERE book_id=$book_id")->fetch_assoc();
        if ($bk['track_status'] !== 'Available') { $feedback = "❌ Book is no longer available."; $feedback_type = 'error'; }
        else {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("UPDATE Borrow_Request_Table SET status='approved' WHERE request_id=?");
                $stmt->bind_param("i", $req_id); $stmt->execute(); $stmt->close();
                $stmt = $conn->prepare("INSERT INTO Transaction_Table (book_id, user_id, issue_date, due_date) VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY))");
                $stmt->bind_param("iii", $book_id, $user_id, $due_days); $stmt->execute(); $stmt->close();
                $stmt = $conn->prepare("UPDATE Books_Table SET track_status='Borrowed' WHERE book_id=?");
                $stmt->bind_param("i", $book_id); $stmt->execute(); $stmt->close();
                $conn->commit();
                $feedback = "✅ Request approved! Due in $due_days days."; $feedback_type = 'success';
            } catch (Exception $e) { $conn->rollback(); $feedback = "❌ Error: " . $e->getMessage(); $feedback_type = 'error'; }
        }
    }

    if ($action === 'reject') {
        $req_id = intval($_POST['request_id']);
        $stmt = $conn->prepare("UPDATE Borrow_Request_Table SET status='rejected' WHERE request_id=?");
        $stmt->bind_param("i", $req_id); $stmt->execute(); $stmt->close();
        $feedback = "Request rejected."; $feedback_type = 'success';
    }

    if ($action === 'mark_returned') {
        $txn_id = intval($_POST['transaction_id']); $book_id = intval($_POST['book_id']);
        $conn->begin_transaction();
        try {
            $conn->query("UPDATE Transaction_Table SET return_date=CURDATE() WHERE transaction_id=$txn_id");
            $conn->query("UPDATE Books_Table SET track_status='Available' WHERE book_id=$book_id");
            $txn = $conn->query("SELECT due_date FROM Transaction_Table WHERE transaction_id=$txn_id")->fetch_assoc();
            $days_late = (int)$conn->query("SELECT DATEDIFF(CURDATE(), '{$txn['due_date']}') AS d")->fetch_assoc()['d'];
            if ($days_late > 0) { $fine = $days_late * 5.00; $conn->query("INSERT INTO Fines_table (transaction_id, fine_amount, status) VALUES ($txn_id, $fine, 'unpaid')"); }
            $conn->commit();
            $feedback = "✅ Book marked as returned." . ($days_late > 0 ? " Fine of ₱" . number_format($days_late * 5, 2) . " assessed." : ""); $feedback_type = 'success';
        } catch (Exception $e) { $conn->rollback(); $feedback = "❌ Error: " . $e->getMessage(); $feedback_type = 'error'; }
    }

    if ($action === 'mark_paid') {
        $fine_id = intval($_POST['fine_id']);
        $conn->query("UPDATE Fines_table SET status='paid' WHERE fine_id=$fine_id");
        $feedback = "✅ Fine marked as paid."; $feedback_type = 'success';
    }

    if ($action === 'delete_fine') {
        $fine_id = intval($_POST['fine_id']);
        $conn->query("DELETE FROM Fines_table WHERE fine_id=$fine_id");
        $feedback = "Fine removed."; $feedback_type = 'success';
    }

    // [NEW] APPROVE ACCOUNT REGISTRATION
    if ($action === 'approve_account') {
        $uid = intval($_POST['target_user_id']);
        $conn->query("UPDATE User_Table SET account_status='approved' WHERE user_id=$uid");
        $feedback = "✅ Account approved. Student can now log in."; $feedback_type = 'success';
    }

    // [NEW] REJECT ACCOUNT REGISTRATION
    if ($action === 'reject_account') {
        $uid = intval($_POST['target_user_id']);
        $conn->query("UPDATE User_Table SET account_status='rejected' WHERE user_id=$uid");
        $feedback = "Account registration rejected."; $feedback_type = 'success';
    }

    // [NEW] DELETE PENDING/REJECTED ACCOUNT
    if ($action === 'delete_account') {
        $uid = intval($_POST['target_user_id']);
        $conn->query("DELETE FROM User_Table WHERE user_id=$uid AND account_status != 'approved'");
        $feedback = "Account removed."; $feedback_type = 'success';
    }
}

// Fetch data
$pending_count       = $conn->query("SELECT COUNT(*) as c FROM Borrow_Request_Table WHERE status='pending'")->fetch_assoc()['c'];
// [NEW] Count pending account registrations for sidebar badge
$pending_accounts    = $conn->query("SELECT COUNT(*) as c FROM User_Table WHERE account_status='pending'")->fetch_assoc()['c'];
$books_result        = $conn->query("SELECT * FROM Books_Table ORDER BY book_id DESC");
$requests_result     = $conn->query("SELECT br.*, u.name_user, bk.title FROM Borrow_Request_Table br JOIN User_Table u ON br.user_id=u.user_id JOIN Books_Table bk ON br.book_id=bk.book_id WHERE br.status='pending' ORDER BY br.request_date DESC");
$borrowers_result    = $conn->query("SELECT ut.user_id, ut.name_user, bk.title, t.transaction_id, t.book_id, t.due_date, DATEDIFF(CURDATE(), t.due_date) AS days_overdue FROM Transaction_Table t JOIN User_Table ut ON t.user_id=ut.user_id JOIN Books_Table bk ON t.book_id=bk.book_id WHERE t.return_date IS NULL ORDER BY t.due_date ASC");
$transactions_result = $conn->query("SELECT t.*, u.name_user, bk.title, CASE WHEN t.return_date IS NOT NULL THEN 'Returned' WHEN t.return_date IS NULL AND CURDATE() > t.due_date THEN 'Overdue' ELSE 'Active' END AS borrow_status FROM Transaction_Table t JOIN User_Table u ON t.user_id=u.user_id JOIN Books_Table bk ON t.book_id=bk.book_id ORDER BY t.transaction_id DESC");
$fines_result        = $conn->query("SELECT f.*, u.name_user, bk.title, t.due_date, t.return_date FROM Fines_table f JOIN Transaction_Table t ON f.transaction_id=t.transaction_id JOIN User_Table u ON t.user_id=u.user_id JOIN Books_Table bk ON t.book_id=bk.book_id ORDER BY f.fine_id DESC");
$total_books         = $conn->query("SELECT COUNT(*) as c FROM Books_Table")->fetch_assoc()['c'];
$available_books     = $conn->query("SELECT COUNT(*) as c FROM Books_Table WHERE track_status='Available'")->fetch_assoc()['c'];
$active_borrows      = $conn->query("SELECT COUNT(*) as c FROM Transaction_Table WHERE return_date IS NULL")->fetch_assoc()['c'];
$total_fines         = $conn->query("SELECT COALESCE(SUM(fine_amount),0) as s FROM Fines_table WHERE status='unpaid'")->fetch_assoc()['s'];

// [NEW] All pending registrations for the account approval tab
$pending_accounts_result = $conn->query("SELECT * FROM User_Table WHERE account_status='pending' ORDER BY user_id ASC");

// Book Collection Preview data
$preview_books_result = $conn->query("SELECT * FROM Books_Table ORDER BY title ASC");
$preview_all_books = [];
if ($preview_books_result && $preview_books_result->num_rows > 0) {
    while ($row = $preview_books_result->fetch_assoc()) $preview_all_books[] = $row;
}
$preview_emoji_map = [
    'Fiction'=>'🎭','Classic'=>'📜','Dystopian'=>'👁️','Sci-Fi'=>'🚀','Mystery'=>'🕵️',
    'Romance'=>'❤️','Fantasy'=>'🪄','Non-Fiction'=>'📰','Biography'=>'👤',
    'ISEKAI'=>'⚡','Adventure'=>'🗺️','Classic Fiction'=>'📜',
];
$preview_genres = array_unique(array_filter(array_column($preview_all_books, 'genre')));
sort($preview_genres);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard | Alexandria</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root {
    --navy:#0d1b3e; --navy-light:#162347; --navy-mid:#1e2f52;
    --accent:#4f8ef7; --accent-gold:#f0b429; --green:#22c55e;
    --red:#ef4444; --orange:#f97316; --text:#e2e8f0;
    --text-muted:#94a3b8; --border:rgba(255,255,255,0.08);
    --card:rgba(255,255,255,0.04); --sidebar-w:260px;
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;background:var(--navy);color:var(--text);display:flex;min-height:100vh;}
.sidebar{width:var(--sidebar-w);background:var(--navy-light);border-right:1px solid var(--border);position:fixed;height:100vh;display:flex;flex-direction:column;padding:0;z-index:100;overflow-y:auto;}
.sidebar-logo{padding:28px 24px 20px;border-bottom:1px solid var(--border);}
.sidebar-logo .logo-title{font-family:'Playfair Display',serif;font-size:1.4rem;color:var(--accent);letter-spacing:1px;}
.sidebar-logo .logo-sub{font-size:0.72rem;color:var(--text-muted);margin-top:3px;text-transform:uppercase;letter-spacing:2px;}
.nav-section{padding:16px 12px 8px;font-size:0.68rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:2px;padding-left:16px;}
.nav-item{display:flex;align-items:center;gap:12px;padding:12px 16px;margin:2px 8px;border-radius:10px;cursor:pointer;transition:0.2s;color:var(--text-muted);font-size:0.88rem;font-weight:500;position:relative;}
.nav-item:hover{background:var(--card);color:var(--text);}
.nav-item.active{background:rgba(79,142,247,0.15);color:var(--accent);}
.nav-item .icon{font-size:1.1rem;width:20px;text-align:center;}
.nav-item .badge{margin-left:auto;background:var(--red);color:#fff;border-radius:20px;padding:1px 7px;font-size:0.7rem;font-weight:700;}
.nav-item.preview-nav{color:#2dd4bf;}
.nav-item.preview-nav.active{background:rgba(45,212,191,0.12);color:#2dd4bf;}
.nav-item.preview-nav:hover{color:#2dd4bf;}
/* [NEW] Account approval nav item — amber colour */
.nav-item.account-nav{color:#f0b429;}
.nav-item.account-nav.active{background:rgba(240,180,41,0.12);color:#f0b429;}
.nav-item.account-nav:hover{color:#f0b429;}
.sidebar-footer{margin-top:auto;padding:16px 12px;border-top:1px solid var(--border);}
.sidebar-footer .nav-item{color:#ef4444;}
.main{margin-left:var(--sidebar-w);flex:1;padding:28px;}
.topbar{display:flex;justify-content:space-between;align-items:center;background:var(--navy-light);border:1px solid var(--border);padding:14px 24px;border-radius:14px;margin-bottom:24px;}
.topbar-title{font-family:'Playfair Display',serif;font-size:1.5rem;color:var(--text);}
.topbar-right{display:flex;align-items:center;gap:16px;}
.admin-badge{background:rgba(79,142,247,0.15);border:1px solid rgba(79,142,247,0.3);padding:6px 14px;border-radius:20px;font-size:0.82rem;color:var(--accent);}
.search-wrap input{background:var(--card);border:1px solid var(--border);color:var(--text);padding:9px 14px;border-radius:8px;width:220px;font-size:0.85rem;outline:none;}
.search-wrap input:focus{border-color:var(--accent);}
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
.stat-card{background:var(--navy-light);border:1px solid var(--border);border-radius:14px;padding:20px;display:flex;flex-direction:column;gap:8px;}
.stat-card .stat-label{font-size:0.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;}
.stat-card .stat-value{font-family:'Playfair Display',serif;font-size:2rem;}
.stat-card .stat-icon{font-size:1.6rem;margin-bottom:4px;}
.stat-card.blue .stat-value{color:var(--accent);}
.stat-card.green .stat-value{color:var(--green);}
.stat-card.orange .stat-value{color:var(--orange);}
.stat-card.red .stat-value{color:var(--red);}
.tab-panel{display:none;}
.tab-panel.active{display:block;}
.panel-card{background:var(--navy-light);border:1px solid var(--border);border-radius:14px;overflow:hidden;}
.panel-header{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;}
.panel-header h2{font-size:1rem;font-weight:600;}
table{width:100%;border-collapse:collapse;}
thead th{padding:12px 16px;font-size:0.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;text-align:left;background:rgba(255,255,255,0.02);border-bottom:1px solid var(--border);}
tbody td{padding:13px 16px;font-size:0.85rem;border-bottom:1px solid var(--border);}
tbody tr:hover{background:rgba(255,255,255,0.02);}
tbody tr:last-child td{border-bottom:none;}
.empty-row td{text-align:center;padding:32px;color:var(--text-muted);}
.badge-status{padding:3px 10px;border-radius:20px;font-size:0.72rem;font-weight:600;display:inline-block;}
.badge-available{background:rgba(34,197,94,0.15);color:#22c55e;border:1px solid rgba(34,197,94,0.3);}
.badge-borrowed{background:rgba(249,115,22,0.15);color:#f97316;border:1px solid rgba(249,115,22,0.3);}
.badge-overdue{background:rgba(239,68,68,0.15);color:#ef4444;border:1px solid rgba(239,68,68,0.3);}
.badge-returned{background:rgba(79,142,247,0.15);color:#4f8ef7;border:1px solid rgba(79,142,247,0.3);}
.badge-active{background:rgba(240,180,41,0.15);color:#f0b429;border:1px solid rgba(240,180,41,0.3);}
.badge-pending{background:rgba(240,180,41,0.15);color:#f0b429;border:1px solid rgba(240,180,41,0.3);}
.badge-paid{background:rgba(34,197,94,0.15);color:#22c55e;border:1px solid rgba(34,197,94,0.3);}
.badge-unpaid{background:rgba(239,68,68,0.15);color:#ef4444;border:1px solid rgba(239,68,68,0.3);}
.badge-approved{background:rgba(34,197,94,0.15);color:#22c55e;border:1px solid rgba(34,197,94,0.3);}
.badge-rejected-acc{background:rgba(239,68,68,0.15);color:#ef4444;border:1px solid rgba(239,68,68,0.3);}
.btn{padding:7px 14px;border-radius:7px;border:none;cursor:pointer;font-size:0.78rem;font-weight:600;transition:0.2s;display:inline-flex;align-items:center;gap:5px;}
.btn:hover{opacity:0.85;transform:translateY(-1px);}
.btn-primary{background:var(--accent);color:#fff;}
.btn-success{background:var(--green);color:#fff;}
.btn-danger{background:var(--red);color:#fff;}
.btn-warning{background:var(--orange);color:#fff;}
.btn-secondary{background:rgba(255,255,255,0.1);color:var(--text);}
.btn-sm{padding:5px 10px;font-size:0.74rem;}
.feedback-bar{padding:12px 20px;border-radius:10px;margin-bottom:20px;font-weight:600;font-size:0.88rem;}
.feedback-bar.success{background:rgba(34,197,94,0.15);color:#22c55e;border:1px solid rgba(34,197,94,0.3);}
.feedback-bar.error{background:rgba(239,68,68,0.15);color:#ef4444;border:1px solid rgba(239,68,68,0.3);}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:1000;align-items:center;justify-content:center;}
.modal-overlay.open{display:flex;}
.modal{background:var(--navy-mid);border:1px solid var(--border);border-radius:16px;padding:30px;width:460px;max-width:90vw;}
.modal h3{font-family:'Playfair Display',serif;font-size:1.3rem;margin-bottom:20px;color:var(--text);}
.form-group{margin-bottom:16px;}
.form-group label{display:block;font-size:0.78rem;color:var(--text-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:1px;}
.form-group input,.form-group select{width:100%;background:var(--navy-light);border:1px solid var(--border);color:var(--text);padding:10px 14px;border-radius:8px;font-size:0.88rem;outline:none;}
.form-group input:focus,.form-group select:focus{border-color:var(--accent);}
.modal-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:20px;}
/* Book Collection Preview */
.bc-back-btn{display:inline-flex;align-items:center;gap:8px;background:rgba(45,212,191,0.15);border:1px solid rgba(45,212,191,0.35);color:#2dd4bf;padding:9px 18px;border-radius:10px;font-size:0.85rem;font-weight:700;cursor:pointer;transition:0.2s;margin-bottom:20px;}
.bc-back-btn:hover{background:rgba(45,212,191,0.28);transform:translateX(-3px);}
.bc-admin-notice{background:rgba(45,212,191,0.08);border:1px solid rgba(45,212,191,0.25);border-radius:10px;padding:10px 18px;margin-bottom:20px;font-size:0.82rem;color:#2dd4bf;display:flex;align-items:center;gap:10px;}
.bc-search-row{display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;}
.bc-search-row input{flex:1;min-width:200px;background:rgba(255,255,255,0.06);border:1px solid var(--border);color:var(--text);padding:10px 16px;border-radius:8px;font-size:0.88rem;outline:none;}
.bc-search-row input:focus{border-color:#2dd4bf;}
.bc-search-row button{background:rgba(45,212,191,0.2);color:#2dd4bf;border:1px solid rgba(45,212,191,0.3);padding:10px 20px;border-radius:8px;font-weight:700;font-size:0.85rem;cursor:pointer;}
.bc-layout{display:flex;gap:24px;align-items:flex-start;}
.bc-books-col{flex:1;min-width:0;}
.bc-sidebar-col{width:260px;flex-shrink:0;position:sticky;top:20px;}
.bc-genre-panel{background:var(--navy-light);border:1px solid var(--border);border-radius:14px;padding:18px;margin-bottom:16px;}
.bc-genre-panel h3{font-size:0.85rem;margin-bottom:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;}
.bc-genre-list{list-style:none;display:flex;flex-direction:column;gap:6px;}
.bc-genre-list li{padding:8px 12px;background:rgba(255,255,255,0.04);border-left:3px solid rgba(255,255,255,0.1);border-radius:4px;cursor:pointer;font-size:0.82rem;font-weight:500;transition:0.2s;color:var(--text-muted);}
.bc-genre-list li:hover{background:rgba(45,212,191,0.08);border-left-color:#2dd4bf;color:var(--text);}
.bc-genre-list li.bc-active{background:rgba(45,212,191,0.12);border-left-color:#2dd4bf;color:#2dd4bf;}
.bc-stats-panel{background:var(--navy-light);border:1px solid var(--border);border-radius:14px;padding:16px 18px;display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;}
.bc-stat{text-align:center;}
.bc-stat .bcs-val{font-size:1.4rem;font-weight:700;display:block;}
.bc-stat .bcs-lbl{font-size:0.65rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.8px;}
.bcs-val.green{color:#22c55e;}.bcs-val.orange{color:#f97316;}.bcs-val.teal{color:#2dd4bf;}.bcs-val.white{color:#fff;}
.bc-book-card{background:var(--navy-light);border:1px solid var(--border);border-radius:14px;margin-bottom:14px;overflow:hidden;transition:0.2s;}
.bc-book-card:hover{border-color:rgba(45,212,191,0.3);transform:translateX(4px);}
.bc-card-header{background:rgba(45,212,191,0.08);border-bottom:1px solid var(--border);padding:12px 18px;font-weight:700;font-size:0.95rem;display:flex;justify-content:space-between;align-items:center;}
.bc-card-body{display:flex;gap:16px;padding:14px 18px;align-items:center;}
.bc-cover{width:60px;height:70px;background:rgba(0,0,0,0.3);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.8rem;flex-shrink:0;}
.bc-meta{flex:1;display:flex;flex-direction:column;gap:6px;}
.bc-meta span{background:rgba(255,255,255,0.04);border:1px solid var(--border);padding:5px 10px;border-radius:20px;font-size:0.78rem;color:var(--text-muted);}
.bc-no-results{text-align:center;padding:40px;color:var(--text-muted);background:var(--navy-light);border:1px solid var(--border);border-radius:14px;}
@media(max-width:1024px){
    :root{--sidebar-w:70px;}
    .sidebar-logo .logo-title,.sidebar-logo .logo-sub,.nav-item span,.nav-section{display:none;}
    .nav-item{justify-content:center;padding:14px;}
    .stats-grid{grid-template-columns:repeat(2,1fr);}
    .bc-layout{flex-direction:column;}
    .bc-sidebar-col{width:100%;position:static;}
}
@media(max-width:640px){.stats-grid{grid-template-columns:1fr;}}
</style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="logo-title">COLLEGE OF COMPUTING</div>
        <div class="logo-sub">Library Management System</div>
    </div>
    <div class="nav-section">Library</div>
    <div class="nav-item active" onclick="showTab('books',this)"><span class="icon">📚</span><span>Books Inventory</span></div>
    <div class="nav-item" onclick="showTab('requests',this)">
        <span class="icon">📋</span><span>Borrow Requests</span>
        <?php if($pending_count > 0): ?><span class="badge"><?= $pending_count ?></span><?php endif; ?>
    </div>
    <div class="nav-item" onclick="showTab('borrowers',this)"><span class="icon">👥</span><span>Borrowers List</span></div>
    <div class="nav-item" onclick="showTab('transactions',this)"><span class="icon">🔄</span><span>Transactions</span></div>
    <div class="nav-item" onclick="showTab('fines',this)"><span class="icon">💰</span><span>Fines</span></div>
    <!-- [NEW] Account Approval nav — amber, shows badge if pending registrations exist -->
    <div class="nav-section">Management</div>
    <div class="nav-item account-nav" onclick="showTab('accounts',this)">
        <span class="icon">🧑‍💼</span><span>Account Approval</span>
        <?php if($pending_accounts > 0): ?><span class="badge"><?= $pending_accounts ?></span><?php endif; ?>
    </div>
    <div class="nav-section">Preview</div>
    <div class="nav-item preview-nav" onclick="showTab('bookcollection',this)"><span class="icon">🔍</span><span>Book Collection</span></div>
    <div class="sidebar-footer">
        <div class="nav-item" onclick="window.location.href='logout.php'"><span class="icon">🚪</span><span>Logout</span></div>
    </div>
</aside>

<main class="main">
    <div class="topbar">
        <div class="topbar-title" id="topbarTitle">Admin Dashboard</div>
        <div class="topbar-right">
            <div class="search-wrap"><input type="text" id="adminSearch" onkeyup="filterTable()" placeholder="🔍 Search records..."></div>
            <div class="admin-badge">👤 <?= htmlspecialchars($_SESSION['name_user']) ?></div>
        </div>
    </div>

    <?php if($feedback): ?>
    <div class="feedback-bar <?= $feedback_type ?>"><?= htmlspecialchars($feedback) ?></div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card blue"><div class="stat-icon">📚</div><div class="stat-label">Total Books</div><div class="stat-value"><?= $total_books ?></div></div>
        <div class="stat-card green"><div class="stat-icon">✅</div><div class="stat-label">Available</div><div class="stat-value"><?= $available_books ?></div></div>
        <div class="stat-card orange"><div class="stat-icon">📖</div><div class="stat-label">Active Borrows</div><div class="stat-value"><?= $active_borrows ?></div></div>
        <div class="stat-card red"><div class="stat-icon">💰</div><div class="stat-label">Unpaid Fines</div><div class="stat-value">₱<?= number_format($total_fines,2) ?></div></div>
    </div>

    <!-- BOOKS TAB -->
    <div id="books" class="tab-panel active">
        <div class="panel-card">
            <div class="panel-header"><h2>📚 Books Inventory</h2><button class="btn btn-primary" onclick="openModal('addBookModal')">+ Add Book</button></div>
            <table>
                <thead><tr><th>ID</th><th>Title</th><th>Author</th><th>Genre</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if($books_result && $books_result->num_rows > 0): while($row = $books_result->fetch_assoc()): ?>
                <tr>
                    <td>#<?= $row['book_id'] ?></td>
                    <td><strong><?= htmlspecialchars($row['title']) ?></strong></td>
                    <td><?= htmlspecialchars($row['author']) ?></td>
                    <td><?= htmlspecialchars($row['genre'] ?? '—') ?></td>
                    <td><span class="badge-status badge-<?= strtolower($row['track_status']) ?>"><?= $row['track_status'] ?></span></td>
                    <td>
                        <button class="btn btn-secondary btn-sm" onclick="openEditBook(<?= $row['book_id'] ?>,'<?= addslashes($row['title']) ?>','<?= addslashes($row['author']) ?>','<?= addslashes($row['genre']??'') ?>','<?= $row['track_status'] ?>')">✏️ Edit</button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this book?')">
                            <input type="hidden" name="action" value="delete_book">
                            <input type="hidden" name="book_id" value="<?= $row['book_id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">🗑️ Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; else: ?><tr class="empty-row"><td colspan="6">No books found.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- REQUESTS TAB -->
    <div id="requests" class="tab-panel">
        <div class="panel-card">
            <div class="panel-header"><h2>📋 Pending Borrow Requests</h2></div>
            <table>
                <thead><tr><th>ID</th><th>Student</th><th>Book</th><th>Requested</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if($requests_result && $requests_result->num_rows > 0): while($row = $requests_result->fetch_assoc()): ?>
                <tr>
                    <td>#<?= $row['request_id'] ?></td>
                    <td><?= htmlspecialchars($row['name_user']) ?><br><small style="color:var(--text-muted)"><?= htmlspecialchars($row['user_id']) ?></small></td>
                    <td><?= htmlspecialchars($row['title']) ?></td>
                    <td><?= htmlspecialchars($row['request_date']) ?></td>
                    <td><span class="badge-status badge-pending">Pending</span></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="request_id" value="<?= $row['request_id'] ?>">
                            <input type="hidden" name="book_id" value="<?= $row['book_id'] ?>">
                            <input type="hidden" name="user_id" value="<?= htmlspecialchars($row['user_id']) ?>">
                            <button type="submit" class="btn btn-success btn-sm">✅ Approve</button>
                        </form>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="request_id" value="<?= $row['request_id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">❌ Reject</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; else: ?><tr class="empty-row"><td colspan="6">✅ No pending requests!</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- BORROWERS TAB -->
    <div id="borrowers" class="tab-panel">
        <div class="panel-card">
            <div class="panel-header"><h2>👥 Active Borrowers</h2></div>
            <table>
                <thead><tr><th>Student ID</th><th>Name</th><th>Book</th><th>Due Date</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if($borrowers_result && $borrowers_result->num_rows > 0): while($row = $borrowers_result->fetch_assoc()):
                    $days_over = intval($row['days_overdue']); $is_overdue = $days_over > 0; ?>
                <tr>
                    <td><?= htmlspecialchars($row['user_id']) ?></td>
                    <td><?= htmlspecialchars($row['name_user']) ?></td>
                    <td><?= htmlspecialchars($row['title']) ?></td>
                    <td><?= htmlspecialchars($row['due_date']) ?><?php if($is_overdue): ?><br><small style="color:var(--red)"><?= $days_over ?> days overdue</small><?php endif; ?></td>
                    <td><span class="badge-status <?= $is_overdue?'badge-overdue':'badge-active' ?>"><?= $is_overdue?'Overdue':'Active' ?></span></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Mark this book as returned?')">
                            <input type="hidden" name="action" value="mark_returned">
                            <input type="hidden" name="transaction_id" value="<?= $row['transaction_id'] ?>">
                            <input type="hidden" name="book_id" value="<?= $row['book_id'] ?>">
                            <button type="submit" class="btn btn-primary btn-sm">📥 Return</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; else: ?><tr class="empty-row"><td colspan="6">No active borrowers.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TRANSACTIONS TAB -->
    <div id="transactions" class="tab-panel">
        <div class="panel-card">
            <div class="panel-header"><h2>🔄 Transaction History</h2></div>
            <table>
                <thead><tr><th>ID</th><th>Borrower</th><th>Book</th><th>Issued</th><th>Due</th><th>Returned</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if($transactions_result && $transactions_result->num_rows > 0): while($row = $transactions_result->fetch_assoc()):
                    $sc = match($row['borrow_status']){'Returned'=>'badge-returned','Overdue'=>'badge-overdue',default=>'badge-active'}; ?>
                <tr>
                    <td>#<?= $row['transaction_id'] ?></td>
                    <td><?= htmlspecialchars($row['name_user']) ?></td>
                    <td><?= htmlspecialchars($row['title']) ?></td>
                    <td><?= $row['issue_date'] ?></td><td><?= $row['due_date'] ?></td>
                    <td><?= $row['return_date']??'—' ?></td>
                    <td><span class="badge-status <?= $sc ?>"><?= $row['borrow_status'] ?></span></td>
                    <td>
                        <?php if($row['borrow_status']!=='Returned'): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Mark as returned?')">
                            <input type="hidden" name="action" value="mark_returned">
                            <input type="hidden" name="transaction_id" value="<?= $row['transaction_id'] ?>">
                            <input type="hidden" name="book_id" value="<?= $row['book_id'] ?>">
                            <button type="submit" class="btn btn-success btn-sm">📥 Return</button>
                        </form>
                        <?php else: ?><span style="color:var(--text-muted);font-size:0.78rem;">Completed</span><?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; else: ?><tr class="empty-row"><td colspan="8">No transactions found.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- FINES TAB -->
    <div id="fines" class="tab-panel">
        <div class="panel-card">
            <div class="panel-header"><h2>💰 Fines Management</h2><small style="color:var(--text-muted)">₱5.00/day overdue</small></div>
            <table>
                <thead><tr><th>Fine ID</th><th>Borrower</th><th>Book</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if($fines_result && $fines_result->num_rows > 0): while($row = $fines_result->fetch_assoc()): ?>
                <tr>
                    <td>#<?= $row['fine_id'] ?></td>
                    <td><?= htmlspecialchars($row['name_user']) ?></td>
                    <td><?= htmlspecialchars($row['title']) ?></td>
                    <td style="font-weight:700;color:var(--accent-gold)">₱<?= number_format($row['fine_amount'],2) ?></td>
                    <td><span class="badge-status badge-<?= ($row['status']??'unpaid') ?>"><?= ucfirst($row['status']??'unpaid') ?></span></td>
                    <td>
                        <?php if(($row['status']??'unpaid')==='unpaid'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="mark_paid">
                            <input type="hidden" name="fine_id" value="<?= $row['fine_id'] ?>">
                            <button type="submit" class="btn btn-success btn-sm">✅ Mark Paid</button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this fine?')">
                            <input type="hidden" name="action" value="delete_fine">
                            <input type="hidden" name="fine_id" value="<?= $row['fine_id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; else: ?><tr class="empty-row"><td colspan="6">No fines recorded.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- [NEW] ACCOUNT APPROVAL TAB -->
    <!-- Students register via Homepage.php → account_status = 'pending' by default -->
    <!-- Admin reviews here and approves or rejects before the student can log in -->
    <div id="accounts" class="tab-panel">
        <div class="panel-card">
            <div class="panel-header">
                <h2>🧑‍💼 Pending Account Registrations</h2>
                <small style="color:var(--text-muted)">Students cannot log in until approved</small>
            </div>
            <table>
                <thead><tr><th>Student ID</th><th>Full Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if($pending_accounts_result && $pending_accounts_result->num_rows > 0): while($row = $pending_accounts_result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['user_id']) ?></td>
                    <td><strong><?= htmlspecialchars($row['name_user']) ?></strong></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= ucfirst($row['role']) ?></td>
                    <td><span class="badge-status badge-pending">Pending</span></td>
                    <td>
                        <!-- Approve button -->
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Approve this account? Student will be able to log in.')">
                            <input type="hidden" name="action" value="approve_account">
                            <input type="hidden" name="target_user_id" value="<?= $row['user_id'] ?>">
                            <button type="submit" class="btn btn-success btn-sm">✅ Approve</button>
                        </form>
                        <!-- Reject button -->
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Reject this registration?')">
                            <input type="hidden" name="action" value="reject_account">
                            <input type="hidden" name="target_user_id" value="<?= $row['user_id'] ?>">
                            <button type="submit" class="btn btn-warning btn-sm">🚫 Reject</button>
                        </form>
                        <!-- Delete button -->
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Permanently delete this registration?')">
                            <input type="hidden" name="action" value="delete_account">
                            <input type="hidden" name="target_user_id" value="<?= $row['user_id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; else: ?><tr class="empty-row"><td colspan="6">✅ No pending registrations!</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- [END NEW] ACCOUNT APPROVAL TAB -->

    <!-- BOOK COLLECTION PREVIEW TAB -->
    <div id="bookcollection" class="tab-panel">
        <button class="bc-back-btn" onclick="showTab('books', document.querySelector('.nav-item'))">← Back to Dashboard</button>
        <div class="bc-admin-notice">🔍 <strong>Admin Preview — Book Collection View</strong> &nbsp;|&nbsp; Read-only preview of what students see.</div>
        <div class="bc-search-row">
            <input type="text" id="bcSearch" placeholder="Search by title, author, genre or status..." oninput="bcFilter()">
            <button onclick="bcFilter()">Search</button>
        </div>
        <div class="bc-layout">
            <div class="bc-books-col" id="bcBooksCol"></div>
            <div class="bc-sidebar-col">
                <div class="bc-stats-panel">
                    <div class="bc-stat"><span class="bcs-val white"><?= count($preview_all_books) ?></span><span class="bcs-lbl">Total</span></div>
                    <div class="bc-stat"><span class="bcs-val green"><?= count(array_filter($preview_all_books,fn($b)=>$b['track_status']==='Available')) ?></span><span class="bcs-lbl">Available</span></div>
                    <div class="bc-stat"><span class="bcs-val orange"><?= count(array_filter($preview_all_books,fn($b)=>$b['track_status']!=='Available')) ?></span><span class="bcs-lbl">Borrowed</span></div>
                    <div class="bc-stat"><span class="bcs-val teal"><?= count($preview_genres) ?></span><span class="bcs-lbl">Genres</span></div>
                </div>
                <div class="bc-genre-panel">
                    <h3>🎭 Genres</h3>
                    <ul class="bc-genre-list" id="bcGenreList">
                        <li data-genre="all" class="bc-active" onclick="bcSetGenre(this)">✨ All Genres</li>
                        <?php foreach($preview_genres as $g): ?>
                        <li data-genre="<?= htmlspecialchars(strtolower($g)) ?>" onclick="bcSetGenre(this)"><?= htmlspecialchars($g) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- MODAL: ADD BOOK -->
<div class="modal-overlay" id="addBookModal"><div class="modal">
    <h3>📚 Add New Book</h3>
    <form method="POST">
        <input type="hidden" name="action" value="add_book">
        <div class="form-group"><label>Title *</label><input type="text" name="title" required placeholder="Enter book title"></div>
        <div class="form-group"><label>Author *</label><input type="text" name="author" required placeholder="Enter author name"></div>
        <div class="form-group"><label>Genre</label>
            <select name="genre"><option value="">— Select Genre —</option>
                <option value="Classic">Classic</option><option value="Fiction">Fiction</option>
                <option value="Dystopian">Dystopian</option><option value="Sci-Fi">Sci-Fi</option>
                <option value="Mystery">Mystery</option><option value="Romance">Romance</option>
                <option value="Fantasy">Fantasy</option><option value="Non-Fiction">Non-Fiction</option>
                <option value="Biography">Biography</option><option value="ISEKAI">ISEKAI</option>
                <option value="Adventure">Adventure</option>
            </select>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="closeModal('addBookModal')">Cancel</button>
            <button type="submit" class="btn btn-primary">Add Book</button>
        </div>
    </form>
</div></div>

<!-- MODAL: EDIT BOOK -->
<div class="modal-overlay" id="editBookModal"><div class="modal">
    <h3>✏️ Edit Book</h3>
    <form method="POST">
        <input type="hidden" name="action" value="edit_book">
        <input type="hidden" name="book_id" id="editBookId">
        <div class="form-group"><label>Title *</label><input type="text" name="title" id="editTitle" required></div>
        <div class="form-group"><label>Author *</label><input type="text" name="author" id="editAuthor" required></div>
        <div class="form-group"><label>Genre</label>
            <select name="genre" id="editGenre"><option value="">— Select Genre —</option>
                <option value="Classic">Classic</option><option value="Fiction">Fiction</option>
                <option value="Dystopian">Dystopian</option><option value="Sci-Fi">Sci-Fi</option>
                <option value="Mystery">Mystery</option><option value="Romance">Romance</option>
                <option value="Fantasy">Fantasy</option><option value="Non-Fiction">Non-Fiction</option>
                <option value="Biography">Biography</option><option value="ISEKAI">ISEKAI</option>
                <option value="Adventure">Adventure</option>
            </select>
        </div>
        <div class="form-group"><label>Status</label>
            <select name="track_status" id="editStatus">
                <option value="Available">Available</option>
                <option value="Borrowed">Borrowed</option>
            </select>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="closeModal('editBookModal')">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div></div>

<script>
function showTab(id, el) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    if (el) el.classList.add('active');
    const titles = {
        books:'Admin Dashboard', requests:'Borrow Requests', borrowers:'Active Borrowers',
        transactions:'Transaction History', fines:'Fines Management',
        accounts:'Account Approval', bookcollection:'🔍 Book Collection Preview'
    };
    document.getElementById('topbarTitle').textContent = titles[id] || 'Admin Dashboard';
    document.getElementById('adminSearch').style.display = (id === 'bookcollection') ? 'none' : '';
}
function filterTable() {
    const q = document.getElementById('adminSearch').value.toLowerCase();
    const rows = document.querySelectorAll('.tab-panel.active tbody tr:not(.empty-row)');
    rows.forEach(row => row.style.display = row.innerText.toLowerCase().includes(q) ? '' : 'none');
}
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(o => { o.addEventListener('click', e => { if(e.target===o) o.classList.remove('open'); }); });
function openEditBook(id, title, author, genre, status) {
    document.getElementById('editBookId').value = id;
    document.getElementById('editTitle').value  = title;
    document.getElementById('editAuthor').value = author;
    document.getElementById('editGenre').value  = genre;
    document.getElementById('editStatus').value = status;
    openModal('editBookModal');
}
setTimeout(() => { const fb = document.querySelector('.feedback-bar'); if(fb) fb.style.display='none'; }, 5000);

const bcBooks = <?= json_encode(array_map(function($b) use ($preview_emoji_map) {
    return ['id'=>(int)$b['book_id'],'title'=>$b['title'],'author'=>$b['author'],'status'=>$b['track_status'],'genre'=>$b['genre']??'','emoji'=>$preview_emoji_map[$b['genre']??'']??'📘'];
}, $preview_all_books)) ?>;
let bcCurrentGenre = 'all', bcCurrentSearch = '';
function bcEscape(str) { if(!str) return ''; return str.replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
function bcCard(book) {
    const sc = book.status==='Available'?'badge-available':'badge-borrowed';
    return `<div class="bc-book-card"><div class="bc-card-header"><span>${bcEscape(book.title)}</span><span class="badge-status ${sc}">${bcEscape(book.status)}</span></div><div class="bc-card-body"><div class="bc-cover">${book.emoji}</div><div class="bc-meta"><span>✍️ ${bcEscape(book.author)}</span><span>🏷️ ${bcEscape(book.genre||'N/A')}</span></div></div></div>`;
}
function bcRender() {
    const term = bcCurrentSearch.trim().toLowerCase();
    const filtered = bcBooks.filter(b => {
        const mg = bcCurrentGenre==='all'||(b.genre||'').toLowerCase()===bcCurrentGenre;
        const ms = !term||b.title.toLowerCase().includes(term)||b.author.toLowerCase().includes(term)||b.status.toLowerCase().includes(term)||(b.genre||'').toLowerCase().includes(term);
        return mg&&ms;
    });
    const col = document.getElementById('bcBooksCol');
    col.innerHTML = filtered.length ? filtered.map(bcCard).join('') : '<div class="bc-no-results">📭 No books match your filter.</div>';
}
function bcSetGenre(el) { bcCurrentGenre=el.getAttribute('data-genre'); document.querySelectorAll('.bc-genre-list li').forEach(li=>li.classList.remove('bc-active')); el.classList.add('bc-active'); bcRender(); }
function bcFilter() { bcCurrentSearch=document.getElementById('bcSearch').value; bcRender(); }
bcRender();
</script>
</body>
</html>