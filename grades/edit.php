<?php
include('../config/db.php');

// Make sure we have session included
if (!isset($_SESSION)) {
    session_start();
}

// Check if database connection is valid
if (!$conn) {
    die("Database connection failed");
}

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = $_GET['id'];

// Fetch the grade record
$stmt = $conn->prepare("SELECT * FROM student_grades WHERE id = ?");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: index.php");
    exit;
}

$grade = $result->fetch_assoc();

// Fetch students and subjects
$students_result = $conn->query("SELECT * FROM students");
$subjects_result = $conn->query("SELECT * FROM subjects");

// Verify queries succeeded
if (!$students_result || !$subjects_result) {
    die("Error fetching data: " . $conn->error);
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = $_POST['student_id'];
    $subject_id = $_POST['subject_id'];
    $status = $_POST['status'];

    $update_stmt = $conn->prepare("UPDATE student_grades SET student_id = ?, subject_id = ?, status = ? WHERE id = ?");
    if (!$update_stmt) {
        die("Prepare failed: " . $conn->error);
    }
    
    $update_stmt->bind_param("iisi", $student_id, $subject_id, $status, $id);
    
    if (!$update_stmt->execute()) {
        die("Execute failed: " . $update_stmt->error);
    }

    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UDM Study Plan Generator - Edit Grade</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #4cc9f0;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --success: #38b000;
            --warning: #ffb700;
            --danger: #dc3545;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --radius: 10px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fb;
            color: var(--dark);
            line-height: 1.6;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 20px 0;
            box-shadow: var(--shadow);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s;
        }

        .sidebar-header {
            padding: 20px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .sidebar-header h2 {
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .sidebar-header h2 i {
            margin-right: 12px;
            font-size: 1.5rem;
        }

        .sidebar-user {
            display: flex;
            align-items: center;
            padding: 15px 25px;
            margin-bottom: 20px;
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }

        .user-avatar i {
            font-size: 20px;
        }

        .user-info h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .user-info p {
            font-size: 0.8rem;
            opacity: 0.7;
        }

        .nav-menu {
            list-style: none;
        }

        .nav-item {
            margin-bottom: 5px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }

        .nav-link:hover, .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            padding-left: 30px;
        }

        .nav-link i {
            margin-right: 12px;
            font-size: 1.1rem;
            width: 24px;
            text-align: center;
        }

        .logout {
            position: absolute;
            bottom: 20px;
            width: 100%;
            padding: 0 25px;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            padding: 12px 16px;
            border-radius: var(--radius);
            text-decoration: none;
            transition: all 0.3s;
            width: calc(100% - 50px);
        }

        .logout-btn:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .logout-btn i {
            margin-right: 10px;
        }

        /* Main Content */
        .main {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
            transition: all 0.3s;
        }

        .main-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .main-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .sub-title {
            color: var(--gray);
            font-size: 1rem;
        }

        /* Content Card */
        .content-card {
            background-color: white;
            border-radius: var(--radius);
            padding: 25px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            max-width: 600px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ced4da;
            border-radius: var(--radius);
            font-size: 1rem;
            transition: border-color 0.15s ease-in-out;
        }

        .form-control:focus {
            border-color: var(--primary);
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
        }

        select.form-control {
            cursor: pointer;
            appearance: none;
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236c757d' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E") no-repeat;
            background-position: calc(100% - 15px) center;
            background-color: white;
            padding-right: 35px;
        }

        /* Button Styles */
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 10px 16px;
            border-radius: var(--radius);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
        }

        .btn i {
            margin-right: 8px;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-secondary {
            background-color: var(--gray);
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        .form-actions {
            display: flex;
            gap: 10px;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: 240px;
            }
            .main {
                margin-left: 240px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 1000;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main {
                margin-left: 0;
            }
            .toggle-btn {
                display: block;
            }
            
            .hamburger {
                position: fixed;
                top: 20px;
                left: 20px;
                z-index: 1001;
                background-color: var(--primary);
                width: 40px;
                height: 40px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                box-shadow: var(--shadow);
            }
            
            .hamburger i {
                color: white;
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Mobile menu toggle button -->
        <div class="hamburger" style="display: none;">
            <i class="fas fa-bars"></i>
        </div>
        
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-book-open"></i> UDM Study Plan Generator</h2>
            </div>
            
            <div class="sidebar-user">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-info">
                    <h3><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Administrator'; ?></h3>
                    <p>Administrator</p>
                </div>
            </div>
            
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="../dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
        
                <li class="nav-item">
                    <a href="../departments/index.php" class="nav-link">
                        <i class="fas fa-building"></i> Departments
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../courses/index.php" class="nav-link">
                        <i class="fas fa-graduation-cap"></i> Program
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../subjects/index.php" class="nav-link">
                        <i class="fas fa-book"></i> Subjects
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../students/index.php" class="nav-link">
                        <i class="fas fa-users"></i> Students
                    </a>
                </li>
                <li class="nav-item">
                    <a href="index.php" class="nav-link active">
                        <i class="fas fa-chart-line"></i> Grades
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../studyplan/index.php" class="nav-link">
                        <i class="fas fa-tasks"></i> Generate Plan
                    </a>
                </li>
            </ul>
            
            <div class="logout">
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main">
            <div class="main-header">
                <div>
                    <h1 class="main-title">Edit Grade</h1>
                    <p class="sub-title">Update student's subject grade</p>
                </div>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Grades
                </a>
            </div>
            
            <div class="content-card">
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Student:</label>
                        <select name="student_id" class="form-control" required>
                            <?php while ($s = $students_result->fetch_assoc()): ?>
                                <option value="<?= $s['id'] ?>" <?= $s['id'] == $grade['student_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['full_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Subject:</label>
                        <select name="subject_id" class="form-control" required>
                            <?php while ($sub = $subjects_result->fetch_assoc()): ?>
                                <option value="<?= $sub['id'] ?>" <?= $sub['id'] == $grade['subject_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sub['code']) ?> - <?= htmlspecialchars($sub['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Status:</label>
                        <select name="status" class="form-control" required>
                            <option value="Passed" <?= $grade['status'] == 'Passed' ? 'selected' : '' ?>>Passed</option>
                            <option value="Failed" <?= $grade['status'] == 'Failed' ? 'selected' : '' ?>>Failed</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Grade
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // For mobile responsiveness
        document.addEventListener('DOMContentLoaded', function() {
            const hamburger = document.querySelector('.hamburger');
            const sidebar = document.querySelector('.sidebar');
            
            // Show hamburger menu on mobile
            if (window.innerWidth <= 768) {
                document.querySelector('.hamburger').style.display = 'flex';
            }
            
            // Toggle sidebar on hamburger click
            if (hamburger) {
                hamburger.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                });
            }
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth <= 768) {
                    document.querySelector('.hamburger').style.display = 'flex';
                } else {
                    document.querySelector('.hamburger').style.display = 'none';
                    sidebar.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>