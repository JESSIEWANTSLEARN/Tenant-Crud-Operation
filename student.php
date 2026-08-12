<?php
session_start();
include 'db.php';
 
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: Homepage.php");
    exit();
}
 
$message = '';
$message_type = '';

 
// ======================== HANDLE BORROW REQUEST ========================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'request') {
    $book_id    = intval($_POST['book_id']);
    $user_id    = intval($_SESSION['user_id']);
    $book_title = htmlspecialchars($_POST['book_title'] ?? '');
    
    // Form lease specific fields mapped to original variables to retain names
    $monthly_rent = floatval($_POST['monthly_rent'] ?? 0);
    $lease_type   = htmlspecialchars($_POST['lease_type'] ?? '');
 
    // [NEW] SAFETY NET 1: Block borrow if student has any unpaid fines
    $fine_check = $conn->prepare("
        SELECT COUNT(*) as c FROM Fines_table f
        JOIN Transaction_Table t ON f.transaction_id = t.transaction_id
        WHERE t.user_id = ? AND f.status = 'unpaid'
    ");
    $fine_check->bind_param("i", $user_id);
    $fine_check->execute();
    $fine_row = $fine_check->get_result()->fetch_assoc();
    $fine_check->close();
 
    if ($fine_row['c'] > 0) {
        $message = "❌ You have unpaid balances. Please talk to the property manager to settle your account before adding a new lease.";
        $message_type = "error";
    } else {
        // Check if already actively borrowing this book
        $active_check = $conn->prepare("SELECT transaction_id FROM Transaction_Table WHERE book_id=? AND user_id=? AND return_date IS NULL");
        $active_check->bind_param("ii", $book_id, $user_id);
        $active_check->execute();
        if ($active_check->get_result()->num_rows > 0) {
            $message = "✅ Property lease has been approved! You are now managing '$book_title' under $lease_type terms";
            $message_type = "success";
            $active_check->close();
        } else {
            $active_check->close();
 
            // Check if pending request exists
            $pending_check = $conn->prepare("SELECT request_id FROM Borrow_Request_Table WHERE book_id=? AND user_id=? AND status='pending'");
            $pending_check->bind_param("ii", $book_id, $user_id);
            $pending_check->execute();
            if ($pending_check->get_result()->num_rows > 0) {
                $message = "⏳ You already have a pending lease request for this property!";
                $message_type = "error";
                $pending_check->close();
            } else {
                $pending_check->close();
 
                // Check if previously rejected (allow re-request by deleting old rejected)
                $rejected_check = $conn->prepare("SELECT request_id FROM Borrow_Request_Table WHERE book_id=? AND user_id=? AND status='rejected'");
                $rejected_check->bind_param("ii", $book_id, $user_id);
                $rejected_check->execute();
                if ($rejected_check->get_result()->num_rows > 0) {
                    $rejected_check->close();
                    $del = $conn->prepare("DELETE FROM Borrow_Request_Table WHERE book_id=? AND user_id=? AND status='rejected'");
                    $del->bind_param("ii", $book_id, $user_id);
                    $del->execute();
                    $del->close();
                } else {
                    $rejected_check->close();
                }
 
                // Check availability
                $avail = $conn->query("SELECT track_status FROM Books_Table WHERE book_id=$book_id")->fetch_assoc();
                if (!$avail || $avail['track_status'] !== 'Available') {
                    $message = "❌ This property is currently unavailable for lease. Check back later!";
                    $message_type = "error";
                } else {
                    $ins = $conn->prepare("INSERT INTO Borrow_Request_Table (book_id, user_id, request_date, status) VALUES (?, ?, CURDATE(), 'pending')");
                    $ins->bind_param("ii", $book_id, $user_id);
                    if ($ins->execute()) {
                        $message = "✅ Lease application sent! Management will review your submission for '$book_title' ($lease_type - ₱" . number_format($monthly_rent, 2) . "/mo)";
                        $message_type = "success";
                    } else {
                        $message = "❌ Error: " . $conn->error;
                        $message_type = "error";
                    }
                    $ins->close();
                }
            }
        }
    }


// ✅ POST/REDIRECT/GET PATTERN
    // Store message in session and redirect to clear POST data
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type']    = $message_type;
    
    header("Location: Student.php");
    exit();
}

// ✅ Read flash message from session (if it exists)
if (isset($_SESSION['flash_message'])) {
    $message      = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_type'];
    
    // Clear the flash message so it only shows once
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}
 
