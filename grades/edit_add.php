<?php
include('../config/db.php');

// Make sure we have session included
if (!isset($_SESSION)) {
    session_start();
}

// Check if database connection is valid before running queries
if (!$conn) {
    die("Database connection failed");
}

// Check if we have required parameters
if (!isset($_GET['student_id']) || !isset($_GET['subject_id'])) {
    header("Location: index.php");
    exit;
}

$student_id = (int)$_GET['student_id'];
$subject_id = (int)$_GET['subject_id'];

// Fetch student information
$student_query = "SELECT s.*, c.id as course_id, c.course_name 
                 FROM students s 
                 LEFT JOIN courses c ON s.course_id = c.id 
                 WHERE s.id = ?";
$stmt = $conn->prepare($student_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student_result = $stmt->get_result();

if ($student_result->num_rows == 0) {
    // Student not found
    header("Location: index.php");
    exit;
}

$student = $student_result->fetch_assoc();

// Fetch subject information
$subject_query = "SELECT * FROM subjects WHERE id = ?";
$stmt = $conn->prepare($subject_query);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$subject_result = $stmt->get_result();

if ($subject_result->num_rows == 0) {
    // Subject not found
    header("Location: index.php");
    exit;
}

$subject = $subject_result->fetch_assoc();

// Fetch current grade
$grade_query = "SELECT * FROM student_grades WHERE student_id = ? AND subject_id = ?";
$stmt = $conn->prepare($grade_query);
$stmt->bind_param("ii", $student_id, $subject_id);
$stmt->execute();
$grade_result = $stmt->get_result();

if ($grade_result->num_rows == 0) {
    // Grade not found - redirect to add grade
    header("Location: add.php");
    exit;
}

$grade = $grade_result->fetch_assoc();
$current_status = $grade['status'];

// Process form submission
$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_grade'])) {
        $new_status = $_POST['status'];
        
        // Validate status
        if ($new_status != 'Passed' && $new_status != 'Failed') {
            $error_message = "Invalid grade status. Please select either Passed or Failed.";
        } else {
            // Update the grade
            $update_query = "UPDATE student_grades SET status = ? WHERE student_id = ? AND subject_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("sii", $new_status, $student_id, $subject_id);
            
            if ($stmt->execute()) {
                $success_message = "Grade successfully updated!";
                $current_status = $new_status; // Update current status for display
            } else {
                $error_message = "Error updating grade: " . $conn->error;
            }
        }
    } elseif (isset($_POST['delete_grade'])) {
        // Delete the grade
        $delete_query = "DELETE FROM student_grades WHERE student_id = ? AND subject_id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param("ii", $student_id, $subject_id);
        
        if ($stmt->execute()) {
            $success_message = "Grade successfully deleted!";
            // Redirect after short delay
            header("refresh:2;url=add.php");
        } else {
            $error_message = "Error deleting grade: " . $conn->error;
        }
    }
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
        /* Copy all the styles from add.php */
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
            z-index: 1000;
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
            max-width: 900px;
            width: 100%;
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

        /* Button Styles */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border-radius: var(--radius);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            min-width: 120px;
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

        .btn-danger {
            background-color: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background-color: #c82333;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            justify-content: space-between;
        }

        /* Alert messages */
        .alert {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: var(--radius);
            font-weight: 500;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Radio button styling */
        .radio-container {
            display: flex;
            gap: 15px;
        }
        
        .radio-btn {
            cursor: pointer;
            padding: 6px 12px;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .radio-passed {
            background-color: rgba(56, 176, 0, 0.1);
            color: var(--success);
            border: 1px solid var(--success);
        }
        
        .radio-passed.active {
            background-color: var(--success);
            color: white;
        }
        
        .radio-failed {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger);
            border: 1px solid var(--danger);
        }
        
        .radio-failed.active {
            background-color: var(--danger);
            color: white;
        }
        
        .hidden-radio {
            display: none;
        }

        /* Info Panel */
        .info-panel {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: var(--radius);
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            margin-bottom: 10px;
        }

        .info-label {
            width: 120px;
            font-weight: 500;
        }

        .info-value {
            flex: 1;
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
                <a href="add.php?student_id=<?= $student_id ?>&show_grades=1" class="btn btn-secondary">
    <i class="fas fa-arrow-left"></i> Back to Add Grades
</a>
            </div>
            
            <div class="content-card">
                <!-- Success/Error messages -->
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>
                
                <!-- Student and Subject Information -->
                <div class="info-panel">
                    <div class="info-row">
                        <div class="info-label">Student:</div>
                        <div class="info-value"><?= htmlspecialchars($student['full_name']) ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Course:</div>
                        <div class="info-value"><?= htmlspecialchars($student['course_name']) ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Subject Code:</div>
                        <div class="info-value"><?= htmlspecialchars($subject['code']) ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Subject Name:</div>
                        <div class="info-value"><?= htmlspecialchars($subject['name']) ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Current Status:</div>
                        <div class="info-value">
                            <?php if ($current_status == 'Passed'): ?>
                                <span style="color: var(--success); font-weight: bold;">
                                    <i class="fas fa-check-circle"></i> Passed
                                </span>
                            <?php else: ?>
                                <span style="color: var(--danger); font-weight: bold;">
                                    <i class="fas fa-times-circle"></i> Failed
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Edit Form -->
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">Update Status:</label>
                        <div class="radio-container">
                            <!-- Passed option -->
                            <input type="radio" id="status_passed" name="status" value="Passed" class="hidden-radio" <?= $current_status == 'Passed' ? 'checked' : '' ?>>
                            <label for="status_passed" class="radio-btn radio-passed <?= $current_status == 'Passed' ? 'active' : '' ?>">
                                <i class="fas fa-check"></i> Passed
                            </label>
                            
                            <!-- Failed option -->
                            <input type="radio" id="status_failed" name="status" value="Failed" class="hidden-radio" <?= $current_status == 'Failed' ? 'checked' : '' ?>>
                            <label for="status_failed" class="radio-btn radio-failed <?= $current_status == 'Failed' ? 'active' : '' ?>">
                                <i class="fas fa-times"></i> Failed
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <div>
                            <button type="submit" name="delete_grade" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this grade? This action cannot be undone.')">
                                <i class="fas fa-trash"></i> Delete Grade
                            </button>
                        </div>
                        <div>
                        <a href="add.php?student_id=<?= $student_id ?>&show_grades=1" class="btn btn-secondary">
    <i class="fas fa-times"></i> Cancel
</a>
                            <button type="submit" name="update_grade" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Grade
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // For mobile responsiveness
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

        // Radio button styling
        const passedRadio = document.getElementById('status_passed');
        const failedRadio = document.getElementById('status_failed');
        const passedLabel = document.querySelector('label[for="status_passed"]');
        const failedLabel = document.querySelector('label[for="status_failed"]');
        
        if (passedRadio && failedRadio) {
            passedRadio.addEventListener('change', function() {
                if (this.checked) {
                    passedLabel.classList.add('active');
                    failedLabel.classList.remove('active');
                }
            });
            
            failedRadio.addEventListener('change', function() {
                if (this.checked) {
                    failedLabel.classList.add('active');
                    passedLabel.classList.remove('active');
                }
            });
        }
    });
    </script>
</body>
</html>