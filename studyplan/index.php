<?php
include('../session.php');
include('../config/db.php');

function get_direct_prerequisites($conn, $subject_id) {
    $prereqs = [];
    $query = $conn->prepare("SELECT sp.prerequisite_id FROM subject_prerequisites sp WHERE sp.subject_id = ?");
    $query->bind_param("i", $subject_id);
    $query->execute();
    $result = $query->get_result();
    while ($row = $result->fetch_assoc()) $prereqs[] = $row['prerequisite_id'];
    $query->close();
    return $prereqs;
}

function get_course_subjects_with_prereqs($conn, $course_id) {
     $all_subjects = [];
     $subjects_query = $conn->prepare("SELECT id, code, name, units, year_level, semester FROM subjects WHERE course_id = ? ORDER BY year_level ASC, semester ASC, code ASC");
     $subjects_query->bind_param("i", $course_id);
     $subjects_query->execute();
     $subjects_result = $subjects_query->get_result();
     $subject_ids = [];
     while ($subject = $subjects_result->fetch_assoc()) {
         $all_subjects[$subject['id']] = $subject;
         $subject_ids[] = $subject['id'];
     }
     $subjects_query->close();

      if (!empty($subject_ids)) {
          $placeholders = implode(',', array_fill(0, count($subject_ids), '?'));
          $types = str_repeat('i', count($subject_ids));
          $prereq_query = $conn->prepare("SELECT sp.subject_id, p.id as prerequisite_id, p.code as prerequisite_code FROM subject_prerequisites sp JOIN subjects p ON sp.prerequisite_id = p.id WHERE sp.subject_id IN ($placeholders)");
          $prereq_query->bind_param($types, ...$subject_ids);
          $prereq_query->execute();
          $prereq_result = $prereq_query->get_result();

          foreach ($all_subjects as $id => &$subject) {
              $subject['prerequisites'] = []; 
              $subject['prerequisite_ids'] = [];
          }
          unset($subject);

          while ($prereq = $prereq_result->fetch_assoc()) {
              if (isset($all_subjects[$prereq['subject_id']])) {
                  $all_subjects[$prereq['subject_id']]['prerequisites'][$prereq['prerequisite_id']] = $prereq['prerequisite_code'];
                  $all_subjects[$prereq['subject_id']]['prerequisite_ids'][] = $prereq['prerequisite_id'];
              }
          }
          $prereq_query->close();
      }
     return $all_subjects;
}

function find_unavailable_subjects($failed_subject_ids, $all_subjects) {
    $unavailable_ids = [];
    $dependency_map = [];
    foreach ($all_subjects as $subject_id => $subject_details) {
        if (!empty($subject_details['prerequisite_ids'])) {
            foreach ($subject_details['prerequisite_ids'] as $prereq_id) {
                $dependency_map[$prereq_id][] = $subject_id;
            }
        }
    }

    $queue = $failed_subject_ids;
    $processed = [];
    while (!empty($queue)) {
        $current_failed_id = array_shift($queue);
        if (isset($processed[$current_failed_id])) continue;
        $processed[$current_failed_id] = true;

        if (isset($dependency_map[$current_failed_id])) {
            foreach ($dependency_map[$current_failed_id] as $dependent_id) {
                if (!in_array($dependent_id, $unavailable_ids)) {
                     $unavailable_ids[] = $dependent_id;
                     if (!isset($processed[$dependent_id])) $queue[] = $dependent_id;
                }
            }
        }
    }
    return $unavailable_ids;
}

function get_year_string($year_level) {
    switch ($year_level) {
        case 1: return '1st Year'; 
        case 2: return '2nd Year'; 
        case 3: return '3rd Year'; 
        case 4: return '4th Year';
        case 5: return '5th Year';
        default: return $year_level.'th Year';
    }
}