// ======================== FETCH BOOKS ========================
$books_result = $conn->query("SELECT * FROM Books_Table ORDER BY title ASC");
$all_books = [];
if ($books_result && $books_result->num_rows > 0) {
    while ($row = $books_result->fetch_assoc()) $all_books[] = $row;
}
 
// My pending requests (disable buttons)
$my_pending = [];
$stmt = $conn->prepare("SELECT book_id FROM Borrow_Request_Table WHERE user_id=? AND status='pending'");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$r = $stmt->get_result();
while ($row = $r->fetch_assoc()) $my_pending[] = $row['book_id'];
$stmt->close();
 
// My active borrows (disable buttons)
$my_borrows = [];
$stmt = $conn->prepare("SELECT book_id FROM Transaction_Table WHERE user_id=? AND return_date IS NULL");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$r = $stmt->get_result();
while ($row = $r->fetch_assoc()) $my_borrows[] = $row['book_id'];
$stmt->close();
 
// My transactions
$my_transactions = [];
$stmt = $conn->prepare("SELECT t.*, bk.title FROM Transaction_Table t JOIN Books_Table bk ON t.book_id=bk.book_id WHERE t.user_id=? ORDER BY t.transaction_id DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$r = $stmt->get_result();
while ($row = $r->fetch_assoc()) $my_transactions[] = $row;
$stmt->close();
 
// My pending requests full details
$my_pending_full = [];
$pending_stmt = $conn->prepare("SELECT br.*, bk.title FROM Borrow_Request_Table br JOIN Books_Table bk ON br.book_id=bk.book_id WHERE br.user_id=? AND br.status='pending' ORDER BY br.request_date DESC");
$pending_stmt->bind_param("i", $_SESSION['user_id']);
$pending_stmt->execute();
$pr = $pending_stmt->get_result();
while ($row = $pr->fetch_assoc()) $my_pending_full[] = $row;
$pending_stmt->close();
 
// [NEW] My unpaid fines — shown in dashboard
$my_fines = [];
$fines_stmt = $conn->prepare("
    SELECT f.fine_id, f.fine_amount, f.status, bk.title, t.due_date, t.return_date
    FROM Fines_table f
    JOIN Transaction_Table t ON f.transaction_id = t.transaction_id
    JOIN Books_Table bk ON t.book_id = bk.book_id
    WHERE t.user_id = ? AND f.status = 'unpaid'
    ORDER BY f.fine_id DESC
");
$fines_stmt->bind_param("i", $_SESSION['user_id']);
$fines_stmt->execute();
$fr = $fines_stmt->get_result();
while ($row = $fr->fetch_assoc()) $my_fines[] = $row;
$fines_stmt->close();
$total_my_fines = array_sum(array_column($my_fines, 'fine_amount'));
 
// Unread notifications (approved/rejected requests not yet seen)
$notifications = [];
$notif_stmt = $conn->prepare("
    SELECT br.status, br.request_id, bk.title
    FROM Borrow_Request_Table br
    JOIN Books_Table bk ON br.book_id = bk.book_id
    WHERE br.user_id = ? AND br.status != 'pending' AND br.is_read = 0
    ORDER BY br.request_date DESC
");
$notif_stmt->bind_param("i", $_SESSION['user_id']);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
while ($row = $notif_result->fetch_assoc()) $notifications[] = $row;
$notif_stmt->close();
 
// Mark notifications as read
if (count($notifications) > 0) {
    $mark_stmt = $conn->prepare("UPDATE Borrow_Request_Table SET is_read=1 WHERE user_id=? AND status != 'pending' AND is_read=0");
    $mark_stmt->bind_param("i", $_SESSION['user_id']);
    $mark_stmt->execute();
    $mark_stmt->close();
}
 
// Stats
$total_books     = count($all_books);
$avail_count     = count(array_filter($all_books, fn($b) => $b['track_status'] === 'Available'));
$borrow_count    = $total_books - $avail_count;
$my_borrow_count = count($my_transactions);
$active_only     = array_filter($my_transactions, fn($t) => !$t['return_date']);
$has_unpaid      = count($my_fines) > 0;
 
// Emoji map for genres
$emoji_map = [
    'Fiction'=>'🏢','Classic'=>'🏠','Dystopian'=>'🏬','Sci-Fi'=>'🏨',
    'Mystery'=>'🏡','Romance'=>'🏘️','Fantasy'=>'🏰','Non-Fiction'=>'🏗️',
    'Biography'=>'👤','Isekai'=>'⚡','Adventure'=>'🗺️',
];
$genre_icons = [
    'Fiction'=>'🏢','Classic'=>'🏠','Dystopian'=>'🏬','Sci-Fi'=>'🏨',
    'Mystery'=>'🏡','Romance'=>'🏘️','Fantasy'=>'🏰','Non-Fiction'=>'🏗️',
    'Biography'=>'👤','Isekai'=>'⚡','Adventure'=>'🗺️',
];
 
$genres_in_db = array_unique(array_filter(array_column($all_books, 'genre')));
sort($genres_in_db);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property Catalog | Prime Realty Management</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background-image: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('https://images.freeimages.com/images/large-previews/701/my-university-library-3-1442034.jpg');
            background-size: cover; background-position: center;
            background-attachment: fixed; font-family: 'Segoe UI', Arial, sans-serif;
            color: #D9D9D9; min-height: 100vh; display: flex; flex-direction: column;
        }
        header {
            background: rgba(102,86,161,0.95); padding: 15px 30px;
            display: flex; flex-wrap: wrap; align-items: center;
            justify-content: space-between; gap: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3); position: sticky; top: 0; z-index: 100;
        }
        header > div:first-child { font-weight: bold; font-size: 1.1em; letter-spacing: 1px; flex: 1; }
        nav { display: flex; gap: 15px; align-items: center; }
        nav a, nav span { color: white; text-decoration: none; font-weight: 600; transition: 0.3s; font-size: 0.95em; }
        nav a:hover { color: #a8e6ff; }
        .login-btn { background: #220686; padding: 10px 20px; border-radius: 5px; border: 1px solid rgba(255,255,255,0.2); }
        .logout-btn { background: #e74c3c; padding: 10px 20px; border-radius: 5px; border: none; cursor: pointer; }
        .search-box { display: flex; gap: 10px; flex: 1; min-width: 250px; max-width: 400px; }
        .search-box input { flex: 1; background: rgba(255,255,255,0.2); color: #D9D9D9; padding: 12px; border-radius: 5px; border: none; outline: none; }
        .search-box input::placeholder { color: rgba(255,255,255,0.6); }
        .search-box button { background: #31323E; color: #D9D9D9; padding: 10px 25px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
 
        /* MESSAGE ALERT */
        .message-alert { position: fixed; top: 20px; right: 20px; padding: 15px 25px; border-radius: 8px; font-weight: bold; animation: slideIn 0.3s ease-out; z-index: 9999; max-width: 400px; }
        .message-alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message-alert.error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        @keyframes slideIn { from { transform: translateX(400px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
 
        /* NOTIFICATION PANEL */
        .notif-panel { margin-bottom: 20px; display: flex; flex-direction: column; gap: 10px; }
        .notif-item { display: flex; align-items: flex-start; gap: 12px; padding: 14px 18px; border-radius: 12px; font-size: 0.88rem; font-weight: 500; animation: slideIn 0.4s ease-out; line-height: 1.5; }
        .notif-item.approved { background: rgba(34,197,94,0.12); border: 1px solid rgba(34,197,94,0.35); color: #34d399; }
        .notif-item.rejected { background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.35); color: #f87171; }
        .notif-icon { font-size: 1.2rem; flex-shrink: 0; margin-top: 1px; }
        .notif-text { flex: 1; }
        .notif-label { display: inline-block; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; padding: 2px 8px; border-radius: 20px; margin-bottom: 4px; }
        .notif-item.approved .notif-label { background: rgba(34,197,94,0.2); color: #34d399; border: 1px solid rgba(34,197,94,0.3); }
        .notif-item.rejected .notif-label { background: rgba(239,68,68,0.2); color: #f87171; border: 1px solid rgba(239,68,68,0.3); }
        .notif-book { font-size: 0.85rem; font-weight: 600; }
 
        /* CAROUSEL */
        .topContent { padding: 40px 20px; background: rgba(0,0,0,0.3); border-bottom: 1px solid rgba(102,86,161,0.3); }
        .topContent h1 { text-align: center; margin-bottom: 30px; text-shadow: 2px 2px 10px rgba(0,0,0,0.5); font-size: 2em; }
        .carousel-container { position: relative; display: flex; align-items: center; gap: 10px; }
        .book-cards-grid { display: flex; gap: 25px; overflow-x: auto; scroll-behavior: smooth; padding: 10px 5px 20px 5px; flex: 1; scrollbar-width: thin; }
        .book-cards-grid::-webkit-scrollbar { height: 6px; }
        .book-cards-grid::-webkit-scrollbar-track { background: #2a2343; border-radius: 10px; }
        .book-cards-grid::-webkit-scrollbar-thumb { background: #b9a9ff; border-radius: 10px; }
        .prev, .next { cursor: pointer; font-size: 2rem; font-weight: bold; background: rgba(96,81,155,0.7); width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: 0.3s; user-select: none; flex-shrink: 0; }
        .prev:hover, .next:hover { background: #8f7bcb; transform: scale(1.05); }
 
        /* CAROUSEL CARD */
        .book-card { background: rgba(255,255,255,0.08); backdrop-filter: blur(15px); border-radius: 15px; border: 2px solid rgba(255,255,255,0.15); box-shadow: 0 15px 35px rgba(0,0,0,0.4); overflow: hidden; transition: all 0.3s ease; width: 200px; flex-shrink: 0; cursor: pointer; }
        .book-card:hover { transform: translateY(-8px); background: rgba(255,255,255,0.12); border-color: rgba(102,86,161,0.8); }
        .book-card .book-card-title { background: rgba(102,86,161,0.6); border-bottom: 1px solid rgba(255,255,255,0.1); padding: 12px; text-align: center; font-weight: bold; font-size: 0.85rem; }
        .book-card .book-cover { display: flex; align-items: center; justify-content: center; min-height: 140px; background: rgba(0,0,0,0.3); padding: 15px; font-size: 3rem; }
        .book-card .book-card-meta { display: flex; flex-direction: column; padding: 12px; gap: 8px; background: rgba(0,0,0,0.2); }
        .book-card .book-card-meta span { background: rgba(102,86,161,0.5); padding: 6px 10px; border-radius: 20px; text-align: center; font-size: 0.78rem; }
 
        /* LAYOUT */
        .bottomContent { display: flex; gap: 40px; padding: 50px 20px; flex: 1; align-items: flex-start; justify-content: center; flex-wrap: wrap; }
        .left-column { flex: 1; min-width: 300px; max-width: 900px; }
        .left-column h1 { margin-bottom: 20px; text-shadow: 2px 2px 10px rgba(0,0,0,0.5); font-size: 2em; }
 
        /* BOOK CARD LEFT */
        .left-column .book-card { display: flex; flex-direction: column; width: 100%; margin-bottom: 20px; background: rgba(20,15,40,0.85); backdrop-filter: blur(12px); border-radius: 20px; border: 1px solid rgba(255,255,255,0.2); transition: 0.2s; cursor: default; }
        .left-column .book-card:hover { transform: translateX(6px); background: rgba(35,28,65,0.9); }
        .left-column .book-card-title { background: rgba(102,86,161,0.7); border-radius: 20px 20px 0 0; text-align: left; padding: 12px 20px; font-size: 1.1rem; font-weight: bold; border-bottom: 1px solid rgba(255,255,255,0.2); }
        .left-column .card-row { display: flex; gap: 20px; padding: 15px 20px; align-items: center; flex-wrap: wrap; }
        .left-column .book-cover { flex: 0 0 100px; min-height: 120px; background: rgba(0,0,0,0.4); border-radius: 12px; display: flex; align-items: center; justify-content: center; padding: 8px; font-size: 2.8rem; }
        .left-column .book-card-meta { flex: 1; padding: 0; gap: 12px; display: flex; flex-direction: column; }
        .left-column .book-card-meta span { background: rgba(102,86,161,0.6); padding: 8px 12px; border-radius: 30px; font-size: 0.9rem; text-align: left; }
        
        /* LEASE FORM STYLES */
        .lease-form-group { display: flex; gap: 10px; align-items: center; background: rgba(0,0,0,0.2); padding: 8px 12px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); }
        .lease-form-group label { font-size: 0.85rem; font-weight: bold; color: #a8e6ff; min-width: 90px; }
        .lease-form-group input, .lease-form-group select { flex: 1; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 5px; color: white; padding: 6px 10px; font-size: 0.85rem; outline: none; }
        .lease-form-group select option { background: #221a3b; color: white; }

        .borrow-button-container { display: flex; gap: 10px; margin-top: 10px; }
        .btn-borrow { flex: 1; padding: 10px 16px; background: #28a745; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; transition: 0.3s; font-size: 0.9rem; }
        .btn-borrow:hover:not(:disabled) { background: #218838; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(40,167,69,0.4); }
        .btn-borrow:disabled { background: #6c757d; cursor: not-allowed; transform: none; }
 
        /* RIGHT COLUMN */
        .right-column { flex: 1; min-width: 280px; max-width: 370px; display: flex; flex-direction: column; gap: 16px; position: sticky; top: 80px; }
 
        /* DASHBOARD PANEL */
        .dashboard-panel { background: rgba(255,255,255,0.08); backdrop-filter: blur(15px); border-radius: 15px; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 8px 20px rgba(0,0,0,0.3); overflow: hidden; }
        .dash-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 1px; background: rgba(255,255,255,0.06); border-bottom: 1px solid rgba(255,255,255,0.08); }
        .dash-stat-item { background: rgba(20,15,50,0.7); padding: 14px 16px; text-align: center; }
        .dash-stat-item .ds-val { font-size: 1.5rem; font-weight: bold; display: block; line-height: 1; }
        .dash-stat-item .ds-lbl { font-size: 0.65rem; color: rgba(217,217,217,0.55); text-transform: uppercase; letter-spacing: 0.8px; margin-top: 4px; display: block; }
        .ds-val.green  { color: #34d399; }
        .ds-val.orange { color: #fb923c; }
        .ds-val.purple { color: #b9a9ff; }
        .ds-val.white  { color: #fff; }
        .ds-val.red    { color: #f87171; }
        .dash-section-header { padding: 10px 16px 8px; font-size: 0.72rem; font-weight: 700; color: rgba(217,217,217,0.5); text-transform: uppercase; letter-spacing: 1.2px; border-bottom: 1px solid rgba(255,255,255,0.05); background: rgba(0,0,0,0.15); }
        .dash-borrow-list { padding: 8px 12px 12px; display: flex; flex-direction: column; gap: 7px; }
        .dash-borrow-item { background: rgba(102,86,161,0.2); border-radius: 8px; padding: 9px 12px; }
        .dash-borrow-item .dbi-title { font-size: 0.82rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .dash-borrow-item .dbi-due { font-size: 0.72rem; color: rgba(217,217,217,0.55); margin-top: 2px; }
        .dash-borrow-item .dbi-due.overdue { color: #f87171; font-weight: 600; }
        .dash-empty { padding: 10px 16px 14px; font-size: 0.78rem; color: rgba(217,217,217,0.38); font-style: italic; }
        .dash-pending-list { padding: 8px 12px 12px; display: flex; flex-direction: column; gap: 7px; }
        .dash-pending-item { background: rgba(240,180,41,0.08); border: 1px solid rgba(240,180,41,0.2); border-radius: 8px; padding: 9px 12px; display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .dash-pending-item .dpi-inner { flex: 1; min-width: 0; }
        .dash-pending-item .dpi-title { font-size: 0.82rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .dash-pending-item .dpi-date { font-size: 0.7rem; color: rgba(217,217,217,0.45); margin-top: 1px; }
        .badge-pending-sm { background: rgba(240,180,41,0.12); color: #f0b429; border: 1px solid rgba(240,180,41,0.25); border-radius: 20px; padding: 2px 8px; font-size: 0.64rem; font-weight: 700; white-space: nowrap; flex-shrink: 0; }
 
        /* [NEW] FINES inside dashboard */
        .dash-fines-list { padding: 8px 12px 12px; display: flex; flex-direction: column; gap: 7px; }
        .dash-fine-item { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25); border-radius: 8px; padding: 9px 12px; }
        .dash-fine-item .dfi-title { font-size: 0.82rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #f87171; }
        .dash-fine-item .dfi-amount { font-size: 0.78rem; color: #fca5a5; margin-top: 2px; font-weight: 700; }
        .dash-fine-notice { padding: 10px 12px 14px; font-size: 0.75rem; color: #fca5a5; line-height: 1.5; background: rgba(239,68,68,0.06); border-top: 1px solid rgba(239,68,68,0.15); }
        .dash-fine-total { padding: 8px 12px; font-size: 0.8rem; font-weight: 700; color: #f87171; display: flex; justify-content: space-between; border-bottom: 1px solid rgba(239,68,68,0.15); background: rgba(239,68,68,0.08); }
 
        /* GENRES */
        .genres-section { background: rgba(255,255,255,0.08); backdrop-filter: blur(15px); border-radius: 15px; border: 1px solid rgba(255,255,255,0.1); padding: 20px; box-shadow: 0 8px 20px rgba(0,0,0,0.3); }
        .genres-section h2 { margin-bottom: 14px; font-size: 1.1em; }
        .genres-section ul { list-style: none; display: flex; flex-direction: column; gap: 8px; }
        .genres-section li { padding: 10px 14px; background: rgba(102,86,161,0.3); border-left: 3px solid rgba(102,86,161,0.8); border-radius: 4px; transition: all 0.3s ease; cursor: pointer; font-weight: 500; font-size: 0.88rem; }
        .genres-section li:hover { background: rgba(102,86,161,0.6); border-left-color: #a8e6ff; transform: translateX(8px); }
        .active-genre { background: rgba(143,123,203,0.8) !important; border-left-color: white !important; }
 
        footer { background: rgba(96,81,155,0.95); padding: 25px; text-align: center; margin-top: auto; font-size: 0.9em; }
        @media (max-width: 768px) { .left-column .card-row { flex-direction: column; } .right-column { position: static; } .prev, .next { width: 36px; height: 36px; font-size: 1.5rem; } }
        @media (max-width: 480px) { .book-card { width: 160px; } }
    </style>
</head>
<body>
 
<?php if ($message): ?>
<div class="message-alert <?= $message_type ?>" id="messageAlert">
    <?= $message ?>
</div>
<script>
    setTimeout(() => { const el = document.getElementById('messageAlert'); if(el) el.remove(); }, 5000);
</script>
<?php endif; ?>
 
<header>
    <div><strong>PRIME REALTY MANAGEMENT | PROPERTY RENTAL SYSTEM</strong></div>
    <nav>
        <a href="FAQ.php">FAQ</a>
        <span>Welcome, <?= htmlspecialchars($_SESSION['name_user']) ?></span>
        <a href="logout.php" class="login-btn logout-btn">Logout</a>
    </nav>
    <div class="search-box">
        <input type="text" id="searchInput" placeholder="Search by property, location, or status...">
        <button id="searchBtn">Search</button>
    </div>
</header>
 
<!-- CAROUSEL -->
<section class="topContent">
    <h1>🏢 Featured Properties</h1>
    <div class="carousel-container">
        <a class="prev" id="carouselPrev">&#10094;</a>
        <div class="book-cards-grid" id="carouselGrid"></div>
        <a class="next" id="carouselNext">&#10095;</a>
    </div>
</section>
 
<section class="bottomContent">
    <section class="left-column">
        <h1>🏡 Available Properties Catalog</h1>
 
        <!-- NOTIFICATION PANEL -->
        <?php if (count($notifications) > 0): ?>
        <div class="notif-panel">
            <?php foreach ($notifications as $notif):
                $is_approved = $notif['status'] === 'approved';
                $type  = $is_approved ? 'approved' : 'rejected';
                $icon  = $is_approved ? '✅' : '❌';
                $label = $is_approved ? 'Approved' : 'Rejected';
                $msg   = $is_approved
                    ? "Your lease application has been <strong>approved</strong>! Please check details for moving in."
                    : "Your lease application has been <strong>rejected</strong> by property management.";
            ?>
            <div class="notif-item <?= $type ?>">
                <span class="notif-icon"><?= $icon ?></span>
                <div class="notif-text">
                    <span class="notif-label"><?= $label ?></span>
                    <div class="notif-book"><?= htmlspecialchars($notif['title']) ?></div>
                    <div style="font-size:0.78rem;margin-top:3px;opacity:0.8;"><?= $msg ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
 
        <div id="booksContainer"></div>
    </section>
 
    <section class="right-column">
        <div class="dashboard-panel">
 
            <!-- STATS (4 boxes) -->
            <div class="dash-stats">
                <div class="dash-stat-item">
                    <span class="ds-val white"><?= $total_books ?></span>
                    <span class="ds-lbl">Total Units</span>
                </div>
                <div class="dash-stat-item">
                    <span class="ds-val green"><?= $avail_count ?></span>
                    <span class="ds-lbl">Vacant</span>
                </div>
                <div class="dash-stat-item">
                    <span class="ds-val orange"><?= $borrow_count ?></span>
                    <span class="ds-lbl">Occupied</span>
                </div>
                <div class="dash-stat-item">
                    <span class="ds-val purple"><?= $my_borrow_count ?></span>
                    <span class="ds-lbl">My Leases</span>
                </div>
            </div>
 
            <!-- ACTIVE BORROWS -->
            <div class="dash-section-header">📌 My Active Leases</div>
            <?php if (count($active_only) > 0): ?>
            <div class="dash-borrow-list">
                <?php foreach ($active_only as $txn):
                    $is_overdue = strtotime($txn['due_date']) < time(); ?>
                <div class="dash-borrow-item">
                    <div class="dbi-title" title="<?= htmlspecialchars($txn['title']) ?>"><?= htmlspecialchars($txn['title']) ?></div>
                    <div class="dbi-due <?= $is_overdue ? 'overdue' : '' ?>">
                        Due: <?= htmlspecialchars($txn['due_date']) ?><?= $is_overdue ? ' ⚠️ Payment Overdue' : '' ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="dash-empty">No active unit leases.</div>
            <?php endif; ?>
 
            <!-- PENDING REQUESTS -->
            <div class="dash-section-header">⏳ My Pending Applications (<?= count($my_pending_full) ?>)</div>
            <?php if (count($my_pending_full) > 0): ?>
            <div class="dash-pending-list">
                <?php foreach ($my_pending_full as $pr): ?>
                <div class="dash-pending-item">
                    <div class="dpi-inner">
                        <div class="dpi-title" title="<?= htmlspecialchars($pr['title']) ?>"><?= htmlspecialchars($pr['title']) ?></div>
                        <div class="dpi-date"><?= htmlspecialchars($pr['request_date']) ?></div>
                    </div>
                    <span class="badge-pending-sm">Pending</span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="dash-empty">No pending lease applications.</div>
            <?php endif; ?>
 
            <!-- [NEW] UNPAID FINES SECTION — only shows if student has fines -->
            <?php if ($has_unpaid): ?>
            <div class="dash-section-header" style="color:#f87171;">💰 My Unpaid Balances</div>
            <div class="dash-fines-list">
                <?php foreach ($my_fines as $fine): ?>
                <div class="dash-fine-item">
                    <div class="dfi-title" title="<?= htmlspecialchars($fine['title']) ?>"><?= htmlspecialchars($fine['title']) ?></div>
                    <div class="dfi-amount">₱<?= number_format($fine['fine_amount'], 2) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="dash-fine-total">
                <span>Total Balance</span>
                <span>₱<?= number_format($total_my_fines, 2) ?></span>
            </div>
            <div class="dash-fine-notice">
                ⚠️ You cannot submit new lease records while you have unpaid balances. Please settle your account with management.
            </div>
            <?php endif; ?>
 
        </div>
    </section>
</section>
 
<footer>
    <p>&copy; 2026 Prime Realty Management Systems. All rights reserved.</p>
</footer>
 
<script>
const booksData = <?= json_encode(array_map(function($b) use ($emoji_map) {
    return [
        'id'         => (int)$b['book_id'],
        'title'      => $b['title'],
        'author'     => $b['author'],
        'status'     => $b['track_status'],
        'genre'      => $b['genre'] ?? '',
        'coverEmoji' => $emoji_map[$b['genre'] ?? ''] ?? '🏢',
    ];
}, $all_books)) ?>;
 
const myPending  = <?= json_encode(array_map('intval', $my_pending)) ?>;
const myBorrows  = <?= json_encode(array_map('intval', $my_borrows)) ?>;
const hasUnpaid  = <?= $has_unpaid ? 'true' : 'false' ?>;
 
const recommendedBooks = booksData.filter(b => b.status === 'Available').slice(0, 9);
let currentSearch = "";
let currentGenre  = "all";
 
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}
 
function getButtonState(book) {
    const id = parseInt(book.id);
    if (hasUnpaid)               return { disabled: true, text: '💰 Settle Balance First' };
    if (myBorrows.includes(id))  return { disabled: true, text: '🏡 Already Leasing' };
    if (myPending.includes(id))  return { disabled: true, text: '⏳ Application Pending' };
    if (book.status !== 'Available') return { disabled: true, text: '❌ Unit Unavailable' };
    return { disabled: false, text: '📤 Add Lease Record' };
}
 
function generateHorizontalCard(book) {
    const btn = getButtonState(book);
    return `
        <div class="book-card" data-genre="${(book.genre||'').toLowerCase()}">
            <div class="book-card-title">${escapeHtml(book.title)}</div>
            <div class="card-row">
                <div class="book-cover">${book.coverEmoji}</div>
                <div class="book-card-meta">
                    <span>✍️ Landlord/Owner: ${escapeHtml(book.author)}</span>
                    <span>📌 Status: ${escapeHtml(book.status)}</span>
                    <span>🏷️ Property Type: ${escapeHtml(book.genre || 'N/A')}</span>
                    
                    <!-- Form for monthly rent and lease type inputs -->
                    <form method="POST" action="Student.php" style="width:100%; margin:0; display:flex; flex-direction:column; gap:8px;">
                        <input type="hidden" name="action" value="request">
                        <input type="hidden" name="book_id" value="${book.id}">
                        <input type="hidden" name="book_title" value="${escapeHtml(book.title)}">
                        
                        <div class="lease-form-group">
                            <label>Monthly Rent:</label>
                            <input type="number" name="monthly_rent" step="0.01" placeholder="e.g. 15000" required>
                        </div>
                        
                        <div class="lease-form-group">
                            <label>Lease Type:</label>
                            <select name="lease_type" required>
                                <option value="">Select Lease Type</option>
                                <option value="Fixed Term">Fixed Term</option>
                                <option value="Month-to-Month">Month-to-Month</option>
                                <option value="Commercial">Commercial</option>
                                <option value="Residential">Residential</option>
                            </select>
                        </div>

                        <div class="borrow-button-container">
                            <button type="submit" class="btn-borrow" ${btn.disabled ? 'disabled' : ''}>${btn.text}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>`;
}
 
function generateVerticalCard(book) {
    return `
        <div class="book-card">
            <div class="book-card-title">${escapeHtml(book.title)}</div>
            <div class="book-cover">${book.coverEmoji}</div>
            <div class="book-card-meta">
                <span>✍️ ${escapeHtml(book.author)}</span>
                <span>📌 ${escapeHtml(book.status)}</span>
                <span>🏷️ ${escapeHtml(book.genre || 'N/A')}</span>
            </div>
        </div>`;
}
 
function renderCarousel() {
    const c = document.getElementById('carouselGrid');
    if (!c) return;
    c.innerHTML = recommendedBooks.length
        ? recommendedBooks.map(generateVerticalCard).join('')
        : '<p style="padding:20px;color:#aaa;">No available properties right now.</p>';
}
 
function filterAndRender() {
    const term = currentSearch.trim().toLowerCase();
    const filtered = booksData.filter(book => {
        const matchGenre  = currentGenre === 'all' || (book.genre||'').toLowerCase() === currentGenre.toLowerCase();
        const matchSearch = !term || book.title.toLowerCase().includes(term) || book.author.toLowerCase().includes(term) || book.status.toLowerCase().includes(term) || (book.genre||'').toLowerCase().includes(term);
        return matchGenre && matchSearch;
    });
    const c = document.getElementById('booksContainer');
    if (!c) return;
    c.innerHTML = filtered.length
        ? filtered.map(generateHorizontalCard).join('')
        : `<div style="text-align:center;padding:40px;background:rgba(0,0,0,0.5);border-radius:30px;">📭 No properties match your search or filter.</div>`;
}
 
document.getElementById('searchInput')?.addEventListener('input', () => { currentSearch = document.getElementById('searchInput').value; filterAndRender(); });
document.getElementById('searchBtn')?.addEventListener('click', () => { currentSearch = document.getElementById('searchInput').value; filterAndRender(); });
document.querySelectorAll('#genreList li').forEach(li => {
    li.addEventListener('click', () => {
        currentGenre = li.getAttribute('data-genre');
        document.querySelectorAll('#genreList li').forEach(x => x.classList.remove('active-genre'));
        li.classList.add('active-genre');
        filterAndRender();
    });
});
document.getElementById('carouselPrev')?.addEventListener('click', () => { document.getElementById('carouselGrid').scrollBy({ left: -280, behavior: 'smooth' }); });
document.getElementById('carouselNext')?.addEventListener('click', () => { document.getElementById('carouselGrid').scrollBy({ left: 280, behavior: 'smooth' }); });
 
renderCarousel();
filterAndRender();
</script>
</body>
</html>