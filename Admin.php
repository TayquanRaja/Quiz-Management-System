<?php
session_start();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>QuizVerse Admin Panel</title>

    <link rel="stylesheet" href="Admin.css">
     <link
      href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
      rel="stylesheet"
    />
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    />
    <style>
        /* Small layout adjustment for form dropdowns to match input styling */
        select {
            width: 100%;
            padding: 12px 16px;
            margin: 10px 0 16px 0;
            border: 1.5px solid var(--color-border);
            border-radius: 10px;
            font-size: 14px;
            outline: none;
            background: var(--color-card-bg);
            color: var(--color-text-primary);
            transition: all 0.2s ease;
            cursor: pointer;
        }
        select:focus {
            border-color: var(--color-sidebar-bg);
            box-shadow: 0 0 0 4px rgba(76, 94, 61, 0.15);
        }
    </style>
</head>

<body>

<header class="header-nav">
    <div class="topbar-left">
        <div class="logo">
            <div class="logo-box">
                <img src="quizverse-logo.png" alt="QuizVerse Logo" onerror="this.style.display = 'none'">
            </div>
            <h2>QuizVerse</h2>
        </div>

        <nav class="topbar-nav-links">
            <div class="header-dropdown-container">
                <a href="#" class="simple-nav-icon" id="themeBtn" title="Change Theme">
                    <i class="fa-regular fa-moon" style="font-size: 20px"></i>
                </a>
                <div class="dropdown-menu" id="themeMenu">
                    <button onclick="setTheme('light')"><i class="fa-solid fa-sun"></i> Light Default</button>
                    <button onclick="setTheme('dark')"><i class="fa-solid fa-moon"></i> Dark Onyx</button>
                    <button onclick="setTheme('blue')"><i class="fa-solid fa-droplet"></i> Blue Fluorescent</button>
                </div>
            </div>

            <div class="header-dropdown-container">
                <a href="#" class="simple-nav-icon" id="textBtn" title="Text Size Scaling">
                    <i class="fa-solid fa-text-height" style="font-size: 19px"></i>
                </a>
                <div class="dropdown-menu" id="textMenu">
                    <button onclick="setTextSize('small')">A- Small Scale</button>
                    <button onclick="setTextSize('medium')">A Default Medium</button>
                    <button onclick="setTextSize('large')">A+ Large Scale</button>
                </div>
            </div>
        </nav>
    </div>
</header>

<div class="header-section">
    <h1>ADMIN <span>DASHBOARD</span></h1>
    <p>Manage users efficiently and monitor system activity.</p>
</div>

<main class="main">

    <h1>Users (<span id="userCount">0</span>)</h1>

    <div class="form-box">
        <h2>Add New User</h2>

        <input type="text" id="name" placeholder="Enter Name">
        <input type="email" id="email" placeholder="Enter Email">
        
        <label for="role" style="font-size: 14px; font-weight: 500;">Assign System Role:</label>
        <select id="role">
            <option value="student">Student</option>
            <option value="teacher">Teacher</option>
           
        </select>

        <label for="securityQuestion" style="font-size: 14px; font-weight: 500;">Security Question:</label>
        <select id="securityQuestion">
            <option value="What is your pet's name?">What is your pet's name?</option>
            <option value="What is your mother's maiden name?">What is your mother's maiden name?</option>
            <option value="What was the name of your first school?">What was the name of your first school?</option>
            <option value="In what city were you born?">In what city were you born?</option>
        </select>

        <input type="text" id="securityAnswer" placeholder="Enter Security Answer">

        <button onclick="addUser()">Add User</button>
    </div>

    <div class="table-box">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Security Question</th>
                    <th>Answer</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody id="userTable"></tbody>
        </table>
    </div>

</main>

<div class="modal" id="popup">
    <div class="modal-content">
        <h3 id="popupTitle"></h3>
        <p id="popupMessage"></p>
        <button onclick="closePopup()">OK</button>
    </div>
</div>

<div class="modal" id="confirmModal">
    <div class="modal-content">
        <h3>Confirm Delete</h3>
        <p>Are you sure you want to delete this user?</p>
        <div class="modal-actions">
            <button class="danger" onclick="confirmDelete()">Yes, Delete</button>
            <button class="btn-secondary" onclick="closeConfirm()">No</button>
        </div>
    </div>
</div>

<div class="modal" id="editModal" style="max-width: 100%;">
    <div class="modal-content" style="max-width: 450px;">
        <h3>Edit User</h3>
        <input type="text" id="editName" placeholder="Name">
        <input type="email" id="editEmail" placeholder="Email">
        
        <label for="editRole" style="font-size: 14px; font-weight: 500; text-align: left; display: block;">Role:</label>
        <select id="editRole">
            <option value="student">Student</option>
            <option value="teacher">Teacher</option>
            <option value="admin">Admin</option>
        </select>

        <label for="editSecurityQuestion" style="font-size: 14px; font-weight: 500; text-align: left; display: block;">Security Question:</label>
        <select id="editSecurityQuestion">
            <option value="What is your pet's name?">What is your pet's name?</option>
            <option value="What is your mother's maiden name?">What is your mother's maiden name?</option>
            <option value="What was the name of your first school?">What was the name of your first school?</option>
            <option value="In what city were you born?">In what city were you born?</option>
        </select>

        <input type="text" id="editSecurityAnswer" placeholder="Security Answer">

        <div class="modal-actions">
            <button onclick="saveUser()">Save Changes</button>
            <button class="btn-secondary" onclick="closeEdit()">Cancel</button>
        </div>
    </div>
</div>

<script src="Admin.js"></script>

</body>
</html>