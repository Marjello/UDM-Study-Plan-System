<?php
include('../session.php');
include('../config/db.php');

// Get search parameter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$hasSearched = isset($_GET['search']) && $_GET['search'] !== '';

// Let's check if students table has course_id column
$students_columns_query = "SHOW COLUMNS FROM students LIKE 'course_id'";
$students_columns_result = $conn->query($students_columns_query);

// Let's check if courses table exists and has the expected structure
$check_courses_query = "SHOW TABLES LIKE 'courses'";
$check_courses_result = $conn->query($check_courses_query);

// Determine query based on database structure
try {
    if ($check_courses_result->num_rows > 0 && $students_columns_result->num_rows > 0) {
        // Both courses table and course_id exist
        $students_query = "
            SELECT s.id, s.full_name, c.name AS course_name 
            FROM students s
            JOIN courses c ON s.course_id = c.id
            ORDER BY c.name, s.full_name
        ";
    } else {
        // Get course column information (might be named differently or in a separate table)
        $students_info_query = "DESCRIBE students";
        $students_info_result = $conn->query($students_info_query);
        $course_column = null;
        
        while ($column = $students_info_result->fetch_assoc()) {
            if (strpos(strtolower($column['Field']), 'course') !== false) {
                $course_column = $column['Field'];
                break;
            }
        }
        
        if ($course_column) {
            // Found a course-related column
            $students_query = "
                SELECT s.id, s.full_name, s.$course_column AS course_name
                FROM students s
                ORDER BY s.$course_column, s.full_name
            ";
        } else {
            // Fallback - no course information found
            $students_query = "SELECT id, full_name FROM students ORDER BY full_name";
        }
    }
    
    $students_result = $conn->query($students_query);
} catch (Exception $e) {
    // Ultimate fallback if queries fail
    $students_query = "SELECT id, full_name FROM students ORDER BY full_name";
    $students_result = $conn->query($students_query);
}

// Group students by course
$grouped_students = [];
if ($students_result) {
    while ($student = $students_result->fetch_assoc()) {
        // Check if course_name exists in the result
        $course = isset($student['course_name']) ? $student['course_name'] : 'All Students';
        
        // If course name is empty or null, categorize as "Uncategorized"
        if (empty($course) || $course === null) {
            $course = 'Uncategorized';
        }
        
        $grouped_students[$course][] = $student;
    }
} 

// If no students were found, create an empty group to avoid errors
if (empty($grouped_students)) {
    $grouped_students["All Students"] = [];
}

