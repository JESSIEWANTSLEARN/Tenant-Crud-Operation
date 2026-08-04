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
    $user_id    = $_SESSION['user_id'];
    $book_title = htmlspecialchars($_POST['book_title'] ?? '');

    // Check if book is still available
    $avail = $conn->query("SELECT track_status FROM Books_Table WHERE book_id=$book_id")->fetch_assoc();
    if (!$avail || $avail['track_status'] !== 'Available') {
        $message = "❌ This book is no longer available.";
        $message_type = "error";
    } else {
        // Check for existing pending request
        $stmt = $conn->prepare("SELECT request_id FROM Borrow_Request_Table WHERE book_id=? AND user_id=? AND status='pending'");
        $stmt->bind_param("is", $book_id, $user_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $message = "⏳ You already have a pending request for this book!";
            $message_type = "error";
        } else {
            // Check if already borrowing this book (active transaction)
            $stmt2 = $conn->prepare("SELECT transaction_id FROM Transaction_Table WHERE book_id=? AND user_id=? AND return_date IS NULL");
            $stmt2->bind_param("is", $book_id, $user_id);
            $stmt2->execute();
            if ($stmt2->get_result()->num_rows > 0) {
                $message = "❌ You are already borrowing this book!";
                $message_type = "error";
            } else {
                $ins = $conn->prepare("INSERT INTO Borrow_Request_Table (book_id, user_id, request_date, status) VALUES (?, ?, CURDATE(), 'pending')");
                $ins->bind_param("is", $book_id, $user_id);
                if ($ins->execute()) {
                    $message = "✅ Request sent for '$book_title'! Waiting for admin approval.";
                    $message_type = "success";
                } else {
                    $message = "❌ Error: " . $conn->error;
                    $message_type = "error";
                }
                $ins->close();
            }
            $stmt2->close();
        }
        $stmt->close();
    }
}

// ======================== FETCH BOOKS FROM DB ========================
$books_result = $conn->query("SELECT * FROM Books_Table ORDER BY title ASC");
$all_books = [];
if ($books_result && $books_result->num_rows > 0) {
    while ($row = $books_result->fetch_assoc()) {
        $all_books[] = $row;
    }
}

// Get my active borrows and pending requests (to disable buttons)
$my_borrows = [];
$stmt = $conn->prepare("SELECT book_id FROM Transaction_Table WHERE user_id=? AND return_date IS NULL");
$stmt->bind_param("s", $_SESSION['user_id']);
$stmt->execute();
$r = $stmt->get_result();
while($row = $r->fetch_assoc()) $my_borrows[] = $row['book_id'];
$stmt->close();

$my_pending = [];
$stmt = $conn->prepare("SELECT book_id FROM Borrow_Request_Table WHERE user_id=? AND status='pending'");
$stmt->bind_param("s", $_SESSION['user_id']);
$stmt->execute();
$r = $stmt->get_result();
while($row = $r->fetch_assoc()) $my_pending[] = $row['book_id'];
$stmt->close();

// My active transactions
$my_transactions = [];
$stmt = $conn->prepare("SELECT t.*, bk.title FROM Transaction_Table t JOIN Books_Table bk ON t.book_id=bk.book_id WHERE t.user_id=? ORDER BY t.transaction_id DESC");
$stmt->bind_param("s", $_SESSION['user_id']);
$stmt->execute();
$r = $stmt->get_result();
while($row = $r->fetch_assoc()) $my_transactions[] = $row;
$stmt->close();

// Genre filter options
$genres = array_unique(array_filter(array_column($all_books, 'genre')));
sort($genres);

// ======================== EMOJI MAP ========================
$emoji_map = [
    'Fiction'     => '📖',
    'Classic'     => '📜',
    'Dystopian'   => '⚙️',
    'Sci-Fi'      => '🚀',
    'Mystery'     => '🕵️',
    'Romance'     => '❤️',
    'Fantasy'     => '🐉',
    'Non-Fiction' => '📰',
    'Biography'   => '👤',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Alexandria Library | Browse Books</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,700;1,400&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root {
    --bg: #0a0e1a;
    --surface: #111827;
    --surface2: #1a2235;
    --accent: #c8a96e;
    --accent2: #7c6af5;
    --text: #e8e0d4;
    --text-muted: #8a8fa8;
    --green: #34d399;
    --red: #f87171;
    --orange: #fb923c;
    --border: rgba(200,169,110,0.12);
}
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'DM Sans',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; }

