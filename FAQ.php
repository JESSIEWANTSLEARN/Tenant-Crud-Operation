

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library System Online FAQ</title>
    <style>
        /* --- BASE STYLES --- */
        body {
            background-image: url('https://images.freeimages.com/images/large-previews/701/my-university-library-3-1442034.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            color: white;
            font-family: Arial, sans-serif;
            perspective: 1000px;
            margin: 0;
            padding: 0;
            overflow: hidden; /* Important for the slide effect */
        }
        /* Custom scrollbar for the FAQ card */
.faq-scroll {
    max-height: 60vh; /* Keeps card from growing off-screen */
    overflow-y: auto; /* Adds scrollbar if text is too long */
    text-align: left !important; /* Better for reading FAQs */
}

/* Make scrollbar look glassy to match your design */
.faq-scroll::-webkit-scrollbar {
    width: 6px;
}
.faq-scroll::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 10px;
}

/* Horizontal rule style */
hr {
    border: 0;
    height: 1px;
    background: rgba(255, 255, 255, 0.2);
    margin: 15px 0;
}


        h1 {
            transform: rotateY(10deg);
            transition: transform 0.5s ease-in-out;
            margin-bottom: 20px;
        }


        h1:hover {
            transform: rotateY(0deg) scale(1.1);
        }


        /* --- NAVIGATION --- */
        nav {
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 100; /* Keeps nav above the sliding sections */
        }


        nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            gap: 20px;
        }


        /* Applied styles to both links and labels to keep design consistent */
        nav a, nav label {
            text-decoration: none;
            color: white;
            font-weight: bold;
            padding: 10px 15px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(5px);
            border-radius: 5px;
            cursor: pointer;
            position: relative;
            display: inline-block;
        }


        /* Hover Line Logic */
        nav a::after, nav label::after {
            content: "";
            position: absolute;
            left: 50%;
            bottom: -5px;
            width: 0;
            height: 3px;
            background: #6656A1;
            transition: width 0.4s ease-in-out;
            transform: translateX(-50%);
        }


        nav a:hover::after, nav label:hover::after {
            width: 100%;
        }


        nav a:hover, nav label:hover {
            color: #6656A1;
            text-shadow: 0 0 10px #6656A1;
            transform: scale(1.1);
            background: rgba(255, 255, 255, 0.3);
        }


        /* --- SLIDING TRANSITION LOGIC --- */
        .container {
            position: relative;
            width: 100%;
            height: 100vh;
        }


        section {
            position: absolute;
            width: 100%;
            height: 100vh;
            display: flex;
            flex-direction: column; /* Allows Title + Form to stack */
            align-items: center;
            justify-content: center;
            top: 0;
            left: 100%; /* All sections start off-screen */
            transition: left 0.6s ease-in-out;
        }


        /* Custom Section Backgrounds (Glassy) */
        #sec1 { background: rgba(0, 0, 0, 0.2); }
        #sec2 { background: rgba(102, 86, 161, 0.3); }
        #sec3 { background: rgba(34, 6, 134, 0.4); }


        /* Hide radio buttons */
        input[type="radio"] {
            display: none;
        }


        /* The Activation Logic */
        #tab1:checked ~ .container #sec1,
        #tab2:checked ~ .container #sec2,
        #tab3:checked ~ .container #sec3 {
            left: 0;
        }


        /* --- REGISTRATION FORM CARD --- */
        .contact-form {
            width: 380px;
            background: #6656A1;
            padding: 30px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            transform: rotateY(10deg);
            transition: transform 0.5s ease-in-out;
        }
    .contact-form.faq-scroll {
    width: 80%;           
    max-width: 900px;    
    padding: 40px;        
    font-size: 1.1rem;    
    border-radius: 20px;  
    
    /* Ensure it handles the height well */
    max-height: 80vh;     /* Keeps the box from touching the top/bottom edges */
    overflow-y: auto;     /* Enables scrolling if the content is long */
}

