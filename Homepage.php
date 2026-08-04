<?php include 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Portal | Alexandria Library System</title>
    <style>
        body {
            background-image: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('https://images.freeimages.com/images/large-previews/701/my-university-library-3-1442034.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: white;
            font-family: 'Segoe UI', Arial, sans-serif;
            perspective: 1000px; 
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        .brand-header {
            position: absolute;
            top: 40px;
            text-align: center;
            width: 100%;
        }
        .brand-header h1 { 
            margin: 0; 
            letter-spacing: 2px; 
            text-shadow: 2px 2px 10px rgba(0,0,0,0.5);
        }

        input[type="radio"] { display: none; }

        .auth-card {
            width: 400px;
            background: rgba(102, 86, 161, 0.3);
            padding: 40px;
            border-radius: 20px;
            backdrop-filter: blur(15px);
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            border: 1px solid rgba(255,255,255,0.2);
            transition: all 0.6s cubic-bezier(0.68, -0.55, 0.27, 1.55);
            position: absolute;
            opacity: 0;
            pointer-events: none;
            transform: rotateY(30deg) scale(0.9);
        }

        #tab1:checked ~ #login-section {
            opacity: 1;
            pointer-events: all;
            transform: rotateY(0deg) scale(1);
        }

        #tab2:checked ~ #signup-section {
            opacity: 1;
            pointer-events: all;
            transform: rotateY(0deg) scale(1);
        }

        h2 { text-align: center; margin-bottom: 25px; font-weight: 600; }
        .input-group { margin-bottom: 15px; }
        
        select, input {
            width: 100%;
            padding: 12px;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            color: white;
            font-size: 1em;
            outline: none;
            box-sizing: border-box;
            transition: 0.3s;
        }

        input:focus, select:focus {
            background: rgba(255, 255, 255, 0.25);
            border-color: #3498db;
        }

        select option { background: #4a3b8a; color: white; }

        button {
            width: 100%;
            padding: 12px;
            background: #220686;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
            text-transform: uppercase;
        }

        button:hover { background: #3498db; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4); }

        .switch-link {
            text-align: center;
            margin-top: 20px;
            font-size: 0.9em;
            opacity: 0.9;
        }

        .switch-link label {
            color: #3498db;
            cursor: pointer;
            text-decoration: underline;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <div class="brand-header">
        <h1>PAMANTASAN NG CABUYAO</h1>
        <p>College of Computing Studies | Online Library System</p>
    </div>

    <input type="radio" name="auth-toggle" id="tab1" checked>
    <input type="radio" name="auth-toggle" id="tab2">

    <div class="auth-card" id="login-section">
        <h2>Librarian & Student Login</h2>
        <form action="login_process.php" method="POST">
            <div class="input-group">
                <select name="role" required>
                    <option value="" disabled selected>Identify your role...</option>
                    <option value="student">Student</option>
                    <option value="admin">Librarian / Admin</option>
                </select>
            </div>
            <div class="input-group">
                <input type="text" name="user_id" placeholder="Username or ID Number" required>
            </div>
            <div class="input-group">
                <input type="password" name="password" placeholder="Password" required>
            </div>
            <button type="submit">Log in</button>
        </form>
        <div class="switch-link">
            Need an account? <label for="tab2">Register here</label>
        </div>
    </div>

   <div class="auth-card" id="signup-section">
    <h2>Account Registration</h2>
    <form id="signupForm" action="signup_process.php" method="POST">
        <div class="input-group">
            <select name="role" id="reg-role" required>
                <option value="" disabled selected>Registering as...</option>
                <option value="student">Student</option>
            /* --- Removed admin role if you want to --- *
                <option value="admin">Librarian / Admin</option>
            </select>
        </div>
        <div class="input-group">
            <input type="text" name="full_name" placeholder="Full Name" required>
        </div>
        <div class="input-group">
            <input type="text" name="user_id" placeholder="University ID Number" required>
        </div>
        <div class="input-group">
            <input type="email" name="email" placeholder="Email Address" required>
        </div>
        <div class="input-group">
            <input type="password" name="password" placeholder="Create Password" required>
        </div>
        <button type="submit">Create Account</button>
    </form>
</div>

  <script>
    const signupForm = document.getElementById('signupForm');

    signupForm.addEventListener('submit', function(e) {
        const name = e.target.querySelector('input[name="full_name"]').value;
        alert(`Processing registration for ${name}...`);
    });
</script>
</body>
</html>