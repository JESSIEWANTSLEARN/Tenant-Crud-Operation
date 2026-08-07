DROP DATABASE IF EXISTS Online_Library_Management_System;

-- Note: Book table stored all the Book and its status
CREATE DATABASE Online_Library_Management_System;
USE Online_Library_Management_System;

-- Books Table
  -- Books_Table: The primary parent for all book-related data.
CREATE TABLE Books_Table(
    book_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) UNIQUE NOT NULL,
    author VARCHAR(50) NOT NULL,
    track_status VARCHAR(50),
    genre VARCHAR(50)
);

-- Note: User_Table stores all system users (students + admins)
-- Note: this table acts as the User_Table where all accounts are stored.
-- account_status: controls whether a registered user can log in  
   -- User_Table: The primary parent for all student and admin accounts.
CREATE TABLE User_Table (
    user_id INT PRIMARY KEY,
    name_user VARCHAR(50) NOT NULL,
    email VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'admin') DEFAULT 'student',
    account_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'
);

-- Borrow Request Table
-- is_read: 0 = student hasn't seen the approval/rejection yet, 1 = seen
-- Note all request will go on this table if they tried borrow something.
-- Borrow_Request_Table: Child of Books_Table and User_Table.
CREATE TABLE Borrow_Request_Table (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    user_id INT NOT NULL,
    request_date DATE NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
     is_read INT DEFAULT 0, -- 0 = not seen, 1 = seen
    FOREIGN KEY(book_id) REFERENCES Books_Table(book_id),
    FOREIGN KEY(user_id) REFERENCES User_Table(user_id)
);

-- Transaction Table
-- Note transaction table it show all transaction
-- Transaction_Table: Child of Books_Table and User_Table.
CREATE TABLE Transaction_Table(
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT,
    user_id INT,
    FOREIGN KEY(book_id) REFERENCES Books_Table(book_id),
    FOREIGN KEY(user_id) REFERENCES User_Table(user_id),
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE
);

-- Fines Table
 -- Fines_table: Child of Transaction_Table
CREATE TABLE Fines_table (
    fine_id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT,
    FOREIGN KEY (transaction_id) REFERENCES Transaction_Table(transaction_id),
    fine_amount DECIMAL(10,2),
    status ENUM('unpaid', 'paid') DEFAULT 'unpaid'
);

-- ============ BOOK DATA ============
INSERT INTO Books_Table (title, author, track_status, genre) VALUES
('The Great Gatsby', 'F. Scott Fitzgerald', 'Borrowed', 'Classic'),
('To Kill a Mockingbird', 'Harper Lee', 'Available', 'Fiction'),
('1984', 'George Orwell', 'Borrowed', 'Dystopian'),
('The Alchemist', 'Paulo Coelho', 'Available', 'Adventure'),
('Harry Potter and the Sorcerer Stone', 'J.K. Rowling', 'Borrowed', 'Fantasy'),

('Re:Zero - Starting Life in Another World', 'Tappei Nagatsuki', 'Available', 'Isekai'),
('That Time I Got Reincarnated as a Slime', 'Fuse', 'Available', 'Fantasy'),
('Sword Art Online', 'Reki Kawahara', 'Borrowed', 'Sci-Fi'),
('Overlord', 'Kugane Maruyama', 'Available', 'Fantasy'),
('No Game No Life', 'Yuu Kamiya', 'Available', 'Fantasy'),
('The Rising of the Shield Hero', 'Aneko Yusagi', 'Borrowed', 'Fantasy'),
('Mushoku Tensei: Jobless Reincarnation', 'Rifujin na Magonote', 'Available', 'Fantasy'),
('Konosuba: Gods Blessing on This Wonderful World!', 'Natsume Akatsuki', 'Available', 'Fantasy'),
('Solo Leveling', 'Chugong', 'Borrowed', 'Fantasy'),
('The Beginning After The End', 'TurtleMe', 'Available', 'Fantasy'),
('Log Horizon', 'Mamare Touno', 'Available', 'Sci-Fi'),
('Grimgar of Fantasy and Ash', 'Ao Jyumonji', 'Available', 'Fantasy'),
('Arifureta: From Commonplace to Worlds Strongest', 'Ryo Shirakome', 'Borrowed', 'Fantasy'),
('The Devil is a Part-Timer!', 'Satoshi Wagahara', 'Available', 'Fantasy'),
('Inuyasha', 'Rumiko Takahashi', 'Available', 'Fantasy'),
('Darling in the Franxx', 'Code:000', 'Available', 'Sci-Fi'),
('Steins Gate', '5pb. & Nitroplus', 'Borrowed', 'Sci-Fi'),
('Your Name', 'Makoto Shinkai', 'Available', 'Romance'),
('Death Note', 'Tsugumi Ohba', 'Borrowed', 'Mystery'),
('Attack on Titan', 'Hajime Isayama', 'Available', 'Dystopian');

