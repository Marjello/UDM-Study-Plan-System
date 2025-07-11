<?php
include('../session.php');
include('../config/db.php');
require('../fpdf/fpdf.php'); // Make sure FPDF is installed

// Get parameters
if (!isset($_GET['student_id']) || !isset($_GET['course_id'])) {
    die("Missing required parameters");
}

$student_id = (int)$_GET['student_id'];
$course_id = (int)$_GET['course_id'];

// Function to get DIRECT prerequisites for a subject
function get_direct_prerequisites($conn, $subject_id) {
    $prereqs = []; // Store prerequisite IDs
    $query = $conn->prepare("
        SELECT sp.prerequisite_id
        FROM subject_prerequisites sp
        WHERE sp.subject_id = ?
    ");
    $query->bind_param("i", $subject_id);
    $query->execute();
    $result = $query->get_result();
    while ($row = $result->fetch_assoc()) {
        $prereqs[] = $row['prerequisite_id'];
    }
    $query->close();
    return $prereqs;
}

// Function to get ALL subjects for the course with their direct prerequisites
function get_course_subjects_with_prereqs($conn, $course_id) {
     $all_subjects = [];
     $subjects_query = $conn->prepare("
         SELECT id, code, name, units, year_level, semester
         FROM subjects
         WHERE course_id = ?
         ORDER BY year_level ASC, semester ASC, code ASC
     ");
     $subjects_query->bind_param("i", $course_id);
     $subjects_query->execute();
     $subjects_result = $subjects_query->get_result();
     $subject_ids = [];
     while ($subject = $subjects_result->fetch_assoc()) {
         $all_subjects[$subject['id']] = $subject;
         $subject_ids[] = $subject['id']; // Collect IDs for prereq fetching
     }
     $subjects_query->close();

      // Fetch prerequisites efficiently if subjects exist
      if (!empty($subject_ids)) {
          $placeholders = implode(',', array_fill(0, count($subject_ids), '?'));
          $types = str_repeat('i', count($subject_ids));
          $prereq_query = $conn->prepare("
                SELECT sp.subject_id, p.id as prerequisite_id, p.code as prerequisite_code
                FROM subject_prerequisites sp
                JOIN subjects p ON sp.prerequisite_id = p.id
                WHERE sp.subject_id IN ($placeholders)
            ");
          $prereq_query->bind_param($types, ...$subject_ids);
          $prereq_query->execute();
          $prereq_result = $prereq_query->get_result();

          // Initialize prerequisite arrays
          foreach ($all_subjects as $id => &$subject) {
              $subject['prerequisites'] = []; // id => code map
               $subject['prerequisite_ids'] = []; // Just IDs
          }
          unset($subject); // Unset reference

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

// Function to find all subjects affected by failed prerequisites (direct & indirect)
function find_unavailable_subjects($failed_subject_ids, $all_subjects) {
    $unavailable_ids = [];
    $dependency_map = []; // Map: prerequisite_id => [dependent_subject_id1, dependent_subject_id2, ...]

    // Build the dependency map
    foreach ($all_subjects as $subject_id => $subject_details) {
        if (!empty($subject_details['prerequisite_ids'])) {
            foreach ($subject_details['prerequisite_ids'] as $prereq_id) {
                if (!isset($dependency_map[$prereq_id])) {
                    $dependency_map[$prereq_id] = [];
                }
                $dependency_map[$prereq_id][] = $subject_id;
            }
        }
    }

    // Propagate the "unavailable" status
    $queue = $failed_subject_ids;
    $processed = []; // Keep track of processed subjects to avoid infinite loops in case of cycles

    while (!empty($queue)) {
        $current_failed_id = array_shift($queue);

        if (isset($processed[$current_failed_id])) {
            continue; // Already processed this node
        }
        $processed[$current_failed_id] = true;

        if (isset($dependency_map[$current_failed_id])) {
            foreach ($dependency_map[$current_failed_id] as $dependent_id) {
                 // Only mark if it's not already marked as unavailable
                 if (!in_array($dependent_id, $unavailable_ids)) {
                     $unavailable_ids[] = $dependent_id;
                    // Add the dependent to the queue to check *its* dependents
                     if (!isset($processed[$dependent_id])) { // Avoid adding processed nodes back
                        $queue[] = $dependent_id;
                    }
                }
            }
        }
    }

    return $unavailable_ids;
}

// Function to map numeric year to string
function get_year_string($year_level) {
    switch ($year_level) {
        case 1: return '1st Year';
        case 2: return '2nd Year';
        case 3: return '3rd Year';
        case 4: return '4th Year';
        default: return $year_level.'th Year';
    }
}

// Get student and course information
$student_name = '';
$student_number = '';

// FIX: The SQL query was using $_GET['student_id'] but the param variable was already stored in $student_id
$student_query = $conn->prepare("SELECT full_name, student_id FROM students WHERE id = ?");
$student_query->bind_param("i", $student_id);
$student_query->execute();
$student_result = $student_query->get_result();
if ($student_data = $student_result->fetch_assoc()) {
    $student_name = $student_data['full_name'];
    $student_number = $student_data['student_id']; // FIX: Use different variable name to avoid confusion
}
$student_query->close();

$course_name = '';
$course_query = $conn->prepare("SELECT course_name FROM courses WHERE id = ?");
$course_query->bind_param("i", $course_id);
$course_query->execute();
$course_result = $course_query->get_result();
if ($course_data = $course_result->fetch_assoc()) {
    $course_name = $course_data['course_name'];
}
$course_query->close();

// Fetch ALL subjects for the course with prerequisites
$all_subjects = get_course_subjects_with_prereqs($conn, $course_id);

// Fetch student grades
$student_grades = []; // [subject_id => 'Passed'/'Failed']
$failed_subject_ids = [];
$passed_subject_ids = [];

$grades_query = $conn->prepare("
    SELECT subject_id, status
    FROM student_grades
    WHERE student_id = ?
");
$grades_query->bind_param("i", $student_id);
$grades_query->execute();
$grades_result = $grades_query->get_result();
while ($grade = $grades_result->fetch_assoc()) {
    $student_grades[$grade['subject_id']] = $grade['status'];
    if ($grade['status'] == 'Failed') {
        $failed_subject_ids[] = $grade['subject_id'];
    } elseif ($grade['status'] == 'Passed') {
         $passed_subject_ids[] = $grade['subject_id'];
    }
}
$grades_query->close();

// Find all subjects made unavailable due to failed prerequisites
$unavailable_subject_ids = find_unavailable_subjects($failed_subject_ids, $all_subjects);

// Group subjects by year and semester for PDF display
$plan_by_year_sem = [];
foreach ($all_subjects as $subject) {
    $plan_by_year_sem[$subject['year_level']][$subject['semester']][] = $subject;
}
ksort($plan_by_year_sem); // Sort by year

// Create PDF
class StudyPlanPDF extends FPDF {
    // Header function
    function Header() {
        // Empty for now - we'll create custom header in the main code
    }
    
    // Page footer
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
    
    // FIX: Add NbLines method to calculate number of lines a MultiCell will require
    function NbLines($w, $txt) {
        // Calculate the number of lines a MultiCell of width w will take
        $cw = &$this->CurrentFont['cw'];
        if($w==0)
            $w = $this->w-$this->rMargin-$this->x;
        $wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
        $s = str_replace("\r",'',$txt);
        $nb = strlen($s);
        if($nb>0 && $s[$nb-1]=="\n")
            $nb--;
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while($i<$nb) {
            $c = $s[$i];
            if($c=="\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if($c==' ')
                $sep = $i;
            $l += isset($cw[ord($c)]) ? $cw[ord($c)] : 0;
            if($l>$wmax) {
                if($sep==-1) {
                    if($i==$j)
                        $i++;
                }
                else
                    $i = $sep+1;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            }
            else
                $i++;
        }
        return $nl;
    }
    
    // New function to check if there's enough space for a table or row
    function CheckPageBreak($h) {
        // If the height of the element would cause it to go beyond the page boundary, add a new page
        if($this->GetY() + $h > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation);
            return true;
        }
        return false;
    }
    
    // Function to draw a row of the subject table with proper height calculation
    function DrawTableRow($data, $height = 6) {
        // Calculate the height needed for this row based on the content of each cell
        $max_lines = 1;
        $widths = [30, 75, 15, 40, 30]; // Column widths
        
        foreach($data as $i => $txt) {
            if(isset($txt['content'])) {
                $lines = $this->NbLines($widths[$i], $txt['content']);
                $max_lines = max($max_lines, $lines);
            }
        }
        
        $row_height = $height * $max_lines;
        
        // Check if we need a page break before this row
        $new_page = $this->CheckPageBreak($row_height);
        
        // Draw the cells
        $start_x = $this->GetX();
        $start_y = $this->GetY();
        $current_x = $start_x;
        
        foreach($data as $i => $cell) {
            $this->SetXY($current_x, $start_y);
            
            // Set the font
            $style = isset($cell['style']) ? $cell['style'] : '';
            $this->SetFont('Arial', $style, 9);
            
            $align = isset($cell['align']) ? $cell['align'] : 'L';
            
            // Draw the cell
            $this->Cell($widths[$i], $row_height, $cell['content'], 1, 0, $align);
            $current_x += $widths[$i];
        }
        
        // Move to next row
        $this->SetY($start_y + $row_height);
        
        return $new_page;
    }
}

// Create PDF instance
$pdf = new StudyPlanPDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 15);

// Add title
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'STUDY PLAN', 0, 1, 'C');
$pdf->Ln(2);

// FIX: Improve layout for student details - adjust cell widths to prevent overflow
// Left column: labels, right column: values
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(40, 7, 'Name:', 0);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 7, $student_name, 0, 1); // Use full width and force a new line

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(40, 7, 'Student Number:', 0);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 7, $student_number, 0, 1); // Use full width and force a new line

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(40, 7, 'Course:', 0);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 7, $course_name, 0, 1); // Use full width and force a new line

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(40, 7, 'Section:', 0);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 7, '', 0, 1); // Leave blank as requested, use full width and force a new line