// Updated function to adjust subject year levels based on failures
function adjust_subject_years($all_subjects, $failed_subject_ids, $unavailable_subject_ids) {
    $adjusted_subjects = $all_subjects;
    $dependency_map = [];
    
    // Create dependency map (which subjects depend on which prerequisites)
    foreach ($all_subjects as $subject_id => $subject_details) {
        if (!empty($subject_details['prerequisite_ids'])) {
            foreach ($subject_details['prerequisite_ids'] as $prereq_id) {
                $dependency_map[$prereq_id][] = $subject_id;
            }
        }
    }
    
    // First, adjust failed subjects - just move them to the next year, same semester
    foreach ($failed_subject_ids as $failed_id) {
        if (isset($adjusted_subjects[$failed_id])) {
            // Move failed subject to next year, same semester (only +1 year)
            $adjusted_subjects[$failed_id]['adjusted_year_level'] = $adjusted_subjects[$failed_id]['year_level'] + 1;
            $adjusted_subjects[$failed_id]['original_year_level'] = $adjusted_subjects[$failed_id]['year_level'];
            $adjusted_subjects[$failed_id]['is_adjusted'] = true; // Mark as adjusted
        }
    }
    
    // Then adjust dependent subjects (post-requisites) - also just move them to next year
    foreach ($unavailable_subject_ids as $unavailable_id) {
        if (isset($adjusted_subjects[$unavailable_id])) {
            // Simply move to the next year (+1 year)
            $current_year = $adjusted_subjects[$unavailable_id]['year_level'];
            $adjusted_subjects[$unavailable_id]['original_year_level'] = $current_year;
            $adjusted_subjects[$unavailable_id]['adjusted_year_level'] = $current_year + 1;
            $adjusted_subjects[$unavailable_id]['is_adjusted'] = true; // Mark as adjusted
        }
    }
    
    return $adjusted_subjects;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UDM Study Plan Status</title> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* --- CSS rules remain unchanged as requested --- */
        :root { --primary: #4361ee; --primary-dark: #3a56d4; --secondary: #4cc9f0; --light: #f8f9fa; --dark: #212529; --gray: #6c757d; --success: #38b000; --warning: #ffb700; --danger: #dc3545; --info: #17a2b8; --muted: #6c757d; --shadow: 0 4px 6px rgba(0, 0, 0, 0.1); --radius: 10px; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f7fb; color: var(--dark); line-height: 1.6; }
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); color: white; padding: 20px 0; box-shadow: var(--shadow); position: fixed; height: 100vh; overflow-y: auto; transition: all 0.3s; z-index: 100; }
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
        .logout-btn { display: flex; align-items: center; background-color: rgba(255, 255, 255, 0.1); color: white; padding: 12px 16px; border-radius: var(--radius); text-decoration: none; transition: all 0.3s; width: calc(100% - 50px); margin-left: 25px; margin-right: 25px; }
        .logout-btn:hover { background-color: rgba(255, 255, 255, 0.2); }
        .logout-btn i { margin-right: 10px; }
        .main { flex: 1; margin-left: 280px; padding: 30px; transition: margin-left 0.3s; }
        .main-header { margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .main-title { font-size: 1.8rem; font-weight: 600; color: var(--dark); margin-bottom: 10px; }
        .sub-title { color: var(--gray); font-size: 1rem; }
        .content-card { background-color: white; border-radius: var(--radius); padding: 25px; box-shadow: var(--shadow); margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--dark); }
        .form-control { width: 100%; padding: 10px 15px; border: 1px solid #ced4da; border-radius: var(--radius); font-size: 1rem; transition: border-color 0.15s ease-in-out; }
        .form-control:focus { border-color: var(--primary); outline: 0; box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25); }
        select.form-control { cursor: pointer; appearance: none; background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236c757d' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E") no-repeat; background-position: calc(100% - 15px) center; background-color: white; padding-right: 35px; }
        .btn { display: inline-flex; align-items: center; padding: 10px 16px; border-radius: var(--radius); text-decoration: none; font-weight: 500; font-size: 0.9rem; transition: all 0.3s; cursor: pointer; border: none; }
        .btn i { margin-right: 8px; }
        .btn-primary { background-color: var(--primary); color: white; }
        .btn-primary:hover { background-color: var(--primary-dark); }
        .btn-danger { background-color: var(--danger); color: white; }
        .btn-danger:hover { background-color: #c82333; }
        .btn-success { background-color: var(--success); color: white; }
        .btn-success:hover { background-color: #2d9200; }
        .export-buttons { display: flex; gap: 10px; margin-bottom: 20px; }
        .results-card { background-color: white; border-radius: var(--radius); padding: 25px; box-shadow: var(--shadow); margin-top: 30px; }
        .results-title { font-size: 1.4rem; font-weight: 600; margin-bottom: 20px; color: var(--dark); padding-bottom: 10px; border-bottom: 1px solid #e9ecef; }
        .year-semester-section { margin-bottom: 30px; }
        .year-semester-header { background-color: var(--primary); color: white; padding: 12px 20px; border-radius: var(--radius) var(--radius) 0 0; font-weight: 600; font-size: 1.1rem; }
        .subject-table { width: 100%; border-collapse: collapse; }
        .subject-table th { background-color: #f8f9fa; padding: 12px 15px; text-align: left; font-weight: 600; color: var(--dark); border-bottom: 2px solid #e9ecef; border-top: 1px solid #dee2e6; border-left: 1px solid #dee2e6; border-right: 1px solid #dee2e6; }
         .subject-table th:first-child { border-top-left-radius: 0; }
        .subject-table th:last-child { border-top-right-radius: 0; }
        .subject-table td { padding: 12px 15px; border-bottom: 1px solid #f1f3f5; border-left: 1px solid #dee2e6; border-right: 1px solid #dee2e6; }
       .subject-table tr:last-child td { border-bottom: 1px solid #dee2e6; }
       .subject-table tr:last-child td:first-child { border-bottom-left-radius: var(--radius); }
       .subject-table tr:last-child td:last-child { border-bottom-right-radius: var(--radius); }
        .subject-code { font-weight: 600; color: var(--primary); }
        .status-passed { color: var(--success); font-weight: bold; }
        .status-failed { color: var(--danger); font-weight: bold; }
        .status-not-available { color: var(--muted); font-style: italic; }
        .status-adjusted { color: var(--warning); font-weight: bold; }
         .hamburger { position: fixed; top: 20px; left: 20px; z-index: 1001; background-color: var(--primary); width: 40px; height: 40px; border-radius: 50%; display: none; align-items: center; justify-content: center; cursor: pointer; box-shadow: var(--shadow); }
         .hamburger i { color: white; font-size: 1.2rem; }
        .section-divider { border-top: 2px dashed #e9ecef; margin: 30px 0; padding-top: 20px; }
        .plan-options { margin: 20px 0; display: flex; gap: 15px; }
        .plan-toggle { display: flex; align-items: center; }
        .plan-toggle input[type="radio"] { margin-right: 8px; }
        @media (max-width: 992px) { .sidebar { width: 240px; } .main { margin-left: 240px; } .logout-btn { width: calc(100% - 50px); } }
        @media (max-width: 768px) { .hamburger { display: flex; } .sidebar { transform: translateX(-100%); } .sidebar.show { transform: translateX(0); } .main { margin-left: 0; padding-top: 70px; } .export-buttons { flex-direction: column; align-items: flex-start; } .export-buttons .btn { width: 100%; margin-bottom: 10px; } .export-buttons .btn:last-child { margin-bottom: 0; } .subject-table-container { display: block; width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border: 1px solid #dee2e6; border-radius: 0 0 var(--radius) var(--radius); margin-top: -1px; } .subject-table { margin-bottom: 0; border: none; } .subject-table th, .subject-table td { white-space: nowrap; border-left: none; border-right: none; border-top: none; } .subject-table tr:last-child td { border-bottom: none; } .subject-table tr:last-child td:first-child, .subject-table tr:last-child td:last-child { border-radius: 0; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="hamburger"> <i class="fas fa-bars"></i> </div>
        <div class="sidebar">
             <div class="sidebar-header"> <h2><i class="fas fa-book-open"></i> UDM Study Plan</h2> </div>
             <div class="sidebar-user">
                 <div class="user-avatar"> <i class="fas fa-user"></i> </div>
                 <div class="user-info">
                     <h3><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Administrator'; ?></h3>
                     <p>Administrator</p>
                 </div>
             </div>
             <ul class="nav-menu">
                  <li class="nav-item"> <a href="../dashboard.php" class="nav-link"> <i class="fas fa-tachometer-alt"></i> Dashboard </a> </li>
                  <li class="nav-item"> <a href="../departments/index.php" class="nav-link"> <i class="fas fa-building"></i> Departments </a> </li>
                  <li class="nav-item"> <a href="../courses/index.php" class="nav-link"> <i class="fas fa-graduation-cap"></i> Program </a> </li>
                  <li class="nav-item"> <a href="../subjects/index.php" class="nav-link"> <i class="fas fa-book"></i> Subjects </a> </li>
                  <li class="nav-item"> <a href="../students/index.php" class="nav-link"> <i class="fas fa-users"></i> Students </a> </li>
                  <li class="nav-item"> <a href="../grades/index.php" class="nav-link"> <i class="fas fa-chart-line"></i> Grades </a> </li>
                  <li class="nav-item"> <a href="index.php" class="nav-link active"> <i class="fas fa-tasks"></i> Generate Plan </a> </li>
             </ul>
             <div class="logout"> <a href="../logout.php" class="logout-btn"> <i class="fas fa-sign-out-alt"></i> Logout </a> </div>
        </div>
        <div class="main">
            <div class="main-header">
                <div>
                    <h1 class="main-title">Study Plan Status</h1>
                    <p class="sub-title">View subject status based on grades and prerequisites</p>
                </div>
            </div>
            <div class="content-card">
                <form method="POST">
                    <div class="form-group">
                         <label class="form-label">Program:</label>
                         <select name="course_id" class="form-control" onchange="this.form.submit()" required>
                             <option value="">Select Program</option>
                             <?php
                             $courses_result = $conn->query("SELECT id, course_name FROM courses ORDER BY course_name ASC");
                             $selected_course_id = isset($_POST['course_id']) ? (int)$_POST['course_id'] : (isset($_GET['course_id']) ? (int)$_GET['course_id'] : null);
                             while ($course = $courses_result->fetch_assoc()) {
                                 $selected = ($selected_course_id == $course['id']) ? 'selected' : '';
                                 echo "<option value='{$course['id']}' $selected>" . htmlspecialchars($course['course_name']) . "</option>";
                             }
                             ?>
                         </select>
                     </div>
                     <div class="form-group">
                         <label class="form-label">Student:</label>
                         <select name="student_id" class="form-control" required>
                             <option value="">Select Student</option>
                             <?php
                             $selected_student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : (isset($_GET['student_id']) ? (int)$_GET['student_id'] : null);
                             if ($selected_course_id) {
                                 $stmt = $conn->prepare("SELECT id, full_name FROM students WHERE course_id = ? ORDER BY full_name ASC");
                                 $stmt->bind_param("i", $selected_course_id);
                                 $stmt->execute();
                                 $students_result = $stmt->get_result();
                                 while ($student = $students_result->fetch_assoc()) {
                                      $selected = ($selected_student_id == $student['id']) ? 'selected' : '';
                                     echo "<option value='{$student['id']}' $selected>" . htmlspecialchars($student['full_name']) . "</option>";
                                 }
                                 $stmt->close();
                              }
                             ?>
                         </select>
                         <?php if($selected_course_id && !$selected_student_id): ?>
                             <input type="hidden" name="course_id" value="<?php echo $selected_course_id; ?>">
                         <?php endif; ?>
                     </div>
                     
                     <div class="plan-options">
                         <div class="plan-toggle">
                             <input type="radio" id="plan_original" name="plan_type" value="original" <?php echo (!isset($_POST['plan_type']) || $_POST['plan_type'] == 'original') ? 'checked' : ''; ?>>
                             <label for="plan_original">Original Plan</label>
                         </div>
                         <div class="plan-toggle">
                             <input type="radio" id="plan_adjusted" name="plan_type" value="adjusted" <?php echo (isset($_POST['plan_type']) && $_POST['plan_type'] == 'adjusted') ? 'checked' : ''; ?>>
                             <label for="plan_adjusted">Adjusted Plan (Failed Subjects)</label>
                         </div>
                     </div>
                     
                    <button type="submit" name="generate" class="btn btn-primary"> <i class="fas fa-eye"></i> View Status </button>
                 </form>
             </div>

            <?php
            if (isset($_POST['generate'], $_POST['student_id'], $_POST['course_id'])) {
                $student_id = (int)$_POST['student_id'];
                $course_id = (int)$_POST['course_id'];
                $plan_type = isset($_POST['plan_type']) ? $_POST['plan_type'] : 'original';

                $student_name = 'N/A';
                $student_query = $conn->prepare("SELECT full_name FROM students WHERE id = ?"); 
                $student_query->bind_param("i", $student_id); 
                $student_query->execute();
                $student_result = $student_query->get_result();
                if ($student_data = $student_result->fetch_assoc()) $student_name = $student_data['full_name'];
                $student_query->close();

                $course_name = 'N/A';
                $course_query = $conn->prepare("SELECT course_name FROM courses WHERE id = ?"); 
                $course_query->bind_param("i", $course_id); 
                $course_query->execute();
                $course_result = $course_query->get_result();
                if ($course_data = $course_result->fetch_assoc()) $course_name = $course_data['course_name'];
                $course_query->close();

                $all_subjects = get_course_subjects_with_prereqs($conn, $course_id);

                $student_grades = []; 
                $failed_subject_ids = []; 
                $passed_subject_ids = [];
                $grades_query = $conn->prepare("SELECT subject_id, status FROM student_grades WHERE student_id = ?");
                $grades_query->bind_param("i", $student_id); 
                $grades_query->execute();
                $grades_result = $grades_query->get_result();
                while ($grade = $grades_result->fetch_assoc()) {
                    $student_grades[$grade['subject_id']] = $grade['status'];
                    if ($grade['status'] == 'Failed') $failed_subject_ids[] = $grade['subject_id'];
                    elseif ($grade['status'] == 'Passed') $passed_subject_ids[] = $grade['subject_id'];
                }
                $grades_query->close();

                $unavailable_subject_ids = find_unavailable_subjects($failed_subject_ids, $all_subjects);
                
                // Adjust subjects based on failures if selected plan type is 'adjusted'
                if ($plan_type == 'adjusted') {
                    $adjusted_subjects = adjust_subject_years($all_subjects, $failed_subject_ids, $unavailable_subject_ids);
                } else {
                    $adjusted_subjects = $all_subjects; // Use original plan
                }

                echo '<div class="results-card">';
                echo '<h2 class="results-title">Study Plan Status for ' . htmlspecialchars($student_name) . '</h2>';
                echo '<p>Course: ' . htmlspecialchars($course_name) . '</p>';
                echo '<p>Plan Type: ' . ($plan_type == 'adjusted' ? 'Adjusted Plan (with failed subjects moved)' : 'Original Plan') . '</p>';
                echo '<div class="export-buttons">';
                echo '<a href="export_pdf_status.php?student_id=' . $student_id . '&course_id=' . $course_id . '&plan_type=' . $plan_type . '" class="btn btn-danger" target="_blank"><i class="fas fa-file-pdf"></i> Export Status as PDF</a>';
                echo '</div>';

                $plan_by_year_sem = [];
                
                // Organize subjects by year and semester based on the selected plan type
                foreach ($adjusted_subjects as $subject_id => $subject) {
                    $year_level = $subject['year_level'];
                    
                    // If using adjusted plan and subject has been adjusted, use the adjusted year level
                    if ($plan_type == 'adjusted' && isset($subject['adjusted_year_level'])) {
                        $year_level = $subject['adjusted_year_level'];
                    }
                    
                    $plan_by_year_sem[$year_level][$subject['semester']][$subject_id] = $subject;
                }
                
                ksort($plan_by_year_sem);

                $max_display_years = $plan_type == 'adjusted' ? 5 : 4; // Allow 5th year for adjusted plan
                
                for ($year_level = 1; $year_level <= $max_display_years; $year_level++) {
                    if (isset($plan_by_year_sem[$year_level])) {
                        ksort($plan_by_year_sem[$year_level]);
                        
                        foreach ($plan_by_year_sem[$year_level] as $semester => $subjects) {
                            if ($semester == 'Summer' && empty($subjects)) continue;

                            echo '<div class="year-semester-section">';
                            $year_string = get_year_string($year_level);
                            echo '<div class="year-semester-header">' . htmlspecialchars($year_string) . ' / ' . htmlspecialchars($semester) . ' Semester</div>';
                            echo '<div class="subject-table-container">';
                            echo '<table class="subject-table"><thead><tr><th>Subject Code</th><th>Subject</th><th>Units</th><th>Pre-Requisites</th><th>Status</th></tr></thead><tbody>';

                            if (empty($subjects)) {
                                echo '<tr><td colspan="5" style="text-align:center; color: var(--gray);">No subjects listed for this semester in curriculum.</td></tr>';
                            } else {
                                foreach ($subjects as $subject_id => $subject) {
                                    $prereq_display = !empty($subject['prerequisites']) ? implode(", ", $subject['prerequisites']) : '';
                                    $status_text = ''; 
                                    $status_class = '';

                                    // Check if this is an adjusted plan and the subject has been moved
                                    $is_adjusted_subject = $plan_type == 'adjusted' && isset($subject['is_adjusted']) && $subject['is_adjusted'];

                                    if (isset($student_grades[$subject_id]) && !$is_adjusted_subject) {
                                        // Show original status only if not adjusted or in original plan
                                        if ($student_grades[$subject_id] == 'Passed') {
                                            $status_text = 'Passed';
                                            $status_class = 'status-passed';
                                        } elseif ($student_grades[$subject_id] == 'Failed') {
                                            $status_text = 'Failed';
                                            $status_class = 'status-failed';
                                        }
                                    } elseif (in_array($subject_id, $unavailable_subject_ids)) {
                                        $status_text = 'Not Available (Prerequisite Failed)';
                                        $status_class = 'status-not-available';
                                    } elseif ($is_adjusted_subject) {
                                        $original_year = $subject['original_year_level'];
                                        $status_text = 'Moved from Year ' . $original_year;
                                        $status_class = 'status-adjusted';
                                    } else {
                                        $status_text = 'Not Yet Taken';
                                        $status_class = '';
                                    }

                                    // Check if all prerequisites are passed
                                    $all_prereqs_passed = true;
                                    if (!empty($subject['prerequisite_ids'])) {
                                        foreach ($subject['prerequisite_ids'] as $prereq_id) {
                                            if (!isset($student_grades[$prereq_id]) || $student_grades[$prereq_id] != 'Passed') {
                                                $all_prereqs_passed = false;
                                                break;
                                            }
                                        }
                                    }

                                    // Override status for adjusted subjects that appear in their new position
                                    if ($is_adjusted_subject) {
                                        if (isset($student_grades[$subject_id])) {
                                            // If it's a moved subject that's already taken
                                            if ($student_grades[$subject_id] == 'Passed') {
                                                $status_text = 'Passed (Originally Year ' . $subject['original_year_level'] . ')';
                                                $status_class = 'status-passed';
                                            } elseif ($student_grades[$subject_id] == 'Failed') {
                                                $status_text = 'Failed (Moved from Year ' . $subject['original_year_level'] . ')';
                                                $status_class = 'status-failed';
                                            }
                                        } else {
                                            // If it's just moved but not yet taken
                                            $status_text = 'Moved from Year ' . $subject['original_year_level'];
                                            $status_class = 'status-adjusted';
                                        }
                                    }

                                    echo '<tr>';
                                    echo '<td class="subject-code">' . htmlspecialchars($subject['code']) . '</td>';
                                    echo '<td>' . htmlspecialchars($subject['name']) . '</td>';
                                    echo '<td>' . htmlspecialchars($subject['units']) . '</td>';
                                    echo '<td>' . htmlspecialchars($prereq_display) . '</td>';
                                    echo '<td class="' . $status_class . '">' . htmlspecialchars($status_text) . '</td>';
                                    echo '</tr>';
                                }
                            }

                            echo '</tbody></table>';
                            echo '</div>'; // End subject-table-container
                            echo '</div>'; // End year-semester-section
                        }
                    } else {
                        // No subjects for this year level
                    }
                }

                echo '</div>'; // End results-card
            }
            ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const hamburger = document.querySelector('.hamburger');
            const sidebar = document.querySelector('.sidebar');
            
            hamburger.addEventListener('click', function() {
                sidebar.classList.toggle('show');
            });
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                const isClickInsideSidebar = sidebar.contains(event.target);
                const isClickOnHamburger = hamburger.contains(event.target);
                
                if (!isClickInsideSidebar && !isClickOnHamburger && sidebar.classList.contains('show')) {
                    sidebar.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>