<?php
session_start();
$username = isset($_SESSION['username']) ? $_SESSION['username'] : "Administrator";

// DB connection
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "studyplan";       // Confirm this DB exists

$student_count = 0;
$subject_count = 0;
$course_count = 0;
$plan_count = 0;
$new_students = 0;

try {
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Test connection
    $conn->query("SELECT 1");

    // Get counts
    $student_count = $conn->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $subject_count = $conn->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
    $course_count = $conn->query("SELECT COUNT(*) FROM courses")->fetchColumn();

    $columns = $conn->query("SHOW COLUMNS FROM students LIKE 'created_at'");
    if ($columns->rowCount() > 0) {
        $new_students = $conn->query("
            SELECT COUNT(*) FROM students 
            WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
            AND YEAR(created_at) = YEAR(CURRENT_DATE())
        ")->fetchColumn();
    }

} catch (PDOException $e) {
    error_log("DB ERROR: " . $e->getMessage());
    echo "<p style='color:red;'>Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UDM Study Plan Generator - Dashboard</title>
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

        /* Dashboard Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background-color: white;
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card .icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-bottom: 15px;
            font-size: 1.3rem;
        }

        .stat-card .title {
            font-size: 1rem;
            color: var(--gray);
            margin-bottom: 5px;
        }

        .stat-card .value {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .stat-card .info {
            font-size: 0.85rem;
            color: var(--gray);
        }

        .blue-bg {
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }

        .green-bg {
            background-color: rgba(56, 176, 0, 0.1);
            color: var(--success);
        }

        .yellow-bg {
            background-color: rgba(255, 183, 0, 0.1);
            color: var(--warning);
        }

        .red-bg {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        /* Modules Section */
        .modules-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--dark);
        }

        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .module-card {
            background-color: white;
            border-radius: var(--radius);
            padding: 25px;
            box-shadow: var(--shadow);
            border-left: 4px solid var(--primary);
            transition: all 0.3s;
        }

        .module-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
        }

        .module-card .module-icon {
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 15px;
        }

        .module-card h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--dark);
        }

        .module-card p {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .btn {
            display: inline-block;
            padding: 10px 16px;
            border-radius: var(--radius);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-outline {
            border: 1px solid var(--primary);
            color: var(--primary);
            background-color: transparent;
        }

        .btn-outline:hover {
            background-color: var(--primary);
            color: white;
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
                    <h3><?php echo htmlspecialchars($username); ?></h3>
                    <p>Administrator</p>
                </div>
            </div>
            
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link active">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
            
                <li class="nav-item">
                    <a href="departments/index.php" class="nav-link">
                        <i class="fas fa-building"></i> Departments
                    </a>
                </li>
                <li class="nav-item">
                    <a href="courses/index.php" class="nav-link">
                        <i class="fas fa-graduation-cap"></i> Program
                    </a>
                </li>
                <li class="nav-item">
                    <a href="subjects/index.php" class="nav-link">
                        <i class="fas fa-book"></i> Subjects
                    </a>
                </li>
                <li class="nav-item">
                    <a href="students/index.php" class="nav-link">
                        <i class="fas fa-users"></i> Students
                    </a>
                </li>
                <li class="nav-item">
                    <a href="grades/index.php" class="nav-link">
                        <i class="fas fa-chart-line"></i> Grades
                    </a>
                </li>
                <li class="nav-item">
                    <a href="studyplan/index.php" class="nav-link">
                        <i class="fas fa-tasks"></i> Generate Plan
                    </a>
                </li>
            </ul>
            
            <div class="logout">
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main">

        
            <div class="main-header">
                <h1 class="main-title">Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
                <p class="sub-title"></p>
            </div>
            
            <!-- Stats Overview -->
            <div class="stats-container">
    <!-- Students Card -->
    <div class="stat-card blue-bg">
        <div class="icon"><i class="fas fa-users"></i></div>
        <div class="title">Students</div>
        <div class="value"><?php echo $student_count; ?></div>
        <div class="info"><?php echo $new_students; ?> new this month</div>
    </div>

    <!-- Subjects Card -->
    <div class="stat-card green-bg">
        <div class="icon"><i class="fas fa-book"></i></div>
        <div class="title">Subjects</div>
        <div class="value"><?php echo $subject_count; ?></div>
        <div class="info">Total Subjects Offered</div>
    </div>

    <!-- Courses Card -->
    <div class="stat-card yellow-bg">
        <div class="icon"><i class="fas fa-graduation-cap"></i></div>
        <div class="title">Courses</div>
        <div class="value"><?php echo $course_count; ?></div>
        <div class="info">Active Program</div>
    </div>
</div>

            
            <!-- Modules Section -->
            <h2 class="modules-title">System Modules</h2>
            <div class="modules-grid">

                
                <div class="module-card">
                    <div class="module-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <h3>Manage Departments</h3>
                    <p>Add, edit and organize academic departments and their faculty members.</p>
                    <a href="departments/index.php" class="btn btn-primary">Manage <i class="fas fa-arrow-right"></i></a>
                </div>
                
                <div class="module-card">
                    <div class="module-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h3>Manage Programs</h3>
                    <p>Create and modify programs offered by the institution across departments.</p>
                    <a href="courses/index.php" class="btn btn-primary">Manage <i class="fas fa-arrow-right"></i></a>
                </div>
                
                <div class="module-card">
                    <div class="module-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <h3>Manage Subjects</h3>
                    <p>Add and organize subjects that make up the program's curriculum.</p>
                    <a href="subjects/index.php" class="btn btn-primary">Manage <i class="fas fa-arrow-right"></i></a>
                </div>
                
                <div class="module-card">
                    <div class="module-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Manage Students</h3>
                    <p>Add, edit and manage student profiles, enrollments and personal details.</p>
                    <a href="students/index.php" class="btn btn-primary">Manage <i class="fas fa-arrow-right"></i></a>
                </div>
                
                <div class="module-card">
                    <div class="module-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Manage Grades</h3>
                    <p>Record, update and review student academic performance and grades.</p>
                    <a href="grades/index.php" class="btn btn-primary">Manage <i class="fas fa-arrow-right"></i></a>
                </div>
                
                <div class="module-card">
                    <div class="module-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <h3>Generate Study Plan</h3>
                    <p>Create customized study plans based on student profiles and academic requirements.</p>
                    <a href="studyplan/index.php" class="btn btn-outline">Generate <i class="fas fa-file-alt"></i></a>
                </div>
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
<html>