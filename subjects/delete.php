<?php
include('../config/db.php');

$id = $_GET['id'];

// Delete pre-reqs first to maintain integrity
$conn->query("DELETE FROM subject_prerequisites WHERE subject_id = $id");

// Then delete subject
$conn->query("DELETE FROM subjects WHERE id = $id");

header("Location: index.php");
