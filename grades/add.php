<?php
include('../config/db.php');
if (!isset($_SESSION)) session_start();
if (!$conn) die("Database connection failed");

$courses_result = $conn->query("SELECT * FROM courses");
if (!$courses_result) die("Error fetching courses: " . $conn->error);
$courses = [];
while ($row = $courses_result->fetch_assoc()) $courses[] = $row;

$existing_grades = []; $passed_subjects = []; $failed_subjects = [];
$grades_result = $conn->query("SELECT student_id, subject_id, status FROM student_grades");
if ($grades_result) {
    while ($row = $grades_result->fetch_assoc()) {
        if (!isset($existing_grades[$row['student_id']])) $existing_grades[$row['student_id']] = [];
        $existing_grades[$row['student_id']][] = $row['subject_id'];
        if ($row['status'] === 'Passed') {
            if (!isset($passed_subjects[$row['student_id']])) $passed_subjects[$row['student_id']] = [];
            $passed_subjects[$row['student_id']][] = $row['subject_id'];
        } else if ($row['status'] === 'Failed') {
            if (!isset($failed_subjects[$row['student_id']])) $failed_subjects[$row['student_id']] = [];
            $failed_subjects[$row['student_id']][] = $row['subject_id'];
        }
    }
}

$students_result = $conn->query("SELECT s.*, c.id as course_id FROM students s LEFT JOIN courses c ON s.course_id = c.id");
$subjects_result = $conn->query("SELECT s.*, c.id as course_id FROM subjects s LEFT JOIN courses c ON s.course_id = c.id ORDER BY s.year_level, s.semester, s.code");

// Fetch postrequisites (subjects that have this subject as a prerequisite)
$postrequisites_query = "SELECT subject_id, prerequisite_id FROM subject_prerequisites";
$postrequisites_result = $conn->query($postrequisites_query);
$postrequisites = [];
if ($postrequisites_result) {
    while ($row = $postrequisites_result->fetch_assoc()) {
        if (!isset($postrequisites[$row['prerequisite_id']])) $postrequisites[$row['prerequisite_id']] = [];
        $postrequisites[$row['prerequisite_id']][] = $row['subject_id'];
    }
}

$prerequisites_result = $conn->query("SELECT subject_id, prerequisite_id FROM subject_prerequisites");
$prerequisites = [];
if ($prerequisites_result) {
    while ($row = $prerequisites_result->fetch_assoc()) {
        if (!isset($prerequisites[$row['subject_id']])) $prerequisites[$row['subject_id']] = [];
        $prerequisites[$row['subject_id']][] = $row['prerequisite_id'];
    }
}
if (!$students_result || !$subjects_result) die("Error fetching data: " . $conn->error);

$students = [];
while ($row = $students_result->fetch_assoc()) $students[] = $row;

$subjects = []; $subjects_by_year_sem = []; $subjects_by_id = [];
while ($row = $subjects_result->fetch_assoc()) {
    $subjects[] = $row;
    $subjects_by_id[$row['id']] = $row;
    $year = $row['year_level']; $sem = $row['semester'];
    $key = "Year $year - $sem Semester";
    if (!isset($subjects_by_year_sem[$key])) $subjects_by_year_sem[$key] = [];
    $subjects_by_year_sem[$key][] = $row;
}

