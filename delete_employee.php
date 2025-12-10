<?php
include 'db.php';

if (!isset($_GET['id'])) {
    header("Location: employees.php");
    exit;
}

$id = intval($_GET['id']);

$stmt = $con->prepare("DELETE FROM employees WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

header("Location: employees.php?deleted=1");
exit;