// Process grades search
if ($hasSearched) {
    // Use prepared statement to prevent SQL injection
    $search_term = "%" . $search . "%";
    
    // Check if search is numeric (likely an ID) or a string (likely a name)
    if (is_numeric($search)) {
        $grades_query = "
            SELECT g.*, s.full_name, sb.code AS subject_code, sb.name AS subject_name, sb.year_level, sb.semester
            FROM student_grades g
            JOIN students s ON g.student_id = s.id
            JOIN subjects sb ON g.subject_id = sb.id
            WHERE s.id = ?
            ORDER BY sb.year_level, sb.semester, sb.code
        ";
        $stmt = $conn->prepare($grades_query);
        $stmt->bind_param("s", $search);
    } else {
        $grades_query = "
            SELECT g.*, s.full_name, sb.code AS subject_code, sb.name AS subject_name, sb.year_level, sb.semester
            FROM student_grades g
            JOIN students s ON g.student_id = s.id
            JOIN subjects sb ON g.subject_id = sb.id
            WHERE s.full_name LIKE ?
            ORDER BY sb.year_level, sb.semester, sb.code
        ";
        $stmt = $conn->prepare($grades_query);
        $stmt->bind_param("s", $search_term);
    }
    
    $stmt->execute();
    $grades = $stmt->get_result();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UDM Study Plan Generator - Manage Student Grades</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #4361ee; --primary-dark: #3a56d4; --secondary: #4cc9f0; --light: #f8f9fa; --dark: #212529; --gray: #6c757d; --success: #38b000; --warning: #ffb700; --danger: #dc3545; --shadow: 0 4px 6px rgba(0, 0, 0, 0.1); --radius: 10px; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f7fb; color: var(--dark); line-height: 1.6; }
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); color: white; padding: 20px 0; box-shadow: var(--shadow); position: fixed; height: 100vh; overflow-y: auto; transition: all 0.3s; }
        .sidebar-header { padding: 20px 25px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); margin-bottom: 20px; }
        .sidebar-header h2 { font-size: 1.3rem; font-weight: 600; display: flex; align-items: center; }
        .sidebar-header h2 i { margin-right: 12px; font-size: 1.5rem; }
        .sidebar-user { display: flex; align-items: center; padding: 15px 25px; margin-bottom: 20px; }
        .user-avatar { width: 48px; height: 48px; border-radius: 50%; background-color: rgba(255, 255, 255, 0.2); display: flex; align-items: center; justify-content: center; margin-right: 15px; }
        .user-avatar i { font-size: 20px; }
        .user-info h3 { font-size: 1rem; font-weight: 600; margin-bottom: 4px; }
        .user-info p { font-size: 0.8rem; opacity: 0.7; }
        .nav-menu { list-style: none; }
        .nav-item { margin-bottom: 5px; }
        .nav-link { display: flex; align-items: center; padding: 12px 25px; color: rgba(255, 255, 255, 0.8); text-decoration: none; font-weight: 500; transition: all 0.3s; }
        .nav-link:hover, .nav-link.active { background-color: rgba(255, 255, 255, 0.1); color: white; padding-left: 30px; }
        .nav-link i { margin-right: 12px; font-size: 1.1rem; width: 24px; text-align: center; }
        .logout { position: absolute; bottom: 20px; width: 100%; padding: 0 25px; }
        .logout-btn { display: flex; align-items: center; background-color: rgba(255, 255, 255, 0.1); color: white; padding: 12px 16px; border-radius: var(--radius); text-decoration: none; transition: all 0.3s; width: calc(100% - 50px); }
        .logout-btn:hover { background-color: rgba(255, 255, 255, 0.2); }
        .logout-btn i { margin-right: 10px; }
        .main { flex: 1; margin-left: 280px; padding: 30px; transition: all 0.3s; }
        .main-header { margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-start; }
        .main-title { font-size: 1.8rem; font-weight: 600; color: var(--dark); margin-bottom: 10px; }
        .sub-title { color: var(--gray); font-size: 1rem; margin-bottom: 20px; }
        .content-card { background-color: white; border-radius: var(--radius); padding: 25px; box-shadow: var(--shadow); margin-bottom: 30px; }
        .btn { display: inline-flex; align-items: center; padding: 10px 16px; border-radius: var(--radius); text-decoration: none; font-weight: 500; font-size: 0.9rem; transition: all 0.3s; cursor: pointer; border: none; }
        .btn i { margin-right: 8px; }
        .btn-primary { background-color: var(--primary); color: white; }
        .btn-primary:hover { background-color: var(--primary-dark); }
        .btn-success { background-color: var(--success); color: white; }
        .btn-success:hover { background-color: #2d9000; }
        .btn-danger { background-color: var(--danger); color: white; }
        .btn-danger:hover { background-color: #c82333; }
        .btn-warning { background-color: var(--warning); color: white; }
        .btn-warning:hover { background-color: #e0a800; }
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .data-table th, .data-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e9ecef; }
        .data-table th { background-color: #f8f9fa; font-weight: 600; color: var(--dark); }
        .data-table tr:hover { background-color: #f1f3f5; }
        .data-table .actions { display: flex; gap: 10px; }
        .btn-sm { padding: 6px 10px; font-size: 0.8rem; }
        .btn-edit { background-color: var(--warning); color: white; }
        .btn-edit:hover { background-color: #e0a800; }
        .btn-delete { background-color: var(--danger); color: white; }
        .btn-delete:hover { background-color: #c82333; }
        .search-bar { display: flex; width: 100%; margin-bottom: 20px; gap: 10px; }
        .search-input { flex: 1; padding: 12px 15px; border-radius: var(--radius); border: 1px solid #e0e0e0; font-size: 0.95rem; transition: all 0.3s; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); }
        .search-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2); outline: none; }
        .empty-state { text-align: center; padding: 50px 20px; }
        .empty-state-icon { font-size: 4rem; color: var(--gray); margin-bottom: 20px; opacity: 0.5; }
        .empty-state-title { font-size: 1.5rem; font-weight: 600; margin-bottom: 10px; color: var(--dark); }
        .empty-state-subtitle { font-size: 1rem; color: var(--gray); max-width: 500px; margin: 0 auto 20px; }
        .subject-box { background-color: #f9f9f9; padding: 15px; margin-bottom: 20px; border-radius: 10px; box-shadow: var(--shadow); }
        .subject-header { font-size: 1.3rem; font-weight: 600; margin-bottom: 15px; }
        .subject-list { display: flex; flex-direction: column; }
        .subject-item { padding: 10px; margin: 5px 0; border: 1px solid #ddd; border-radius: 5px; background-color: #fff; }
        .subject-item:hover { background-color: #e9ecef; }
        /* New styles for select2-like dropdown */
        .select-container { flex: 1; position: relative; }
        .custom-select { width: 100%; padding: 12px 15px; border-radius: var(--radius); border: 1px solid #e0e0e0; font-size: 0.95rem; transition: all 0.3s; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); appearance: none; -webkit-appearance: none; }
        .custom-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2); outline: none; }
        .select-arrow { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); pointer-events: none; color: var(--gray); }
        .select-heading { font-weight: bold; background-color: #f0f2f5; color: var(--dark); }
        .action-buttons { display: flex; gap: 10px; margin-top: 10px; }
        @media (max-width: 992px) {
            .sidebar { width: 240px; }
            .main { margin-left: 240px; }
            .main-header { flex-direction: column; }
            .main-header .btn { margin-top: 15px; align-self: flex-start; }
            .action-buttons { flex-direction: column; gap: 5px; }
            .action-buttons .btn { width: 100%; justify-content: center; }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); z-index: 1000; }
            .sidebar.show { transform: translateX(0); }
            .main { margin-left: 0; }
            .hamburger { position: fixed; top: 20px; left: 20px; z-index: 1001; background-color: var(--primary); width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: var(--shadow); }
            .hamburger i { color: white; font-size: 1.2rem; }
            .search-bar { flex-direction: column; }
            .search-bar .btn { width: 100%; justify-content: center; }
            .main-header { padding-top: 20px; }
        }
    </style>
    <!-- Add Select2 CSS (optional but makes the searchable dropdown nicer) -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
</head>
<body>
    <div class="container">
        <div class="hamburger" style="display: none;"><i class="fas fa-bars"></i></div>
        <div class="sidebar">
            <div class="sidebar-header"><h2><i class="fas fa-book-open"></i> UDM Study Plan Generator</h2></div>
            <div class="sidebar-user">
                <div class="user-avatar"><i class="fas fa-user"></i></div>
                <div class="user-info">
                    <h3><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Administrator'; ?></h3>
                    <p>Administrator</p>
                </div>
            </div>
            <ul class="nav-menu">
                <li class="nav-item"><a href="../dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="nav-item"><a href="../departments/index.php" class="nav-link"><i class="fas fa-building"></i> Departments</a></li>
                <li class="nav-item"><a href="../courses/index.php" class="nav-link"><i class="fas fa-graduation-cap"></i> Program</a></li>
                <li class="nav-item"><a href="../subjects/index.php" class="nav-link"><i class="fas fa-book"></i> Subjects</a></li>
                <li class="nav-item"><a href="../students/index.php" class="nav-link"><i class="fas fa-users"></i> Students</a></li>
                <li class="nav-item"><a href="#" class="nav-link active"><i class="fas fa-chart-line"></i> Grades</a></li>
                <li class="nav-item"><a href="../studyplan/index.php" class="nav-link"><i class="fas fa-tasks"></i> Generate Plan</a></li>
            </ul>
            <div class="logout"><a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
        </div>
        <div class="main">
            <div class="main-header">
                <div>
                    <h1 class="main-title">Student Grades</h1>
                    <p class="sub-title">Manage student performance records</p>
                </div>
                <a href="add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Grade</a>
            </div>
            <form method="GET" class="search-bar">
                <div class="select-container">
                    <select name="search" id="student-search" class="custom-select">
                        <option value="">Select a student or type to search...</option>
                        <?php foreach ($grouped_students as $course => $students): ?>
                            <optgroup label="<?= htmlspecialchars($course) ?>">
                                <?php foreach ($students as $student): ?>
                                    <option value="<?= $student['id'] ?>" <?= $search == $student['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($student['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <div class="select-arrow"><i class="fas fa-chevron-down"></i></div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
            </form>
            <div class="content-card">
                <?php if (!$hasSearched): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="fas fa-search"></i></div>
                        <h3 class="empty-state-title">Please search for a student</h3>
                        <p class="empty-state-subtitle">Select a student from the dropdown menu or type a name to search and view their grades and academic performance.</p>
                    </div>
                <?php elseif (isset($grades) && $grades->num_rows > 0): ?>
                    <?php
                    // Show Summary button only when a student is selected and grades are found
                    $student_id = $search;
                    ?>
                    
                    <?php
                    $grouped = [];
                    while ($row = $grades->fetch_assoc()) {
                        $key = "{$row['year_level']} Year - Semester {$row['semester']}";
                        $grouped[$key][] = $row;
                    }
                    foreach ($grouped as $group => $subjects): ?>
                        <h3 style='margin-top: 30px;'><?= htmlspecialchars($group) ?></h3>
                        <div class='table-container'>
                            <table class='data-table'>
                                <thead><tr><th>Student</th><th>Subject</th><th>Status</th><th>Actions</th></tr></thead>
                                <tbody>
                                <?php foreach ($subjects as $subject): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($subject['full_name']) ?></td>
                                        <td><?= htmlspecialchars($subject['subject_code']) . " - " . htmlspecialchars($subject['subject_name']) ?></td>
                                        <td><?= htmlspecialchars($subject['status']) ?></td>
                                        <td class='actions'>
                                            <a href='edit.php?id=<?= $subject['id'] ?>' class='btn btn-sm btn-edit'><i class='fas fa-edit'></i> Edit</a>
                                            <a href='delete.php?id=<?= $subject['id'] ?>' onclick='return confirm("Are you sure you want to delete this grade entry?")' class='btn btn-sm btn-delete'><i class='fas fa-trash'></i> Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="fas fa-exclamation-circle"></i></div>
                        <h3 class="empty-state-title">No results found</h3>
                        <p class="empty-state-subtitle">No student grades match your selection. Try selecting a different student.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Add jQuery and Select2 JS (needed for searchable dropdown) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const hamburger = document.querySelector('.hamburger');
            const sidebar = document.querySelector('.sidebar');
            
            if (window.innerWidth <= 768) { 
                document.querySelector('.hamburger').style.display = 'flex'; 
            }
            
            if (hamburger) { 
                hamburger.addEventListener('click', function() { 
                    sidebar.classList.toggle('show'); 
                }); 
            }
            
            window.addEventListener('resize', function() {
                if (window.innerWidth <= 768) {
                    document.querySelector('.hamburger').style.display = 'flex';
                } else {
                    document.querySelector('.hamburger').style.display = 'none';
                    sidebar.classList.remove('show');
                }
            });
            
            // Initialize Select2 for searchable dropdown
            $(document).ready(function() {
                $('#student-search').select2({
                    placeholder: "Select a student or type to search...",
                    allowClear: true,
                    width: '100%'
                });
            });
        });
    </script>
</body>
</html>