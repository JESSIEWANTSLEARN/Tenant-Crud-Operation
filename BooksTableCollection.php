
npm create vite@latest framework -- --template react
cd framework
npm install
npm run dev
http://localhost:5173



<?php include 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Books Collection | Alexandria Library</title>
    <style>
        /* --- BASE STYLES --- */
        body {
            background-image: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('https://images.freeimages.com/images/large-previews/701/my-university-library-3-1442034.jpg');
            background-size: cover; 
            background-position: center;
            background-attachment: fixed;
            font-family: 'Segoe UI', Arial, sans-serif; 
            color: white; 
            margin: 0;
        }

        /* --- NAVIGATION (Dynamic Header) --- */
        nav { 
            background: rgba(102, 86, 161, 0.9); 
            padding: 15px 30px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            backdrop-filter: blur(10px);
        }
        nav a { color: white; text-decoration: none; font-weight: bold; margin: 0 15px; transition: 0.3s; }
        nav a:hover { color: #3498db; }

        .login-btn { 
            background: #220686; 
            padding: 10px 20px; 
            border-radius: 5px; 
            border: 1px solid rgba(255,255,255,0.2);
        }

        .user-email-display {
            color: #3498db;
            font-weight: bold;
            margin-right: 15px;
        }

        /* --- CONTENT AREA --- */
        .content { 
            padding: 50px 20px; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
        }
        
        h1 { margin-bottom: 30px; text-shadow: 2px 2px 10px rgba(0,0,0,0.5); }

        /* SEARCH BOX (Functional Requirement) */
        .search-box { 
            margin-bottom: 40px; 
            width: 100%; 
            max-width: 600px; 
            display: flex; 
            gap: 10px; 
        }
        .search-box input { 
            flex: 1; 
            padding: 12px; 
            border-radius: 5px; 
            border: none; 
            background: rgba(255,255,255,0.2); 
            color: white; 
            outline: none;
        }
        .search-box button { 
            padding: 10px 25px; 
            background: #6656A1; 
            color: white; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            font-weight: bold;
        }

        /* BOOKS TABLE */
        .table-container {
            width: 95%;
            max-width: 1100px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(15px);
            border-radius: 15px;
            padding: 20px;
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 15px 35px rgba(0,0,0,0.4);
        }

        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: rgba(102, 86, 161, 0.5); padding: 15px; text-transform: uppercase; font-size: 0.85em; }
        td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        tr:hover { background: rgba(255,255,255,0.05); }

        .status { font-weight: bold; padding: 5px 10px; border-radius: 4px; font-size: 0.8em; }
        .available { color: #2ecc71; background: rgba(46, 204, 113, 0.1); }
        .borrowed { color: #e74c3c; background: rgba(231, 76, 60, 0.1); }
    </style>
</head>
<body>

    <nav>
        <div><strong>ALEXANDRIA | PAMANTASAN NG CABUYAO</strong></div>
        <div id="nav-auth-section">
            <a href="FAQ.html">FAQ</a>
            <a href="Homepage.html" class="login-btn">Librarian Login</a>
        </div>
    </nav>

    <div class="content">
        <h1>Public Books Collection</h1>
        
        <div class="search-box">
            <input type="text" id="searchInput" onkeyup="searchBooks()" placeholder="Search by title, author, or status...">
            <button onclick="searchBooks()">Search</button>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Book ID</th>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Genre</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="booksTableBody">
                    <tr><td>1</td><td>The Great Gatsby</td><td>F. Scott Fitzgerald</td><td>Classic</td><td><span class="status borrowed">Borrowed</span></td></tr>
                    <tr><td>2</td><td>To Kill a Mockingbird</td><td>Harper Lee</td><td>Fiction</td><td><span class="status available">Available</span></td></tr>
                    <tr><td>3</td><td>1984</td><td>George Orwell</td><td>Dystopian</td><td><span class="status borrowed">Borrowed</span></td></tr>
                    <tr><td>4</td><td>The Catcher in the Rye</td><td>J.D. Salinger</td><td>Classic</td><td><span class="status available">Available</span></td></tr>
                    <tr><td>5</td><td>Brave New World</td><td>Aldous Huxley</td><td>Sci-Fi</td><td><span class="status available">Available</span></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // --- 1. SESSION CHECK (REAL-TIME USER IDENTITY) ---
        function checkSession() {
            const authSection = document.getElementById('nav-auth-section');
            const savedUser = localStorage.getItem('currentUser');

            if (savedUser) {
                // If a user is found in localStorage, update the UI
                authSection.innerHTML = `
                    <span class="user-email-display">👤 ${savedUser}</span>
                    <a href="FAQ.html">FAQ</a>
                    <a href="#" onclick="logout()" style="color:#e74c3c; font-size:0.9em; text-decoration:underline;">Logout</a>
                `;
            }
        }

        function logout() {
            localStorage.removeItem('currentUser');
            window.location.reload(); 
        }

        // --- 2. SEARCH/FILTER FUNCTION (Requirement 2.1) ---
        function searchBooks() {
            let input = document.getElementById('searchInput').value.toLowerCase();
            let rows = document.querySelectorAll('#booksTableBody tr');
            
            rows.forEach(row => {
                let text = row.innerText.toLowerCase();
                row.style.display = text.includes(input) ? '' : 'none';
            });
        }

        // Run session check immediately when page loads
        window.onload = checkSession;
    </script>
</body>
</html>