$pdf->Ln(2); // Add more space before the subject tables

// Loop through years and semesters
$max_display_years = 4;
for ($year_level = 1; $year_level <= $max_display_years; $year_level++) {
    if (isset($plan_by_year_sem[$year_level])) {
        ksort($plan_by_year_sem[$year_level]); // Sort semesters
        
        foreach ($plan_by_year_sem[$year_level] as $semester => $subjects) {
            // Skip empty summer semesters
            if ($semester == 'Summer' && empty($subjects)) {
                continue;
            }
            
            // Check if we need a page break - allow at least 40mm for the header and first row
            // This prevents orphaned headers
            if ($pdf->GetY() > 230) { // Reduced from 240 for more buffer space
                $pdf->AddPage();
            }
            
            // Year and Semester header
            $year_string = get_year_string($year_level);
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 10, $year_string . ' / ' . $semester . ' Semester', 0, 1, 'C');
            $pdf->Ln(2);
            
            // Table Header
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetFillColor(200, 220, 255);
            $pdf->Cell(30, 8, 'Subject Code', 1, 0, 'C', true);
            $pdf->Cell(75, 8, 'Name', 1, 0, 'C', true);
            $pdf->Cell(15, 8, 'Unit', 1, 0, 'C', true);
            $pdf->Cell(40, 8, 'Pre-requisites', 1, 0, 'C', true);
            $pdf->Cell(30, 8, 'Status', 1, 1, 'C', true);
            
            // Table Content
            $pdf->SetFont('Arial', '', 9);
            if (empty($subjects)) {
                $pdf->Cell(190, 8, 'No subjects listed for this semester in curriculum.', 1, 1, 'C');
                // Add a zero total for empty semesters
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->Cell(105, 8, 'Total No. of Units:', 1, 0, 'R');
                $pdf->Cell(15, 8, '0', 1, 0, 'C');
                $pdf->Cell(70, 8, '', 1, 1);
            } else {
                $row_count = 0;
                $semester_total_units = 0; // Initialize total units for this semester
                
                foreach ($subjects as $subject) {
                    $subject_id = $subject['id'];
                    $prereq_display = !empty($subject['prerequisites']) ? implode(", ", $subject['prerequisites']) : '';
                    
                    // Add units to semester total
                    $semester_total_units += $subject['units'];
                    
                    // Determine status
                    if (isset($student_grades[$subject_id])) {
                        $status_text = $student_grades[$subject_id]; // 'Passed' or 'Failed'
                        $font_style = ($status_text == 'Passed') ? 'B' : '';
                    } elseif (in_array($subject_id, $unavailable_subject_ids)) {
                        $status_text = 'Not Available';
                        $font_style = 'I';
                    } else {
                        $status_text = ''; // Blank if not taken and available
                        $font_style = '';
                    }
                    
                    // Create row data
                    $row_data = [
                        ['content' => $subject['code'], 'align' => 'L'],
                        ['content' => $subject['name'], 'align' => 'L'],
                        ['content' => $subject['units'], 'align' => 'C'],
                        ['content' => $prereq_display, 'align' => 'L'],
                        ['content' => $status_text, 'align' => 'C', 'style' => $font_style]
                    ];
                    
                    // Draw the row - the function will handle page breaks if needed
                    $new_page = $pdf->DrawTableRow($row_data);
                    
                    // If we had a page break, redraw the table header
                    if ($new_page) {
                        $year_string = get_year_string($year_level);
                        $pdf->SetFont('Arial', 'B', 12);
                        $pdf->Ln(2);
                        
                        $pdf->SetFont('Arial', 'B', 10);
                        $pdf->SetFillColor(200, 220, 255);
                        $pdf->Cell(30, 8, 'Subject Code', 1, 0, 'C', true);
                        $pdf->Cell(75, 8, 'Name', 1, 0, 'C', true);
                        $pdf->Cell(15, 8, 'Unit', 1, 0, 'C', true);
                        $pdf->Cell(40, 8, 'Pre-requisites', 1, 0, 'C', true);
                        $pdf->Cell(30, 8, 'Status', 1, 1, 'C', true);
                    }
                    
                    $row_count++;
                }
                
                // Add Total Units row after all subjects for this semester
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->SetFillColor(240, 240, 240); // Light gray background
                $pdf->Cell(105, 8, 'Total No. of Units:', 1, 0, 'R', true);
                $pdf->Cell(15, 8, $semester_total_units, 1, 0, 'C', true);
                $pdf->Cell(70, 8, '', 1, 1, 'C', true); // Empty cells for prereqs and status
            }
            
            $pdf->Ln(1);
        }
    }
}


// Output PDF
$pdf->Output('Study_Plan_' . str_replace(' ', '_', $student_name) . '.pdf', 'I');