/* Optional: Make the headings pop more */
     .faq-scroll h3 {
      font-size: 1.4rem;
       margin-top: 25px;
}

        .contact-form:hover {
            transform: rotateY(0deg) scale(1.02);
        }


        .contact-form h3 { margin: 0 0 20px 0; font-size: 1.5em; }


        .contact-form input, .contact-form button {
            width: 100%;
            margin-bottom: 15px;
            padding: 12px;
            border: none;
            border-radius: 5px;
            font-size: 1em;
            box-sizing: border-box;
        }


        .contact-form input { background: rgba(255, 255, 255, 0.3); color: white; outline: none; }
        .contact-form input::placeholder { color: rgba(255, 255, 255, 0.7); }


        #error-message {
            color: #ffcccc;
            background: rgba(255, 0, 0, 0.15);
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 0.85em;
            text-align: left;
        }


        .contact-form button { background: #220686; color: white; cursor: pointer; font-weight: bold; }
        .success-btn { background: #28a745 !important; }
        .signin-link { font-size: 0.9em; margin-top: 10px; }
        .signin-link a { color: #fff; text-decoration: underline; }


    </style>
</head>
<body>


    <input type="radio" id="tab1" name="tabs" checked>
    <input type="radio" id="tab2" name="tabs">
    <input type="radio" id="tab3" name="tabs">


    <nav>
        <ul>
            <li><a href="student.php">Book Collection<Homepage class="php"></Homepage></a></li>
            <li><label for="tab1">Welcome</label></li>
            <li><label for="tab2">FAQ</label></li>
            <li><label for="tab3">ABOUT</label></li>
        </ul>
    </nav>

    <div class="container">
        <section id="sec1">
             <h1>Welcome to</h1>
            <h1>Online Library Management System</h1>
        </section>

<section id="sec2">
    <h1>FAQ</h1>
    <div class="contact-form faq-scroll">
        <h2 style="text-align: center; margin-bottom: 20px;">Frequently Asked Questions (FAQ)</h2>

        <h3>1. What is this website about?</h3>
        <p>Welcome to our website! This platform is created as part of our final project for <strong>CCS110 – System Creating</strong>. It showcases our <strong>Online Library Management System</strong>, developed by <strong>Group 1</strong>.</p>
        <hr>

        <h3>2. What is the Online Library Management System?</h3>
        <p>The Online Library Management System is a digital solution designed to manage and organize library operations efficiently. It helps track books, borrowers, due dates, and fines in a simple and user-friendly way.</p>
        <hr>

        <h3>3. What features does the system include?</h3>
        <p>Our system includes the following features:</p>
        <ul style="list-style: none; padding: 0;">
            <li>📚 <strong>Book Search</strong> – Easily find available books in the library</li>
            <li>🔄 <strong>Issue & Return Management</strong> – Manage borrowing and returning of books</li>
            <li>⏰ <strong>Due Date Tracking</strong> – Monitor deadlines for borrowed books</li>
            <li>💰 <strong>Penalty Calculation</strong> – Automatically calculate fines for late returns</li>
        </ul>
        <hr>

        <h3>4. Who developed this project?</h3>
        <p>This project was developed by <strong>Group 1</strong> as part of the CCS110 course.</p>
       <ul style="list-style: none; padding: 0;">
    <li><strong>Project Leader & Full-Stack Developer:</strong> [John Jessie R Palarao]</li>
    <li><strong>UI/UX Designer:</strong> [John Paul Villasantal]</li>
    <li><strong>Support:</strong> [Cyron Vinz Noah Corminal]</li>
    <li><strong>Documentation:</strong> [Taironne James Sieterales]</li>
    <li><strong>Documentation:</strong> [Christophe Zarate]</li>
</ul>
        <hr>

        <h3>5. What is the purpose of this project?</h3>
        <p>The goal of this project is to demonstrate our understanding of system development by creating a functional and practical application that improves library management processes.</p>
        <hr>

        <h3>6. Who can use this system?</h3>
        <p>This system is designed for educational purposes but can also be adapted for use in small libraries, schools, or organizations.</p>
        <hr>

        <h3>7. How can I use the system?</h3>
        <p>You can explore the system by navigating through the website, searching for books, and testing the issue and return features.</p>
        <hr>

        <h3>8. Is this a real library system?</h3>
        <p>This is a <strong>prototype system</strong> created for academic purposes. However, it reflects real-world library management functionalities.</p>
        <hr>

        <h3>9. Can this system be improved?</h3>
        <p>Yes! Future improvements may include user accounts, advanced search filters, notifications, and enhanced security features.</p>
        <hr>

        <p style="text-align: center; margin-top: 20px;">Thank you for visiting our website! 😊</p>
    </div>
</section>

  <section id="sec3">
    <h1>About the Project</h1>
    <div class="contact-form faq-scroll" style="width: 85%; max-width: 1000px;">
        <h2 style="text-align: center; margin-bottom: 20px;">System Overview</h2>
        
        <p>The <strong>Online Library Management System</strong> is a digital solution developed to streamline data management processes within an organization. By centralizing data storage, the system aims to improve accessibility, ensure data integrity, and enhance decision-making through efficient information retrieval.</p>
        
        <hr>

        <h3>Our Objectives</h3>
        <ul style="list-style-type: square; padding-left: 20px;">
            <li><strong>Centralize Storage:</strong> Eliminate redundant paperwork and consolidate library records into a secure database.</li>
            <li><strong>Accuracy & Consistency:</strong> Maintain real-time updates on book availability and borrower history.</li>
            <li><strong>Streamlined Collaboration:</strong> Facilitate better information sharing between library staff and students.</li>
        </ul>

        <hr>

        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 280px;">
                <h3>Functional Scope</h3>
                <p>The system is built to handle core library operations including:</p>
                <ul style="list-style: none; padding: 0;">
                    <li>✅ User Authentication & Authorization</li>
                    <li>✅ CRUD Operations (Books & Borrowers)</li>
                    <li>✅ Automated Penalty Calculations</li>
                    <li>✅ Advanced Search & Filtering</li>
                </ul>
            </div>

            <div style="flex: 1; min-width: 280px;">
                <h3>Non-Functional Standards</h3>
                <p>Our development focuses on high-quality performance:</p>
                <ul style="list-style: none; padding: 0;">
                    <li>🚀 <strong>Performance:</strong> Optimized for quick data processing.</li>
                    <li>🛡️ <strong>Security:</strong> Basic data encryption and access control.</li>
                    <li>📱 <strong>Usability:</strong> A user-friendly interface for all levels of tech-savviness.</li>
                </ul>
            </div>
        </div>

        <hr>

        <p style="text-align: center; font-style: italic;">
            Developed for <strong>CCS110 – System Creating</strong> at Pamantasan ng Cabuyao.
        </p>
    </div>
</section>

</body>
</html>


