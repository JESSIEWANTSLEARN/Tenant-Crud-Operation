<?php
session_start();
include 'db.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: Homepage.php");
    exit();
}
// Handle borrow request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'request') {
    $book_id = intval($_POST['book_id']);
    $borrower_id = $_SESSION['user_id'];
    $book_title = htmlspecialchars($_POST['book_title']);

    // Check if student already has a pending request
    $check_sql = "SELECT * FROM Borrow_Request_Table WHERE book_id = ? AND borrower_id = ? AND status = 'pending'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("is", $book_id, $borrower_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $message = "❌ You already have a pending request for this book!";
        $message_type = "error";
    } else {
        // Insert borrow request
        $sql = "INSERT INTO Borrow_Request_Table (book_id, borrower_id, request_date, status) VALUES (?, ?, NOW(), 'pending')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $book_id, $borrower_id);

        if ($stmt->execute()) {
            $message = "✅ Request sent! Admin will review your request for '$book_title'";
            $message_type = "success";
        } else {
            $message = "❌ Error: " . $conn->error;
            $message_type = "error";
        }
        $stmt->close();
    }
    $check_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Books Collection | Alexandria Library</title>

    <style>
        /* --- BASE STYLES --- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-image: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('https://images.freeimages.com/images/large-previews/701/my-university-library-3-1442034.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #D9D9D9;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* --- Header --- */
        header {
            background: rgba(102, 86, 161, 0.95);
            padding: 15px 30px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        header > div:first-child {
            font-weight: bold;
            font-size: 1.1em;
            letter-spacing: 1px;
            flex: 1;
        }

        /* --- Nav Bar --- */
        nav {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        nav a, nav span {
            color: white;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
            font-size: 0.95em;
        }

        nav a:hover {
            color: #a8e6ff;
            text-shadow: 0 0 10px rgba(52, 152, 219, 0.6);
        }

        .login-btn {
            background: #220686;
            padding: 10px 20px;
            border-radius: 5px;
            border: 1px solid rgba(255,255,255,0.2);
            transition: 0.3s;
            cursor: pointer;
        }

        .login-btn:hover {
            background: #3a0fb0;
            border-color: rgba(255,255,255,0.4);
        }

        .logout-btn {
            background: #e74c3c;
            padding: 10px 20px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
        }

        .logout-btn:hover {
            background: #c0392b;
        }

        /* SEARCH BOX */
        .search-box {
            display: flex;
            gap: 10px;
            flex: 1;
            min-width: 250px;
            max-width: 400px;
        }

        .search-box input {
            flex: 1;
            background: rgba(255,255,255,0.2);
            color: #D9D9D9;
            padding: 12px;
            border-radius: 5px;
            border: none;
            outline: none;
            transition: 0.3s;
        }

        .search-box input::placeholder {
            color: rgba(255,255,255,0.6);
        }

        .search-box input:focus {
            background: rgba(255,255,255,0.3);
            box-shadow: 0 0 15px rgba(102, 86, 161, 0.5);
        }

        .search-box button {
            background: #31323E;
            color: #D9D9D9;
            padding: 10px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: 0.3s;
        }

        .search-box button:hover {
            background: #45464f;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        /* --- MESSAGE ALERT --- */
        .message-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 8px;
            font-weight: bold;
            animation: slideIn 0.3s ease-out;
            z-index: 9999;
            max-width: 400px;
        }

        .message-alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message-alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* --- Top Content (Carousel) --- */
        .topContent {
            padding: 40px 20px;
            background: rgba(0, 0, 0, 0.3);
            border-bottom: 1px solid rgba(102, 86, 161, 0.3);
        }

        .topContent h1 {
            text-align: center;
            margin-bottom: 30px;
            text-shadow: 2px 2px 10px rgba(0,0,0,0.5);
            font-size: 2em;
        }

        /* CAROUSEL WRAPPER */
        .carousel-container {
            position: relative;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .book-cards-grid {
            display: flex;
            gap: 25px;
            overflow-x: auto;
            scroll-behavior: smooth;
            padding: 10px 5px 20px 5px;
            flex: 1;
            scrollbar-width: thin;
        }

        .book-cards-grid::-webkit-scrollbar {
            height: 6px;
        }
        .book-cards-grid::-webkit-scrollbar-track {
            background: #2a2343;
            border-radius: 10px;
        }
        .book-cards-grid::-webkit-scrollbar-thumb {
            background: #b9a9ff;
            border-radius: 10px;
        }

        .prev, .next {
            cursor: pointer;
            font-size: 2rem;
            font-weight: bold;
            background: rgba(96, 81, 155, 0.7);
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: 0.3s;
            user-select: none;
            flex-shrink: 0;
        }

        .prev:hover, .next:hover {
            background: #8f7bcb;
            transform: scale(1.05);
        }

        /* Book Card (vertical style for carousel) */
        .book-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(15px);
            border-radius: 15px;
            border: 2px solid rgba(255,255,255,0.15);
            box-shadow: 0 15px 35px rgba(0,0,0,0.4);
            overflow: hidden;
            transition: all 0.3s ease;
            width: 200px;
            flex-shrink: 0;
            cursor: pointer;
        }

        .book-card:hover {
            transform: translateY(-8px);
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(102, 86, 161, 0.8);
        }

        .book-card-title {
            background: rgba(102, 86, 161, 0.6);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding: 12px;
            text-align: center;
            font-weight: bold;
        }

        .book-cover {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 140px;
            background: rgba(0,0,0,0.3);
            padding: 15px;
        }

        .book-cover img, .cover-placeholder {
            width: 100%;
            height: auto;
            max-height: 120px;
            object-fit: contain;
            border-radius: 8px;
        }

        .book-card-meta {
            display: flex;
            flex-direction: column;
            padding: 12px;
            gap: 8px;
            background: rgba(0, 0, 0, 0.2);
        }

        .book-card-meta span {
            background: rgba(102, 86, 161, 0.5);
            padding: 6px 10px;
            border-radius: 20px;
            text-align: center;
            font-size: 0.8rem;
        }

        /* --- Bottom Content --- */
        .bottomContent {
            display: flex;
            gap: 40px;
            padding: 50px 20px;
            flex: 1;
            align-items: flex-start;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* --- Left Column (Books) --- */
        .left-column {
            flex: 1;
            min-width: 300px;
            max-width: 900px;
        }

        .left-column h1 {
            margin-bottom: 30px;
            text-shadow: 2px 2px 10px rgba(0,0,0,0.5);
            font-size: 2em;
        }

        /* HORIZONTAL BOOK CARD for left column */
        .left-column .book-card {
            display: flex;
            flex-direction: column;
            width: 100%;
            margin-bottom: 20px;
            background: rgba(20, 15, 40, 0.85);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.2);
            transition: 0.2s;
        }

        .left-column .book-card:hover {
            transform: translateX(6px);
            background: rgba(35, 28, 65, 0.9);
        }

        .left-column .book-card-title {
            background: rgba(102, 86, 161, 0.7);
            border-radius: 20px 20px 0 0;
            text-align: left;
            padding: 12px 20px;
            font-size: 1.1rem;
            font-weight: bold;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }

        .left-column .card-row {
            display: flex;
            gap: 20px;
            padding: 15px 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .left-column .book-cover {
            flex: 0 0 100px;
            min-height: 120px;
            background: rgba(0,0,0,0.4);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 8px;
        }

        .left-column .book-cover img, 
        .left-column .cover-placeholder {
            width: 100%;
            height: auto;
            max-height: 100px;
            object-fit: contain;
        }

        .left-column .book-card-meta {
            flex: 1;
            background: transparent;
            padding: 0;
            gap: 12px;
            display: flex;
            flex-direction: column;
        }

        .left-column .book-card-meta span {
            background: rgba(102, 86, 161, 0.6);
            padding: 8px 12px;
            border-radius: 30px;
            font-size: 0.9rem;
            text-align: left;
        }

        /* ===== BORROW REQUEST BUTTON ===== */
        .borrow-button-container {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .btn-borrow {
            flex: 1;
            padding: 10px 16px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: 0.3s;
            font-size: 0.9rem;
        }

        .btn-borrow:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
        }

        .btn-borrow-disabled {
            background: #6c757d;
            cursor: not-allowed;
        }

        .btn-borrow-disabled:hover {
            background: #6c757d;
            transform: none;
        }

        /* --- Right Column (Genres) --- */
        .right-column {
            flex: 1;
            min-width: 260px;
            max-width: 350px;
        }

        .genres-section {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(15px);
            border-radius: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            padding: 25px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
            position: sticky;
            top: 100px;
        }

        .genres-section h1 {
            margin-bottom: 20px;
            font-size: 1.5em;
        }

        .genres-section ul {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .genres-section li {
            padding: 12px 16px;
            background: rgba(102, 86, 161, 0.3);
            border-left: 3px solid rgba(102, 86, 161, 0.8);
            border-radius: 4px;
            transition: all 0.3s ease;
            cursor: pointer;
            font-weight: 500;
        }

        .genres-section li:hover {
            background: rgba(102, 86, 161, 0.6);
            border-left-color: #a8e6ff;
            transform: translateX(8px);
        }

        .active-genre {
            background: rgba(143, 123, 203, 0.8) !important;
            border-left-color: white !important;
        }

        /* --- Footer --- */
        footer {
            background: rgba(96, 81, 155, 0.95);
            padding: 25px;
            text-align: center;
            margin-top: auto;
            font-size: 0.9em;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .left-column .card-row {
                flex-direction: column;
                align-items: flex-start;
            }
            .left-column .book-cover {
                width: 100%;
                flex-basis: auto;
            }
            .genres-section {
                position: static;
            }
            .prev, .next {
                width: 36px;
                height: 36px;
                font-size: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .book-card {
                width: 160px;
            }
        }
    </style>
</head>
<body>

<!-- MESSAGE ALERT -->
<?php if (isset($message)): ?>
<div class="message-alert <?php echo $message_type; ?>" id="messageAlert">
    <?php echo $message; ?>
</div>
<script>
    setTimeout(() => {
        document.getElementById('messageAlert').remove();
    }, 4000);
</script>
<?php endif; ?>

<header>
    <div><strong>ALEXANDRIA | PAMANTASAN NG CABUYAO</strong></div>
    <nav id="nav-auth-section">
        <a href="#">FAQ</a>
        <span>Welcome, <?php echo htmlspecialchars($_SESSION['name_user']); ?></span>
        <a href="logout.php" class="login-btn logout-btn">Logout</a>
    </nav>
    <div class="search-box">
        <input type="text" id="searchInput" placeholder="Search by title, author, or status...">
        <button id="searchBtn">Search</button>
    </div>
</header>

<!-- Recommendation Section (Carousel) -->
<section class="topContent">
    <h1>📚 Recommended Books</h1>
    <div class="carousel-container">
        <a class="prev" id="carouselPrev">&#10094;</a>
        <div class="book-cards-grid" id="carouselGrid"></div>
        <a class="next" id="carouselNext">&#10095;</a>
    </div>
</section>

<!-- Main Content Section -->
<section class="bottomContent">
    <!-- LEFT COLUMN: Books Collection -->
    <section class="left-column">
        <h1>📖 Public Books Collection</h1>
        <div id="booksContainer"></div>
    </section>

    <!-- RIGHT COLUMN: Genres -->
    <section class="right-column">
        <div class="genres-section">
            <h1>🎭 Genres</h1>
            <ul id="genreList">
                <li data-genre="all" class="active-genre">✨ All Genres</li>
                <li data-genre="Classic">📜 Classic</li>
                <li data-genre="Fiction">📖 Fiction</li>
                <li data-genre="Dystopian">⚙️ Dystopian</li>
                <li data-genre="Sci-Fi">🚀 Sci-Fi</li>
                <li data-genre="Mystery">🕵️ Mystery</li>
                <li data-genre="Romance">❤️ Romance</li>
                <li data-genre="Fantasy">🐉 Fantasy</li>
            </ul>
        </div>
    </section>
</section>

<footer>
    <p>&copy; 2024 Pamantasan ng Cabuyao - College of Computing Studies. All rights reserved.</p>
</footer>

<script>
    // ---------- BOOK DATABASE (FROM YOUR DATABASE) ----------
    const booksData = [
        { id: 1, title: "The Great Gatsby", author: "F. Scott Fitzgerald", status: "Borrowed", genre: "Fiction", coverEmoji: "🎭" },
        { id: 2, title: "To Kill a Mockingbird", author: "Harper Lee", status: "Available", genre: "Fiction", coverEmoji: "🐦" },
        { id: 3, title: "1984", author: "George Orwell", status: "Borrowed", genre: "Dystopian", coverEmoji: "👁️" },
        { id: 4, title: "The Alchemist", author: "Paulo Coelho", status: "Available", genre: "Fiction", coverEmoji: "✨" },
        { id: 5, title: "Harry Potter and the Sorcerer Stone", author: "J.K. Rowling", status: "Borrowed", genre: "Fantasy", coverEmoji: "🪄" }
    ];

    const recommendedTitles = ["1984", "Harry Potter and the Sorcerer Stone", "The Alchemist"];
    const recommendedBooks = booksData.filter(book => recommendedTitles.includes(book.title));

    // ---------- HELPER: Generate Horizontal Card with Borrow Button ----------
    function generateHorizontalCard(book) {
        const isAvailable = book.status === "Available";
        const buttonClass = isAvailable ? 'btn-borrow' : 'btn-borrow btn-borrow-disabled';
        const buttonText = isAvailable ? '📤 Request to Borrow' : '❌ Currently Unavailable';
        
        return `
            <div class="book-card" data-title="${book.title.toLowerCase()}" data-author="${book.author.toLowerCase()}" data-status="${book.status.toLowerCase()}" data-genre="${book.genre.toLowerCase()}">
                <div class="book-card-title">${escapeHtml(book.title)}</div>
                <div class="card-row">
                    <div class="book-cover">
                        <div class="cover-placeholder" style="font-size: 2.8rem;">${book.coverEmoji || "📘"}</div>
                    </div>
                    <div class="book-card-meta">
                        <span>✍️ Author: ${escapeHtml(book.author)}</span>
                        <span>📌 Status: ${escapeHtml(book.status)}</span>
                        <span>🏷️ Genre: ${escapeHtml(book.genre)}</span>
                        <div class="borrow-button-container">
                            <form method="POST" action="Admin.php" style="width: 100%; margin: 0;">
                                <input type="hidden" name="action" value="request">
                                <input type="hidden" name="book_id" value="${book.id}">
                                <input type="hidden" name="book_title" value="${book.title}">
                                <button type="submit" class="${buttonClass}" ${!isAvailable ? 'disabled' : ''}>
                                    ${buttonText}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // ---------- HELPER: Generate Vertical Card (Carousel) ----------
    function generateVerticalCard(book) {
        return `
            <div class="book-card" data-title="${book.title.toLowerCase()}" data-author="${book.author.toLowerCase()}" data-status="${book.status.toLowerCase()}" data-genre="${book.genre.toLowerCase()}">
                <div class="book-card-title">${escapeHtml(book.title)}</div>
                <div class="book-cover">
                    <div class="cover-placeholder" style="font-size: 3rem;">${book.coverEmoji || "📖"}</div>
                </div>
                <div class="book-card-meta">
                    <span>✍️ ${escapeHtml(book.author)}</span>
                    <span>📌 ${escapeHtml(book.status)}</span>
                    <span>🏷️ ${escapeHtml(book.genre)}</span>
                </div>
            </div>
        `;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    // ---------- CAROUSEL RENDER ----------
    function renderCarousel() {
        const container = document.getElementById('carouselGrid');
        if (!container) return;
        let html = '';
        recommendedBooks.forEach(book => {
            html += generateVerticalCard(book);
        });
        container.innerHTML = html;
    }

    // ---------- LEFT COLUMN RENDER (with filters) ----------
    let currentSearch = "";
    let currentGenre = "all";

    function filterBooks() {
        let filtered = [...booksData];
        if (currentGenre !== "all") {
            filtered = filtered.filter(book => book.genre.toLowerCase() === currentGenre.toLowerCase());
        }
        if (currentSearch.trim() !== "") {
            const term = currentSearch.trim().toLowerCase();
            filtered = filtered.filter(book =>
                book.title.toLowerCase().includes(term) ||
                book.author.toLowerCase().includes(term) ||
                book.status.toLowerCase().includes(term)
            );
        }
        return filtered;
    }

    function renderLeftColumn() {
        const container = document.getElementById('booksContainer');
        if (!container) return;
        const filtered = filterBooks();
        if (filtered.length === 0) {
            container.innerHTML = `<div style="text-align:center; padding:40px; background:rgba(0,0,0,0.5); border-radius:30px;">📭 No books match your search or genre filter.</div>`;
            return;
        }
        let html = '';
        filtered.forEach(book => {
            html += generateHorizontalCard(book);
        });
        container.innerHTML = html;
    }

    // ---------- SEARCH FUNCTION ----------
    function performSearch() {
        const input = document.getElementById('searchInput');
        currentSearch = input ? input.value : "";
        renderLeftColumn();
    }

    // ---------- GENRE FILTER ----------
    function setGenreFilter(genre) {
        currentGenre = genre;
        const items = document.querySelectorAll('#genreList li');
        items.forEach(li => {
            if (li.getAttribute('data-genre') === genre) {
                li.classList.add('active-genre');
            } else {
                li.classList.remove('active-genre');
            }
        });
        renderLeftColumn();
    }

    // ---------- CAROUSEL SCROLL ----------
    function initCarouselScroll() {
        const prevBtn = document.getElementById('carouselPrev');
        const nextBtn = document.getElementById('carouselNext');
        const carousel = document.getElementById('carouselGrid');
        if (prevBtn && nextBtn && carousel) {
            prevBtn.addEventListener('click', () => {
                carousel.scrollBy({ left: -280, behavior: 'smooth' });
            });
            nextBtn.addEventListener('click', () => {
                carousel.scrollBy({ left: 280, behavior: 'smooth' });
            });
        }
    }

    // ---------- EVENT LISTENERS ----------
    function bindEvents() {
        const searchInput = document.getElementById('searchInput');
        const searchBtn = document.getElementById('searchBtn');
        if (searchInput) {
            searchInput.addEventListener('input', performSearch);
        }
        if (searchBtn) {
            searchBtn.addEventListener('click', performSearch);
        }

        const genreItems = document.querySelectorAll('#genreList li');
        genreItems.forEach(item => {
            item.addEventListener('click', (e) => {
                const genreVal = item.getAttribute('data-genre');
                if (genreVal) setGenreFilter(genreVal);
            });
        });
    }

    // ---------- INITIALIZE ----------
    function init() {
        renderCarousel();
        renderLeftColumn();
        bindEvents();
        initCarouselScroll();
    }

    init();
</script>
</body>
</html>