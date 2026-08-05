
<?php
session_start();
include 'db.php';

// If the user is NOT logged in, or if they are NOT an admin, kick them out
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: Homepage.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Admin Dashboard | Alexandria</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Montserrat', sans-serif; }
        
        body {
            display: flex;
            min-height: 100vh;
            background: #f4f7f6;
        }

        /* --- SIDEBAR --- */
        .sidebar {
            width: 260px;
            background: #220686; 
            color: white;
            padding: 20px;
            position: fixed;
            height: 100%;
            box-shadow: 4px 0 15px rgba(0,0,0,0.2);
            z-index: 100;
            overflow-y: auto;
        }

        .sidebar h2 { 
            font-size: 1.1rem; 
            margin-bottom: 30px; 
            border-bottom: 1px solid rgba(255,255,255,0.1); 
            padding-bottom: 10px;
            color: #3498db; 
        }
        
        .nav-menu { list-style: none; }
        .nav-menu li { 
            padding: 15px; 
            cursor: pointer; 
            transition: 0.3s; 
            border-radius: 5px; 
            margin-bottom: 5px; 
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .nav-menu li:hover, .nav-menu li.active { 
            background: #6656A1; 
            border-left: 4px solid #3498db;
        }

        /* Badge for pending requests */
        .badge {
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.75rem;
            font-weight: bold;
            margin-left: auto;
        }

        /* --- MAIN CONTENT --- */
        .main-content {
            margin-left: 260px;
            flex: 1;
            padding: 30px;
            width: calc(100% - 260px);
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border-top: 3px solid #6656A1;
        }

        .search-box input {
            padding: 10px;
            width: 300px;
            border: 1px solid #ddd;
            border-radius: 5px;
            outline-color: #3498db;
        }

        /* --- TABLES & CARDS --- */
        .data-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            display: none;
        }
        .data-card.active { display: block; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th { text-align: left; padding: 12px; background: #f8f9fa; border-bottom: 2px solid #dee2e6; color: #666; font-size: 0.85rem;}
        td { padding: 12px; border-bottom: 1px solid #eee; font-size: 0.9rem; color: #333; }

        .status { padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; }
        .available     { background: #d4edda; color: #155724; }
        .borrowed      { background: #fff3cd; color: #856404; }
        .overdue       { background: #f8d7da; color: #721c24; }
        .returned      { background: #d1ecf1; color: #0c5460; }
        .active-borrow { background: #fff3cd; color: #856404; }
        .pending       { background: #fff3cd; color: #856404; }

        /* --- BUTTONS --- */
        .btn { padding: 8px 16px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; transition: 0.2s; }
        .btn-add      { background: #220686; color: white; }
        .btn-add:hover { background: #3498db; }
        .btn-edit     { background: #3498db; color: white; margin-right: 5px; font-size: 0.8rem; }
        .btn-delete   { background: #e74c3c; color: white; font-size: 0.8rem; }
        .btn-approve  { background: #28a745; color: white; font-size: 0.8rem; margin-right: 5px; }
        .btn-reject   { background: #dc3545; color: white; font-size: 0.8rem; }
        .logout-btn   { background: none; color: #e74c3c; border: 1px solid #e74c3c; padding: 5px 10px; }

        /* Laptop View */
        @media (max-width: 1024px) {
            .sidebar { width: 80px; padding: 10px; }
            .sidebar h2, .nav-menu li span { display: none; }
            .badge { margin-left: 0; }
            .main-content { margin-left: 80px; width: calc(100% - 80px); }
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2>UC Alexandria</h2>
        <ul class="nav-menu">
            <li class="active" onclick="showTab('books', this)">
                <span>📚 Books Inventory</span>
            </li>
            <li onclick="showTab('requests', this)">
                <span>📋 Borrow Requests</span>
                <?php
                    $check_requests = "SELECT COUNT(*) as count FROM Borrow_Request_Table WHERE status = 'pending'";
                    $req_result = $conn->query($check_requests);
                    $req_row = $req_result->fetch_assoc();
                    if ($req_row['count'] > 0) {
                        echo "<span class='badge'>" . $req_row['count'] . "</span>";
                    }
                ?>
            </li>
            <li onclick="showTab('borrowers', this)">
                <span>👥 Borrowers List</span>
            </li>
            <li onclick="showTab('transactions', this)">
                <span>🔄 Transactions</span>
            </li>
            <li onclick="showTab('fines', this)">
                <span>💰 Fines Management</span>
            </li>
            <li style="margin-top: 50px;" onclick="logout()">
                <span>🚪 Logout</span>
            </li>
        </ul>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <div class="search-box">
                <input type="text" id="adminSearch" onkeyup="filterTable()" placeholder="Search records...">
            </div>
            <div class="user-info">
                <strong>Librarian:</strong> <span id="adminName" style="color: #220686;"><?php echo htmlspecialchars($_SESSION['name_user']); ?></span>
            </div>
        </div>

        <!-- ===================== BOOKS TAB ===================== -->
        <div id="books" class="data-card active">
            <div style="display:flex; justify-content: space-between; align-items: center;">
                <h3>📚 Books Collection</h3>
                <button class="btn btn-add" onclick="alert('Opening SQL INSERT form...')">+ Add New Book</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="booksBody">
                <?php
                    $sql = "SELECT * FROM Books_Table";
                    $result = $conn->query($sql);
                    if ($result && $result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            $statusClass = (strtolower($row['track_status']) == 'available') ? 'available' : 'borrowed';
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['book_id']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['title']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['author']) . "</td>";
                            echo "<td><span class='status $statusClass'>" . htmlspecialchars($row['track_status']) . "</span></td>";
                            echo "<td>
                                    <button class='btn btn-edit'>Edit</button>
                                    <button class='btn btn-delete'>Delete</button>
                                  </td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' style='text-align:center;'>No books found in the database.</td></tr>";
                    }
                ?>
                </tbody>
            </table>
        </div>

        <!-- ===================== BORROW REQUESTS TAB ===================== -->
        <div id="requests" class="data-card">
            <h3>📋 Pending Borrow Requests</h3>
            <table>
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>Student Name</th>
                        <th>Book Title</th>
                        <th>Request Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                    $sql_requests = "SELECT 
                        br.request_id,
                        br.book_id,
                        br.borrower_id,
                        br.request_date,
                        br.status,
                        b.name_user,
                        bk.title
                    FROM Borrow_Request_Table br
                    JOIN Borrower_Table b ON br.borrower_id = b.borrower_id
                    JOIN Books_Table bk ON br.book_id = bk.book_id
                    WHERE br.status = 'pending'
                    ORDER BY br.request_date DESC";

                    $res_requests = $conn->query($sql_requests);

                    if ($res_requests && $res_requests->num_rows > 0) {
                        while($row = $res_requests->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>#" . htmlspecialchars($row['request_id']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['name_user']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['title']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['request_date']) . "</td>";
                            echo "<td><span class='status pending'>Pending</span></td>";
                            echo "<td>";
                            echo "  <form method='POST' action='process_request.php' style='display:inline;'>";
                            echo "    <input type='hidden' name='request_id' value='" . $row['request_id'] . "'>";
                            echo "    <input type='hidden' name='book_id' value='" . $row['book_id'] . "'>";
                            echo "    <input type='hidden' name='borrower_id' value='" . htmlspecialchars($row['borrower_id']) . "'>";
                            echo "    <input type='hidden' name='action' value='approve'>";
                            echo "    <button type='submit' class='btn btn-approve'>✅ Approve</button>";
                            echo "  </form>";
                            echo "  <form method='POST' action='process_request.php' style='display:inline;'>";
                            echo "    <input type='hidden' name='request_id' value='" . $row['request_id'] . "'>";
                            echo "    <input type='hidden' name='action' value='reject'>";
                            echo "    <button type='submit' class='btn btn-reject'>❌ Reject</button>";
                            echo "  </form>";
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' style='text-align:center;'>✅ No pending requests!</td></tr>";
                    }
                ?>
                </tbody>
            </table>
        </div>

        <!-- ===================== BORROWERS TAB ===================== -->
        <div id="borrowers" class="data-card">
            <h3>👥 Active Borrowers</h3>
            <table>
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Book Borrowed</th>
                        <th>Due Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                    $sql_borrowers = "SELECT 
                        Borrower_Table.borrower_id,
                        Borrower_Table.name_user, 
                        Books_Table.title, 
                        Transaction_Table.due_date
                    FROM Transaction_Table
                    JOIN Borrower_Table ON Transaction_Table.borrower_id = Borrower_Table.borrower_id
                    JOIN Books_Table    ON Transaction_Table.book_id     = Books_Table.book_id
                    WHERE Transaction_Table.return_date IS NULL";

                    $res_borrowers = $conn->query($sql_borrowers);

                    if ($res_borrowers && $res_borrowers->num_rows > 0) {
                        while($row = $res_borrowers->fetch_assoc()) {
                            echo "<tr>";
                            echo "  <td>" . htmlspecialchars($row['borrower_id']) . "</td>";
                            echo "  <td>" . htmlspecialchars($row['name_user']) . "</td>";
                            echo "  <td>" . htmlspecialchars($row['title']) . "</td>";
                            echo "  <td>" . htmlspecialchars($row['due_date']) . "</td>";
                            echo "  <td><button class='btn btn-edit'>Check Transaction</button></td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' style='text-align:center;'>No active borrowers found.</td></tr>";
                    }
                ?>
                </tbody>
            </table>
        </div>

        <!-- ===================== TRANSACTIONS TAB ===================== -->
        <div id="transactions" class="data-card">
            <h3>🔄 Transaction History</h3>
            <table>
                <thead>
                    <tr>
                        <th>Transaction ID</th>
                        <th>Borrower</th>
                        <th>Book Title</th>
                        <th>Issue Date</th>
                        <th>Due Date</th>
                        <th>Return Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                    $sql_transactions = "SELECT 
                        t.transaction_id,
                        b.name_user,
                        bk.title,
                        t.issue_date,
                        t.due_date,
                        t.return_date,
                        CASE
                            WHEN t.return_date IS NOT NULL                        THEN 'Returned'
                            WHEN t.return_date IS NULL AND CURDATE() > t.due_date THEN 'Overdue'
                            ELSE 'Active'
                        END AS borrow_status
                    FROM Transaction_Table t
                    JOIN Borrower_Table b  ON t.borrower_id = b.borrower_id
                    JOIN Books_Table    bk ON t.book_id     = bk.book_id
                    ORDER BY t.transaction_id DESC";

                    $res_transactions = $conn->query($sql_transactions);

                    if ($res_transactions && $res_transactions->num_rows > 0) {
                        while($row = $res_transactions->fetch_assoc()) {
                            if ($row['borrow_status'] == 'Returned') {
                                $statusClass = 'returned';
                            } elseif ($row['borrow_status'] == 'Overdue') {
                                $statusClass = 'overdue';
                            } else {
                                $statusClass = 'active-borrow';
                            }

                            $returnDisplay = $row['return_date'] ? $row['return_date'] : '—';

                            echo "<tr>";
                            echo "  <td>#" . htmlspecialchars($row['transaction_id']) . "</td>";
                            echo "  <td>" . htmlspecialchars($row['name_user']) . "</td>";
                            echo "  <td>" . htmlspecialchars($row['title']) . "</td>";
                            echo "  <td>" . htmlspecialchars($row['issue_date']) . "</td>";
                            echo "  <td>" . htmlspecialchars($row['due_date']) . "</td>";
                            echo "  <td>" . htmlspecialchars($returnDisplay) . "</td>";
                            echo "  <td><span class='status $statusClass'>" . htmlspecialchars($row['borrow_status']) . "</span></td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7' style='text-align:center;'>No transactions found.</td></tr>";
                    }
                ?>
                </tbody>
            </table>
        </div>

        <!-- ===================== FINES TAB ===================== -->
        <div id="fines" class="data-card">
            <h3>💰 Fines &amp; Penalties</h3>
            <table>
                <thead>
                    <tr>
                        <th>Borrower</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                    $sql_fines = "SELECT b.name_user, f.fine_amount 
                                  FROM Fines_table f
                                  JOIN Transaction_Table t ON f.transaction_id = t.transaction_id
                                  JOIN Borrower_Table b    ON t.borrower_id    = b.borrower_id";
                    $res_fines = $conn->query($sql_fines);

                    if ($res_fines && $res_fines->num_rows > 0) {
                        while($row = $res_fines->fetch_assoc()) {
                            echo "<tr>";
                            echo "  <td>" . htmlspecialchars($row['name_user']) . "</td>";
                            echo "  <td>Overdue Penalty</td>";
                            echo "  <td>₱" . number_format($row['fine_amount'], 2) . "</td>";
                            echo "  <td><span class='status overdue'>Unpaid</span></td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4' style='text-align:center;'>No outstanding fines.</td></tr>";
                    }
                ?>
                </tbody>
            </table>
        </div>

    </div><!-- end main-content -->

    <script>
        // --- 1. SESSION CHECK ---
        window.onload = function() {
            const user = localStorage.getItem('currentUser');
            if(user) {
                document.getElementById('adminName').innerText = user;
            }
        };

        // --- 2. TAB SWITCHING LOGIC ---
        function showTab(tabId, clickedLi) {
            document.querySelectorAll('.data-card').forEach(card => card.classList.remove('active'));
            document.querySelectorAll('.nav-menu li').forEach(li => li.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            clickedLi.classList.add('active');
        }

        // --- 3. SEARCH FILTER ---
        function filterTable() {
            let input = document.getElementById('adminSearch').value.toLowerCase();
            let activeTab = document.querySelector('.data-card.active');
            let rows = activeTab.querySelectorAll('tbody tr');
            rows.forEach(row => {
                row.style.display = row.innerText.toLowerCase().includes(input) ? '' : 'none';
            });
        }

        function logout() {
            window.location.href = 'logout.php';
        }
    </script>
</body>
</html>