-- ============ USER DATA ============
-- All seeded accounts are pre-approved so they can login immediately during demo
-- 1 admin, 8 accounts
INSERT INTO User_Table (user_id, name_user, email, password, role, account_status) 
VALUES
(2041,     'John Jessie Palarao',     'palarao@gmail.com',    'admin123', 'admin',   'approved'),
(20240045, 'Suomii Miyama',           'suomii.m@ucmail.com',         'pass1234', 'student', 'approved'),
(20240067, 'Joshua Roque Greganda',   'joshua.greganda@ucmail.com',  'pass1234', 'student', 'approved'),
(20240089, 'Vonluiz Malinao',         'vonluiz.m@ucmail.com',        'pass1234', 'student', 'approved'),
(20240101, 'Eddie Jordan Villanueva', 'eddie.villanueva@ucmail.com', 'pass1234', 'student', 'approved'),
(20240112, 'Christopher Zarate',      'chris.zarate@ucmail.com',     'pass1234', 'student', 'approved'),
(20240134, 'Cyrone Corminal',         'cyrone.c@ucmail.com',         'pass1234', 'student', 'approved'),
(20240156, 'John Paul Villasanta',    'johnpaul.v@ucmail.com',       'pass1234', 'student', 'approved'),
(20240178, 'Tairone James',           'tairone.j@ucmail.com',        'pass1234', 'student', 'approved'),

(20240201, 'Angela Dela Cruz',     'angela.dc@ucmail.com',     'secure123', 'student', 'pending'),
(20240215, 'Marco Reyes',          'marco.r@ucmail.com',       'qwerty456', 'student', 'pending'),
(20240227, 'Samantha Lopez',       'samantha.l@ucmail.com',    'abc789xyz', 'student', 'pending'),
(20240239, 'Daniel Bautista',      'daniel.b@ucmail.com',      'pass5678',  'student', 'pending');


-- ============ TRANSACTION DATA ============
INSERT INTO Transaction_Table (book_id, user_id, issue_date, due_date, return_date)
 VALUES
(1, 20240045, '2026-01-01', '2026-01-15', NULL),
(3, 20240089, '2026-01-05', '2026-01-19', '2026-01-25'),
(4, 20240101, '2026-01-07', '2026-01-21', NULL),
(5, 20240112, '2026-01-10', '2026-01-24', '2026-01-23');


-- ✅ DELETE BOOKS 
DELETE FROM Books_Table 
WHERE book_id = 2;

-- ✅ DELETE TRANSACTIONS (by transaction ID)
DELETE FROM Transaction_Table
WHERE transaction_id = 2;

-- ✅ DELETE USER (must delete transactions first!)
DELETE FROM Transaction_Table 
WHERE transaction_id = '20240156';

DELETE FROM User_Table 
WHERE user_id = '20240156';


UPDATE Books_Table 
SET title = 'Advanced Java Programming'
WHERE book_id = 1;

UPDATE User_Table 
SET role = 'admin' 
WHERE user_id = '20240112';



-- ============ VERIFY DATA ============
SELECT * FROM Books_Table;
SELECT * FROM User_Table;
SELECT * FROM  Borrow_Request_Table;
SELECT * FROM Transaction_Table;
SELECT * FROM Fines_table;
SHOW TABLES;

-- ============ DEMO SQL QUERIES ============

-- JOIN: View active borrowers with names and book titles
 -- This shows ALL books that are currently out. It includes books that are not late yet (e.g., due tomorrow or next week). 
 -- It helps a librarian plan for what is coming back soon.
SELECT
    User_Table.name_user,
    Books_Table.title,
    Transaction_Table.due_date
FROM Transaction_Table
JOIN IUser_Table ON Transaction_Table.user_id = User_Table.user_id
JOIN Books_Table ON Transaction_Table.book_id = Books_Table.book_id
WHERE Transaction_Table.return_date is Null
ORDER BY Transaction_Table.due_date ASC;


/* VIEW ACTIVE BORROWERS:
   - Performs a JOIN between Transaction, User, and Books tables.
   - WHERE + ORDER BY: Overdue books
   - This is our Action View. It uses DATEDIFF and comparison operators to filter specifically for overdue books that require a penalty."
   - Useful for librarians to see upcoming deadlines at a glance.
   - This is INNER JOIN
*/
SELECT
    User_Table.name_user,
    Books_Table.title,
    Transaction_Table.due_date,
    DATEDIFF(CURDATE(), Transaction_Table.due_date) AS days_overdue
FROM Transaction_Table
JOIN User_Table ON Transaction_Table.user_id = User_Table.user_id
JOIN Books_Table ON Transaction_Table.book_id = Books_Table.book_id
WHERE Transaction_Table.return_date IS NULL
AND Transaction_Table.due_date < CURDATE()
ORDER BY days_overdue DESC;

-- This finds all late returns and calculates the fine on the spot
/* CALCULATE LATE FINES:
   - Uses DATEDIFF to subtract the due_date from the actual return_date.
   - Multiplies the number of late days by 5.00 (the daily penalty rate).
   - Only selects records where the return_date is actually later than the due_date.
*/
SELECT
    transaction_id,
    DATEDIFF(return_date, due_date) AS days_late,
    (DATEDIFF(return_date, due_date) * 5.00) AS calculated_fine
FROM Transaction_Table
WHERE return_date > due_date;

-- GROUP BY: Books per genre
SELECT genre, COUNT(*) AS total_books
FROM Books_Table
GROUP BY genre
ORDER BY total_books DESC;