/* HEADER */
header {
    background:rgba(10,14,26,0.95); backdrop-filter:blur(20px);
    border-bottom:1px solid var(--border); padding:16px 32px;
    display:flex; align-items:center; justify-content:space-between;
    position:sticky; top:0; z-index:100;
}
.logo { font-family:'Cormorant Garamond',serif; font-size:1.6rem; color:var(--accent); letter-spacing:2px; }
.logo span { color:var(--text-muted); font-size:0.9rem; font-family:'DM Sans',sans-serif; display:block; letter-spacing:4px; text-transform:uppercase; font-size:0.62rem; }
nav { display:flex; gap:16px; align-items:center; }
nav a { color:var(--text-muted); text-decoration:none; font-size:0.85rem; transition:0.2s; }
nav a:hover { color:var(--accent); }
.user-chip { background:rgba(200,169,110,0.1); border:1px solid var(--border); padding:6px 14px; border-radius:20px; font-size:0.82rem; color:var(--accent); }
.btn-logout { background:rgba(248,113,113,0.1); border:1px solid rgba(248,113,113,0.2); color:#f87171; padding:7px 16px; border-radius:8px; cursor:pointer; font-size:0.82rem; text-decoration:none; transition:0.2s; }
.btn-logout:hover { background:rgba(248,113,113,0.2); }

/* HERO */
.hero {
    padding:60px 32px 40px;
    background:linear-gradient(135deg, rgba(124,106,245,0.08) 0%, rgba(200,169,110,0.06) 100%);
    border-bottom:1px solid var(--border);
}
.hero h1 { font-family:'Cormorant Garamond',serif; font-size:3rem; margin-bottom:8px; }
.hero h1 em { color:var(--accent); font-style:italic; }
.hero p { color:var(--text-muted); font-size:0.9rem; margin-bottom:24px; }
.hero-search {
    display:flex; gap:12px; max-width:540px;
}
.hero-search input {
    flex:1; background:var(--surface); border:1px solid var(--border);
    color:var(--text); padding:12px 18px; border-radius:10px;
    font-size:0.9rem; outline:none; transition:0.2s;
}
.hero-search input:focus { border-color:var(--accent); }
.hero-search button {
    background:var(--accent); color:var(--bg); padding:12px 24px;
    border:none; border-radius:10px; cursor:pointer; font-weight:600;
    font-size:0.88rem; transition:0.2s;
}
.hero-search button:hover { opacity:0.85; }

/* STATS BAR */
.stats-bar {
    display:flex; gap:24px; padding:20px 32px;
    border-bottom:1px solid var(--border); background:var(--surface);
    overflow-x:auto;
}
.stat-item { text-align:center; white-space:nowrap; }
.stat-item .val { font-family:'Cormorant Garamond',serif; font-size:1.5rem; color:var(--accent); }
.stat-item .lbl { font-size:0.72rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; }

/* LAYOUT */
.layout { display:flex; gap:0; min-height:calc(100vh - 200px); }

/* SIDEBAR FILTERS */
.filters-sidebar {
    width:220px; flex-shrink:0; padding:24px 16px;
    border-right:1px solid var(--border); background:var(--surface);
    position:sticky; top:69px; height:calc(100vh - 69px); overflow-y:auto;
}
.filters-sidebar h3 { font-size:0.72rem; text-transform:uppercase; letter-spacing:2px; color:var(--text-muted); margin-bottom:12px; }
.genre-btn {
    display:flex; align-items:center; gap:8px; width:100%;
    padding:9px 12px; border-radius:8px; cursor:pointer;
    transition:0.2s; font-size:0.84rem; color:var(--text-muted);
    background:none; border:none; text-align:left;
}
.genre-btn:hover { background:rgba(200,169,110,0.08); color:var(--text); }
.genre-btn.active { background:rgba(200,169,110,0.12); color:var(--accent); border-left:2px solid var(--accent); }
.divider { height:1px; background:var(--border); margin:16px 0; }

/* MY BORROWS SECTION */
.my-borrows-widget {
    margin-top:12px;
}
.my-borrow-item {
    background:var(--surface2); border-radius:8px; padding:10px;
    margin-bottom:8px; font-size:0.78rem;
}
.my-borrow-item .bk-title { color:var(--text); font-weight:600; margin-bottom:3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:170px; }
.my-borrow-item .bk-due { color:var(--text-muted); }
.my-borrow-item .bk-due.overdue { color:var(--red); }

/* BOOKS GRID */
.books-area { flex:1; padding:28px 32px; }
.books-area h2 { font-family:'Cormorant Garamond',serif; font-size:1.7rem; margin-bottom:20px; }
.books-area h2 span { color:var(--text-muted); font-size:1rem; font-family:'DM Sans',sans-serif; margin-left:8px; }

.books-grid {
    display:grid;
    grid-template-columns:repeat(auto-fill, minmax(300px, 1fr));
    gap:20px;
}
.book-card {
    background:var(--surface); border:1px solid var(--border);
    border-radius:14px; overflow:hidden; transition:0.25s;
    display:flex; flex-direction:column;
}
.book-card:hover { transform:translateY(-4px); border-color:rgba(200,169,110,0.3); box-shadow:0 12px 32px rgba(0,0,0,0.3); }
.book-card-top {
    padding:20px 20px 14px;
    background:linear-gradient(135deg, rgba(124,106,245,0.06), rgba(200,169,110,0.04));
    display:flex; gap:14px; align-items:flex-start;
}
.book-emoji { font-size:2.6rem; line-height:1; flex-shrink:0; }
.book-info { flex:1; min-width:0; }
.book-title { font-family:'Cormorant Garamond',serif; font-size:1.1rem; font-weight:700; line-height:1.3; margin-bottom:4px; }
.book-author { font-size:0.8rem; color:var(--text-muted); }
.book-card-bottom {
    padding:14px 20px; border-top:1px solid var(--border);
    display:flex; align-items:center; justify-content:space-between; gap:10px;
    margin-top:auto;
}
.badge-genre { 
    background:rgba(124,106,245,0.12); color:#a09cf7; 
    border-radius:20px; padding:3px 10px; font-size:0.72rem; 
}
.badge-avail {
    padding:3px 10px; border-radius:20px; font-size:0.72rem; font-weight:600;
}
.badge-avail.yes { background:rgba(52,211,153,0.12); color:var(--green); }
.badge-avail.no  { background:rgba(248,113,113,0.12); color:var(--red); }

.btn-request {
    background:var(--accent); color:var(--bg); padding:8px 18px;
    border:none; border-radius:8px; cursor:pointer; font-weight:600;
    font-size:0.82rem; transition:0.2s; white-space:nowrap;
}
.btn-request:hover { opacity:0.85; transform:translateY(-1px); }
.btn-request:disabled { background:rgba(255,255,255,0.08); color:var(--text-muted); cursor:not-allowed; transform:none; }

.empty-state { text-align:center; padding:60px 20px; color:var(--text-muted); }
.empty-state .big { font-size:3rem; display:block; margin-bottom:12px; }

/* MY REQUESTS SECTION */
.my-requests-panel {
    background:var(--surface); border:1px solid var(--border); border-radius:14px;
    padding:24px; margin-bottom:28px;
}
.my-requests-panel h3 { font-family:'Cormorant Garamond',serif; font-size:1.3rem; margin-bottom:16px; }
.request-row {
    display:flex; align-items:center; justify-content:space-between;
    padding:10px 0; border-bottom:1px solid var(--border); gap:16px;
}
.request-row:last-child { border-bottom:none; }
.request-row .rt { font-size:0.85rem; }
.request-row .rb { font-size:0.78rem; color:var(--text-muted); }

/* TOAST */
.toast {
    position:fixed; top:20px; right:20px; z-index:9999;
    padding:14px 22px; border-radius:10px; font-weight:600;
    font-size:0.88rem; animation:slideIn 0.3s ease;
    max-width:380px; box-shadow:0 8px 24px rgba(0,0,0,0.4);
}
.toast.success { background:rgba(52,211,153,0.15); color:var(--green); border:1px solid rgba(52,211,153,0.3); }
.toast.error   { background:rgba(248,113,113,0.15); color:var(--red); border:1px solid rgba(248,113,113,0.3); }
@keyframes slideIn { from { transform:translateX(400px); opacity:0; } to { transform:translateX(0); opacity:1; } }

@media(max-width:768px) {
    .filters-sidebar { display:none; }
    .books-area { padding:16px; }
    header { padding:12px 16px; }
    .hero { padding:32px 16px; }
    .stats-bar { padding:16px; }
}
</style>
</head>
<body>

<?php if($message): ?>
<div class="toast <?= $message_type ?>" id="toastMsg"><?= $message ?></div>
<script>setTimeout(() => { const t = document.getElementById('toastMsg'); if(t) t.remove(); }, 5000);</script>
<?php endif; ?>

<!-- HEADER -->
<header>
    <div class="logo">
        Alexandria
        <span>Pamantasan ng Cabuyao</span>
    </div>
    <nav>
        <span class="user-chip">📖 <?= htmlspecialchars($_SESSION['name_user']) ?></span>
        <a href="logout.php" class="btn-logout">Logout</a>
    </nav>
</header>

<!-- HERO -->
<div class="hero">
    <h1>Discover &amp; Borrow <em>Books</em></h1>
    <p>Browse our collection and request to borrow available titles.</p>
    <div class="hero-search">
        <input type="text" id="searchInput" placeholder="Search by title, author, or genre..." oninput="renderBooks()">
        <button onclick="renderBooks()">Search</button>
    </div>
</div>

<!-- STATS -->
<?php
$total  = count($all_books);
$avail  = count(array_filter($all_books, fn($b) => $b['track_status'] === 'Available'));
$borrow = $total - $avail;
?>
<div class="stats-bar">
    <div class="stat-item"><div class="val"><?= $total ?></div><div class="lbl">Total Books</div></div>
    <div class="stat-item"><div class="val" style="color:var(--green)"><?= $avail ?></div><div class="lbl">Available</div></div>
    <div class="stat-item"><div class="val" style="color:var(--orange)"><?= $borrow ?></div><div class="lbl">Borrowed</div></div>
    <div class="stat-item"><div class="val" style="color:var(--accent2)"><?= count($my_transactions) ?></div><div class="lbl">My Borrows</div></div>
</div>

<!-- LAYOUT -->
<div class="layout">

    <!-- SIDEBAR FILTERS -->
    <aside class="filters-sidebar">
        <h3>Filter by Genre</h3>
        <button class="genre-btn active" data-genre="all" onclick="setGenre('all', this)">📚 All Books</button>
        <?php foreach($genres as $genre): ?>
        <?php $em = $emoji_map[$genre] ?? '📘'; ?>
        <button class="genre-btn" data-genre="<?= htmlspecialchars($genre) ?>" onclick="setGenre(this.dataset.genre, this)"><?= $em ?> <?= htmlspecialchars($genre) ?></button>
        <?php endforeach; ?>

        <?php if(count($my_transactions) > 0): ?>
        <div class="divider"></div>
        <h3>My Active Borrows</h3>
        <div class="my-borrows-widget">
        <?php foreach($my_transactions as $txn): ?>
        <?php if(!$txn['return_date']): ?>
        <?php
            $is_od = strtotime($txn['due_date']) < time();
        ?>
        <div class="my-borrow-item">
            <div class="bk-title" title="<?= htmlspecialchars($txn['title']) ?>"><?= htmlspecialchars($txn['title']) ?></div>
            <div class="bk-due <?= $is_od ? 'overdue' : '' ?>">Due: <?= $txn['due_date'] ?><?= $is_od ? ' ⚠️' : '' ?></div>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </aside>

    <!-- BOOKS AREA -->
    <div class="books-area">

        <!-- MY PENDING REQUESTS -->
        <?php
        $pending_requests = $conn->prepare("SELECT br.*, bk.title FROM Borrow_Request_Table br JOIN Books_Table bk ON br.book_id=bk.book_id WHERE br.user_id=? AND br.status='pending' ORDER BY br.request_date DESC");
        $pending_requests->bind_param("s", $_SESSION['user_id']);
        $pending_requests->execute();
        $pr = $pending_requests->get_result();
        $my_pending_full = [];
        while($row = $pr->fetch_assoc()) $my_pending_full[] = $row;
        $pending_requests->close();
        ?>
        <?php if(count($my_pending_full) > 0): ?>
        <div class="my-requests-panel">
            <h3>⏳ My Pending Requests <span style="font-size:0.8rem; color:var(--text-muted); font-family:'DM Sans',sans-serif;">(<?= count($my_pending_full) ?>)</span></h3>
            <?php foreach($my_pending_full as $pr): ?>
            <div class="request-row">
                <div>
                    <div class="rt"><?= htmlspecialchars($pr['title']) ?></div>
                    <div class="rb">Requested on <?= $pr['request_date'] ?></div>
                </div>
                <span style="background:rgba(240,180,41,0.12); color:#f0b429; border:1px solid rgba(240,180,41,0.2); border-radius:20px; padding:3px 10px; font-size:0.72rem; font-weight:600;">Waiting for approval</span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <h2>Books Collection <span id="bookCount"></span></h2>
        <div class="books-grid" id="booksGrid"></div>
    </div>
</div>

<script>
// All books from PHP
const allBooks = <?= json_encode($all_books) ?>;
const myBorrows  = <?= json_encode($my_borrows) ?>;
const myPending  = <?= json_encode($my_pending) ?>;
const emojiMap   = <?= json_encode($emoji_map) ?>;

let currentGenre = 'all';

function setGenre(genre, el) {
    currentGenre = genre;
    document.querySelectorAll('.genre-btn').forEach(b => b.classList.remove('active'));
    if(el) el.classList.add('active');
    renderBooks();
}

function getButtonState(book) {
    const bid = parseInt(book.book_id);
    if (myBorrows.includes(bid))    return { disabled:true, text:'Already Borrowing' };
    if (myPending.includes(bid))    return { disabled:true, text:'⏳ Request Pending' };
    if (book.track_status !== 'Available') return { disabled:true, text:'Currently Borrowed' };
    return { disabled:false, text:'📤 Request to Borrow' };
}

function renderBooks() {
    const search = (document.getElementById('searchInput').value || '').toLowerCase().trim();
    let filtered = allBooks.filter(book => {
        const matchGenre = currentGenre === 'all' || (book.genre || '').toLowerCase() === currentGenre.toLowerCase();
        const matchSearch = !search ||
            book.title.toLowerCase().includes(search) ||
            book.author.toLowerCase().includes(search) ||
            (book.genre || '').toLowerCase().includes(search) ||
            book.track_status.toLowerCase().includes(search);
        return matchGenre && matchSearch;
    });

    document.getElementById('bookCount').textContent = `(${filtered.length} book${filtered.length !== 1 ? 's' : ''})`;

    if (filtered.length === 0) {
        document.getElementById('booksGrid').innerHTML = `
            <div class="empty-state" style="grid-column:1/-1">
                <span class="big">📭</span>
                No books match your search.
            </div>`;
        return;
    }

    const html = filtered.map(book => {
        const emoji = emojiMap[book.genre] || '📘';
        const avail = book.track_status === 'Available';
        const btn   = getButtonState(book);
        return `
        <div class="book-card" data-title="${book.title.toLowerCase()}" data-genre="${(book.genre||'').toLowerCase()}">
            <div class="book-card-top">
                <div class="book-emoji">${emoji}</div>
                <div class="book-info">
                    <div class="book-title">${escH(book.title)}</div>
                    <div class="book-author">by ${escH(book.author)}</div>
                </div>
            </div>
            <div class="book-card-bottom">
                <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                    ${book.genre ? `<span class="badge-genre">${escH(book.genre)}</span>` : ''}
                    <span class="badge-avail ${avail ? 'yes' : 'no'}">${avail ? '✅ Available' : '❌ Borrowed'}</span>
                </div>
                <form method="POST" style="margin:0;">
                    <input type="hidden" name="action" value="request">
                    <input type="hidden" name="book_id" value="${book.book_id}">
                    <input type="hidden" name="book_title" value="${escH(book.title)}">
                    <button type="submit" class="btn-request" ${btn.disabled ? 'disabled' : ''}>${btn.text}</button>
                </form>
            </div>
        </div>`;
    }).join('');

    document.getElementById('booksGrid').innerHTML = html;
}

function escH(str) {
    if (!str) return '';
    return str.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

renderBooks();
</script>
</body>
</html>