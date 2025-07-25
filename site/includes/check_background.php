<?php
require_once __DIR__ . '/db.php';

$submissionId = $argv[1];
$taskId = $argv[2];
$filePath = $argv[3];

try {
    $result = checkSolution($submissionId, $taskId, $filePath);
    logCheckProcess($submissionId, "Check completed with status: " . $result['status']);
} catch (Exception $e) {
    logCheckProcess($submissionId, "Error during check: " . $e->getMessage());
}