$success_message = ""; $error_message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['save_grades'])) {
        $student_id = $_POST['student_id'] ?? null;
        $course_id = $_POST['course_id'] ?? null;
        $success_count = 0; $error_count = 0;
        
        if (!$student_id || !$course_id) {
            $error_message = "Student and Program information required.";
        } else {
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'subject_status_') === 0) {
                    $subject_id = substr($key, strlen('subject_status_'));
                    $status = $value;
                    if (!empty($status)) {
                        $check_stmt = $conn->prepare("SELECT id FROM student_grades WHERE student_id = ? AND subject_id = ?");
                        $check_stmt->bind_param("ii", $student_id, $subject_id);
                        $check_stmt->execute();
                        $check_stmt->store_result();
                        
                        if ($check_stmt->num_rows > 0) {
                            $update_stmt = $conn->prepare("UPDATE student_grades SET status = ? WHERE student_id = ? AND subject_id = ?");
                            if (!$update_stmt) { $error_count++; continue; }
                            $update_stmt->bind_param("sii", $status, $student_id, $subject_id);
                            if ($update_stmt->execute()) $success_count++; else $error_count++;
                            $update_stmt->close();
                        } else {
                            $insert_stmt = $conn->prepare("INSERT INTO student_grades (student_id, subject_id, status) VALUES (?, ?, ?)");
                            if (!$insert_stmt) { $error_count++; continue; }
                            $insert_stmt->bind_param("iis", $student_id, $subject_id, $status);
                            if ($insert_stmt->execute()) {
                                $success_count++;
                                if (!isset($existing_grades[$student_id])) $existing_grades[$student_id] = [];
                                $existing_grades[$student_id][] = (int)$subject_id;
                            } else {
                                $error_count++;
                            }
                            $insert_stmt->close();
                        }
                        $check_stmt->close();
                    } else if (isset($_POST['reset_' . $subject_id])) {
                        // Handle reset request - delete the grade record
                        $delete_stmt = $conn->prepare("DELETE FROM student_grades WHERE student_id = ? AND subject_id = ?");
                        if (!$delete_stmt) { $error_count++; continue; }
                        $delete_stmt->bind_param("ii", $student_id, $subject_id);
                        if ($delete_stmt->execute()) {
                            $success_count++;
                            // Remove from our arrays to reflect the change immediately
                            if (isset($existing_grades[$student_id])) {
                                $key = array_search((int)$subject_id, $existing_grades[$student_id]);
                                if ($key !== false) {
                                    unset($existing_grades[$student_id][$key]);
                                }
                            }
                            if (isset($passed_subjects[$student_id])) {
                                $key = array_search((int)$subject_id, $passed_subjects[$student_id]);
                                if ($key !== false) {
                                    unset($passed_subjects[$student_id][$key]);
                                }
                            }
                            if (isset($failed_subjects[$student_id])) {
                                $key = array_search((int)$subject_id, $failed_subjects[$student_id]);
                                if ($key !== false) {
                                    unset($failed_subjects[$student_id][$key]);
                                }
                            }
                        } else {
                            $error_count++;
                        }
                        $delete_stmt->close();
                    }
                }
            }
            
            if ($success_count > 0) $success_message = "$success_count grades added, updated, or reset successfully!";
            if ($error_count > 0) $error_message .= ($error_message ? " " : "") . "$error_count grades could not be processed.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UDM Study Plan Generator - Add Grade</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{--primary:#4361ee;--primary-dark:#3a56d4;--secondary:#4cc9f0;--light:#f8f9fa;--dark:#212529;--gray:#6c757d;--success:#38b000;--warning:#ffb700;--danger:#dc3545;--shadow:0 4px 6px rgba(0,0,0,0.1);--radius:10px}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background-color:#f5f7fb;color:var(--dark);line-height:1.6}
        .container{display:flex;min-height:100vh}
        .sidebar{width:280px;background:linear-gradient(135deg,var(--primary) 0%,var(--primary-dark) 100%);color:white;padding:20px 0;box-shadow:var(--shadow);position:fixed;height:100vh;overflow-y:auto;transition:all 0.3s;z-index:1000}
        .sidebar-header{padding:20px 25px;border-bottom:1px solid rgba(255,255,255,0.1);margin-bottom:20px}
        .sidebar-header h2{font-size:1.3rem;font-weight:600;display:flex;align-items:center}
        .sidebar-header h2 i{margin-right:12px;font-size:1.5rem}
        .sidebar-user{display:flex;align-items:center;padding:15px 25px;margin-bottom:20px}
        .user-avatar{width:48px;height:48px;border-radius:50%;background-color:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;margin-right:15px}
        .user-avatar i{font-size:20px}
        .user-info h3{font-size:1rem;font-weight:600;margin-bottom:4px}
        .user-info p{font-size:0.8rem;opacity:0.7}
        .nav-menu{list-style:none}
        .nav-item{margin-bottom:5px}
        .nav-link{display:flex;align-items:center;padding:12px 25px;color:rgba(255,255,255,0.8);text-decoration:none;font-weight:500;transition:all 0.3s}
        .nav-link:hover,.nav-link.active{background-color:rgba(255,255,255,0.1);color:white;padding-left:30px}
        .nav-link i{margin-right:12px;font-size:1.1rem;width:24px;text-align:center}
        .logout{position:absolute;bottom:20px;width:100%;padding:0 25px}
        .logout-btn{display:flex;align-items:center;background-color:rgba(255,255,255,0.1);color:white;padding:12px 16px;border-radius:var(--radius);text-decoration:none;transition:all 0.3s;width:calc(100% - 50px)}
        .logout-btn:hover{background-color:rgba(255,255,255,0.2)}
        .logout-btn i{margin-right:10px}
        .main{flex:1;margin-left:280px;padding:30px;transition:all 0.3s}
        .main-header{margin-bottom:30px;display:flex;justify-content:space-between;align-items:center}
        .main-title{font-size:1.8rem;font-weight:600;color:var(--dark);margin-bottom:10px}
        .sub-title{color:var(--gray);font-size:1rem}
        .content-card{background-color:white;border-radius:var(--radius);padding:25px;box-shadow:var(--shadow);margin-bottom:30px;max-width:1500px;width:100%}
        .form-group{margin-bottom:20px}
        .form-label{display:block;margin-bottom:8px;font-weight:500;color:var(--dark)}
        .form-control{width:100%;padding:10px 15px;border:1px solid #ced4da;border-radius:var(--radius);font-size:1rem;transition:border-color 0.15s ease-in-out}
        .form-control:focus{border-color:var(--primary);outline:0;box-shadow:0 0 0 0.2rem rgba(67,97,238,0.25)}
        select.form-control{cursor:pointer;-webkit-appearance:none;-moz-appearance:none;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236c757d' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 15px center;background-color:white;padding-right:35px}
        .btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 16px;border-radius:var(--radius);text-decoration:none;font-weight:500;font-size:0.9rem;transition:all 0.3s;cursor:pointer;border:none;min-width:120px}
        .btn i{margin-right:8px}
        .btn-primary{background-color:var(--primary);color:white}
        .btn-primary:hover{background-color:var(--primary-dark)}
        .btn-secondary{background-color:var(--gray);color:white}
        .btn-secondary:hover{background-color:#5a6268}
        .btn-edit{background-color:var(--warning);color:white;min-width:auto;padding:6px 12px;font-size:0.8rem}
        .btn-edit:hover{background-color:#e0a500}
        .btn-reset{background-color:var(--secondary);color:white;min-width:auto;padding:6px 12px;font-size:0.8rem;margin-left:5px;}
        .btn-reset:hover{background-color:#3ba8d0}
        .form-actions{display:flex;gap:10px;margin-top:30px}
        .alert{padding:12px 15px;margin-bottom:20px;border-radius:var(--radius);font-weight:500}
        .alert-success{background-color:#d4edda;color:#155724;border:1px solid #c3e6cb}
        .alert-danger{background-color:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
        .grades-table{width:100%;border-collapse:collapse;margin-top:20px}
        .grades-table th,.grades-table td{padding:10px;border:1px solid #e0e0e0}
        .grades-table th{background-color:#f5f7fb;text-align:left;font-weight:500}
        .year-section{margin-bottom:30px}
        .year-section h3{margin-bottom:15px;color:var(--primary);border-bottom:1px solid #e0e0e0;padding-bottom:8px}
        .radio-container{display:flex;gap:15px}
        .radio-btn{cursor:pointer;padding:6px 12px;border-radius:var(--radius);font-weight:500;font-size:0.9rem;display:inline-flex;align-items:center;justify-content:center}
        .radio-passed{background-color:rgba(56,176,0,0.1);color:var(--success);border:1px solid var(--success)}
        .radio-passed.active{background-color:var(--success);color:white}
        .radio-failed{background-color:rgba(220,53,69,0.1);color:var(--danger);border:1px solid var(--danger)}
        .radio-failed.active{background-color:var(--danger);color:white}
        .hidden-radio{display:none}
        .disabled-subject{opacity:0.5;background-color:#f9f9f9}
        .prerequisite-warning{font-size:0.85rem;color:var(--warning);margin-top:5px}
        .submit-row{margin-top:30px;display:flex;justify-content:flex-end}
        .edit-status-container{display:none;margin-top:10px}
        .edit-status-container.active{display:flex;gap:15px}
        .action-btns{display:flex;gap:5px;}
        @media (max-width:992px){.sidebar{width:240px}.main{margin-left:240px}}
        @media (max-width:768px){.sidebar{transform:translateX(-100%);z-index:1000}.sidebar.show{transform:translateX(0)}.main{margin-left:0}.toggle-btn{display:block}.hamburger{position:fixed;top:20px;left:20px;z-index:1001;background-color:var(--primary);width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:var(--shadow)}.hamburger i{color:white;font-size:1.2rem}}
    </style>
</head>
<body>
    <div class="container">
        <div class="hamburger" style="display:none;"><i class="fas fa-bars"></i></div>
        <div class="sidebar">
            <div class="sidebar-header"><h2><i class="fas fa-book-open"></i> UDM Study Plan Generator</h2></div>
            <div class="sidebar-user">
                <div class="user-avatar"><i class="fas fa-user"></i></div>
                <div class="user-info">
                    <h3><?= isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Administrator' ?></h3>
                    <p>Administrator</p>
                </div>
            </div>
            <ul class="nav-menu">
                <li class="nav-item"><a href="../dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="nav-item"><a href="../departments/index.php" class="nav-link"><i class="fas fa-building"></i> Departments</a></li>
                <li class="nav-item"><a href="../courses/index.php" class="nav-link"><i class="fas fa-graduation-cap"></i> Program</a></li>
                <li class="nav-item"><a href="../subjects/index.php" class="nav-link"><i class="fas fa-book"></i> Subjects</a></li>
                <li class="nav-item"><a href="../students/index.php" class="nav-link"><i class="fas fa-users"></i> Students</a></li>
                <li class="nav-item"><a href="index.php" class="nav-link active"><i class="fas fa-chart-line"></i> Grades</a></li>
                <li class="nav-item"><a href="../studyplan/index.php" class="nav-link"><i class="fas fa-tasks"></i> Generate Plan</a></li>
            </ul>
            <div class="logout"><a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
        </div>
        <div class="main">
            <div class="main-header">
                <div>
                    <h1 class="main-title">Add New Grades</h1>
                    <p class="sub-title">Record a student's subject grades</p>
                </div>
                <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Grades</a>
            </div>
            <div class="content-card">
                <?php if (!empty($success_message)): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?></div><?php endif; ?>
                <?php if (!empty($error_message)): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?></div><?php endif; ?>
                <form id="selectionForm">
                    <div class="form-group">
                        <label class="form-label" for="course_id">Program:</label>
                        <select name="course_id" id="course_id" class="form-control" required>
                            <option value="">-- Select Program --</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['course_name'] ?? $course['name'] ?? $course['title'] ?? "Program ID: ".$course['id']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="student_id">Student:</label>
                        <select name="student_id" id="student_id" class="form-control" required>
                            <option value="">-- Select Student --</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= $student['id'] ?>" data-course="<?= $student['course_id'] ?>"><?= htmlspecialchars($student['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
                <form id="gradesForm" method="POST" action="" style="display:none;">
                    <input type="hidden" name="student_id" id="form_student_id">
                    <input type="hidden" name="course_id" id="form_course_id">
                    <div id="subjectsByYearContainer"></div>
                    <div class="submit-row"><button type="submit" name="save_grades" class="btn btn-primary"><i class="fas fa-save"></i> Save All Grades</button></div>
                </form>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const hamburger = document.querySelector('.hamburger'), sidebar = document.querySelector('.sidebar');
        if (window.innerWidth <= 768) hamburger.style.display = 'flex';
        if (hamburger) hamburger.addEventListener('click', () => sidebar.classList.toggle('show'));
        window.addEventListener('resize', () => {
            if (window.innerWidth <= 768) hamburger.style.display = 'flex';
            else { hamburger.style.display = 'none'; sidebar.classList.remove('show'); }
        });
        const courseDropdown = document.getElementById('course_id'), studentDropdown = document.getElementById('student_id');
        const selectionForm = document.getElementById('selectionForm'), gradesForm = document.getElementById('gradesForm');
        const formStudentId = document.getElementById('form_student_id'), formCourseId = document.getElementById('form_course_id');
        const subjectsByYearContainer = document.getElementById('subjectsByYearContainer');
        const existingGrades = <?= json_encode($existing_grades, JSON_NUMERIC_CHECK) ?>;
        const passedSubjects = <?= json_encode($passed_subjects, JSON_NUMERIC_CHECK) ?>;
        const failedSubjects = <?= json_encode($failed_subjects, JSON_NUMERIC_CHECK) ?>;
        const prerequisites = <?= json_encode($prerequisites, JSON_NUMERIC_CHECK) ?>;
        const subjectsByYearSem = <?= json_encode($subjects_by_year_sem, JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const allSubjects = <?= json_encode($subjects, JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const originalStudentsOptions = studentDropdown.innerHTML;

        courseDropdown.addEventListener('change', function() {
            const courseId = this.value;
            studentDropdown.innerHTML = '<option value="">-- Select Student --</option>';
            if (!courseId) { studentDropdown.innerHTML = originalStudentsOptions; return; }
            const studentOptions = document.createElement('div');
            studentOptions.innerHTML = originalStudentsOptions;
            Array.from(studentOptions.querySelectorAll('option[data-course]')).forEach(option => {
                if (option.dataset.course === courseId) studentDropdown.appendChild(option.cloneNode(true));
            });
        });
        studentDropdown.addEventListener('change', function() {
            const studentId = parseInt(this.value), courseId = parseInt(courseDropdown.value);
            if (studentId && courseId) {
                gradesForm.style.display = 'block';
                formStudentId.value = studentId; formCourseId.value = courseId;
                generateSubjectTables(studentId, courseId);
            } else {
                gradesForm.style.display = 'none';
            }
        });
        function generateSubjectTables(studentId, courseId) {
            subjectsByYearContainer.innerHTML = '';
            const takenSubjects = existingGrades[studentId] || [], studentPassedSubjects = passedSubjects[studentId] || [];
            const studentFailedSubjects = failedSubjects[studentId] || [];
            const subjectsById = {};
            allSubjects.forEach(subject => subjectsById[subject.id] = subject);

            for (const [yearSem, subjects] of Object.entries(subjectsByYearSem)) {
                const courseSubjects = subjects.filter(subject => parseInt(subject.course_id) === courseId);
                if (courseSubjects.length === 0) continue;
                const yearMatch = yearSem.match(/Year (\d+)/), semMatch = yearSem.match(/(\d+)(?:st|nd|rd|th) Semester/);
                const currentYear = yearMatch ? parseInt(yearMatch[1]) : 1;
                const currentSemester = semMatch ? parseInt(semMatch[1]) : 1;

                const yearSection = document.createElement('div'); yearSection.className = 'year-section';
                const yearTitle = document.createElement('h3'); yearTitle.textContent = yearSem; yearSection.appendChild(yearTitle);
                const table = document.createElement('table'); table.className = 'grades-table';
                const thead = document.createElement('thead'), headerRow = document.createElement('tr');
                ['Subject Code', 'Subject Name', 'Status', 'Action'].forEach(header => {
                    const th = document.createElement('th'); th.textContent = header; headerRow.appendChild(th);
                });
                thead.appendChild(headerRow); table.appendChild(thead);
                const tbody = document.createElement('tbody');
                courseSubjects.forEach(subject => {
                    const row = document.createElement('tr'), subjectId = parseInt(subject.id);
                    const alreadyPassed = studentPassedSubjects.includes(subjectId);
                    const alreadyTaken = takenSubjects.includes(subjectId);
                    const prereqIssue = checkPrerequisitesStatus(subjectId, studentId, studentPassedSubjects, studentFailedSubjects, subjectsById);
                    const isRetakeOpportunity = checkRetakeOpportunity(subjectId, subject, currentYear, currentSemester, studentFailedSubjects, subjectsById);
                    const shouldDisable = alreadyPassed || (alreadyTaken && !isRetakeOpportunity) || (prereqIssue !== false);
                    if (shouldDisable) row.classList.add('disabled-subject');
                    const codeCell = document.createElement('td'); codeCell.textContent = subject.code; row.appendChild(codeCell);
                    const nameCell = document.createElement('td'); nameCell.textContent = subject.name;
                    if (prereqIssue) {
                        const warning = document.createElement('div'); warning.className = 'prerequisite-warning';
                        warning.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${prereqIssue}`; nameCell.appendChild(warning);
                    } else if (alreadyPassed) {
                        const passedInfo = document.createElement('div'); passedInfo.className = 'prerequisite-warning'; passedInfo.style.color = 'var(--success)';
                        passedInfo.innerHTML = '<i class="fas fa-check-circle"></i> Already passed'; nameCell.appendChild(passedInfo);
                    } else if (isRetakeOpportunity) {
                        const retakeInfo = document.createElement('div'); retakeInfo.className = 'prerequisite-warning'; retakeInfo.style.color = 'var(--primary)';
                        retakeInfo.innerHTML = '<i class="fas fa-sync-alt"></i> Failed subject available for retake'; nameCell.appendChild(retakeInfo);
                    }
                    row.appendChild(nameCell);
                    
                    const statusCell = document.createElement('td');
                    const statusContainer = document.createElement('div');
                    statusContainer.className = 'radio-container';
                    
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = `subject_status_${subjectId}`;
                    hiddenInput.value = '';
                    statusContainer.appendChild(hiddenInput);
                    
                    const passedLabel = createRadioButton('Passed', subjectId, alreadyPassed && !isRetakeOpportunity);
                    const failedLabel = createRadioButton('Failed', subjectId, studentFailedSubjects.includes(subjectId) && !isRetakeOpportunity);
                    
                    if (shouldDisable) {
                        passedLabel.style.pointerEvents = 'none';
                        failedLabel.style.pointerEvents = 'none';
                    }
                    
                    statusContainer.appendChild(passedLabel);
                    statusContainer.appendChild(failedLabel);
                    statusCell.appendChild(statusContainer);
                    row.appendChild(statusCell);
                    
                    const actionCell = document.createElement('td');
                    if (alreadyTaken) {
                        const resetBtn = document.createElement('button');
                        resetBtn.type = 'button';
                        resetBtn.className = 'btn btn-reset';
                        resetBtn.innerHTML = '<i class="fas fa-undo"></i> Reset';
                        resetBtn.onclick = function() {
                            if (confirm('Are you sure you want to reset this grade?')) {
                                hiddenInput.value = '';
                                const resetInput = document.createElement('input');
                                resetInput.type = 'hidden';
                                resetInput.name = `reset_${subjectId}`;
                                resetInput.value = '1';
                                actionCell.appendChild(resetInput);
                                passedLabel.classList.remove('active');
                                failedLabel.classList.remove('active');
                                row.classList.remove('disabled-subject');
                            }
                        };
                        actionCell.appendChild(resetBtn);
                    }
                    row.appendChild(actionCell);
                    tbody.appendChild(row);
                });
                
                table.appendChild(tbody);
                yearSection.appendChild(table);
                subjectsByYearContainer.appendChild(yearSection);
            }
        }

        function createRadioButton(status, subjectId, isActive) {
            const label = document.createElement('label');
            label.className = `radio-btn radio-${status.toLowerCase()}${isActive ? ' active' : ''}`;
            label.textContent = status;
            
            label.addEventListener('click', function() {
                const hiddenInput = this.parentNode.querySelector(`input[name="subject_status_${subjectId}"]`);
                const statusButtons = this.parentNode.querySelectorAll('.radio-btn');
                
                statusButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                hiddenInput.value = status;
            });
            
            return label;
        }

        function checkPrerequisitesStatus(subjectId, studentId, passedSubjects, failedSubjects, subjectsById) {
            if (!prerequisites[subjectId] || prerequisites[subjectId].length === 0) {
                return false; // No prerequisites
            }
            
            for (const prereqId of prerequisites[subjectId]) {
                if (!passedSubjects.includes(parseInt(prereqId))) {
                    const prereqSubject = subjectsById[prereqId];
                    return `Required prerequisite not completed: ${prereqSubject ? prereqSubject.code : 'Subject ID ' + prereqId}`;
                }
            }
            
            return false; // All prerequisites satisfied
        }
        
        function checkRetakeOpportunity(subjectId, subject, currentYear, currentSemester, failedSubjects, subjectsById) {
            // Check if the subject was previously failed and is available for retake
            return failedSubjects.includes(parseInt(subjectId));
        }
    });
    </script>
</body>
</html>