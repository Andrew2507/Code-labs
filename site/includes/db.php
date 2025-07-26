<?php

$db = new PDO('mysql:host=127.0.0.1;dbname=labs;charset=utf8', '', '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function getTasks() {
    global $db;
    $stmt = $db->query("SELECT * FROM tasks");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTaskById($id) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getSubmissions($userId, $taskId) {
    global $db;
    $stmt = $db->prepare("SELECT *, DATE_FORMAT(submitted_at, '%d.%m.%Y %H:%i') as submitted_at 
                           FROM submissions 
                           WHERE user_id = ? AND task_id = ? 
                           ORDER BY submitted_at DESC");
    $stmt->execute([$userId, $taskId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function submitSolution($userId, $taskId, $fileName) {
    global $db;
    $stmt = $db->prepare("INSERT INTO submissions (user_id, task_id, file_path, status, submitted_at) VALUES (?, ?, ?, 'В обработке', NOW())");
    $stmt->execute([$userId, $taskId, $fileName]);
    return $db->lastInsertId();
}

function isTaskSolved($userId, $taskId) {
    global $db;

    $stmt = $db->prepare("SELECT status FROM user_tasks WHERE user_id = ? AND task_id = ?");
    $stmt->execute([$userId, $taskId]);
    $result = $stmt->fetch();

    return $result && $result['status'] === 'Решена';
}

function updateSubmissionStatus($submissionId, $status, $message = null) {
    global $db;

    try {
        $query = "UPDATE submissions SET status = ?, message = ?, checked_at = NOW() WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$status, $message, $submissionId]);

        return true;
    } catch (PDOException $e) {
        logError("Database error in updateSubmissionStatus: " . $e->getMessage());
        return false;
    }
}

function checkSolution($submissionId, $taskId, $filePath) {
    global $db;

    try {
        $stmt = $db->prepare("SELECT id, title, tests, required_regex, reward FROM tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            throw new Exception("Задача не найдена");
        }

        $taskData = [
            'id' => (int)$task['id'],
            'tests' => json_decode($task['tests'], true),
            'required_regex' => $task['required_regex'] ?: null
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'http://localhost:7777/check_solution',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'task' => json_encode($taskData),
                'file' => new CURLFile($filePath)
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            throw new Exception("CURL error: " . curl_error($ch));
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response");
        }

        $status = 'Ошибка';
        $message = '';
        $isSolved = false;
        $aiFeedback = null;

        if ($result['status'] === 'success' && $result['passed'] == $result['total']) {
            $status = 'Проверено';
            $message = 'Задача решена успешно!';
            $isSolved = true;
        } else {
            $status = 'Проверено';
            $failedTests = [];
            $aiFeedback = [];

            foreach ($result['details'] as $index => $test) {
                if ($test['status'] !== 'success') {
                    $failedTest = [
                        'test_number' => $index + 1,
                        'status' => $test['status'],
                        'expected' => $test['expected'] ?? 'N/A',
                        'actual' => $test['actual'] ?? 'N/A'
                    ];

                    if (isset($test['ai_feedback'])) {
                        $failedTest['ai_feedback'] = $test['ai_feedback'];
                        $aiFeedback[] = $test['ai_feedback'];
                    }

                    $failedTests[] = $failedTest;
                }
            }

            $message = 'Не пройдены тесты: ' . json_encode($failedTests, JSON_UNESCAPED_UNICODE);

            if (!empty($aiFeedback)) {
                $feedbackText = implode("\n", $aiFeedback);
                $stmt = $db->prepare("UPDATE submissions SET ai_feedback = ? WHERE id = ?");
                $stmt->execute([$feedbackText, $submissionId]);
            }
        }

        updateSubmissionStatus($submissionId, $status, $message);

        if ($isSolved) {
            $stmt = $db->prepare("SELECT user_id FROM submissions WHERE id = ?");
            $stmt->execute([$submissionId]);
            $submission = $stmt->fetch(PDO::FETCH_ASSOC);
            $userId = $submission['user_id'];

            $reward = $task['reward'] ?? 0;
            $stmt = $db->prepare("UPDATE users SET rating = rating + ? WHERE id = ?");
            $stmt->execute([$reward, $userId]);

            $stmt = $db->prepare("INSERT INTO user_tasks (user_id, task_id, status) VALUES (?, ?, 'Решена')
                                  ON DUPLICATE KEY UPDATE status = 'Решена'");
            $stmt->execute([$userId, $taskId]);
        }

        return $result;
    } catch (Exception $e) {
        logError("Error in checkSolution: " . $e->getMessage());
        updateSubmissionStatus($submissionId, 'Ошибка', 'Ошибка при проверке: ' . $e->getMessage());
        throw $e;
    }
}

function getCheckStatus($submissionId) {
    global $db;
    $stmt = $db->prepare("SELECT status FROM submissions WHERE id = ?");
    $stmt->execute([$submissionId]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);

    return $submission ? $submission['status'] : null;
}

function getCurrentUserTaskStatus($userId, $taskId) {
    global $db;
    $stmt = $db->prepare("SELECT status FROM user_tasks WHERE user_id = ? AND task_id = ?");
    $stmt->execute([$userId, $taskId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return $result ? $result['status'] : 'Не решена';
}

function getUsersRating() {
    global $db;
    $stmt = $db->query("
        SELECT u.id, u.username, COUNT(DISTINCT ut.task_id) as solved_tasks, u.rating 
        FROM users u
        LEFT JOIN user_tasks ut ON u.id = ut.user_id AND ut.status = 'Решена'
        GROUP BY u.id
        ORDER BY u.rating DESC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getDifficultyBadgeClass($difficulty) {
    switch ($difficulty) {
        case 'Легкая': return 'success';
        case 'Средняя': return 'warning';
        case 'Сложная': return 'danger';
        default: return 'secondary';
    }
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'Решена': return 'success';
        case 'В процессе': return 'primary';
        case 'Не решена': return 'secondary';
        default: return 'dark';
    }
}

function getSubmissionStatusBadgeClass($status) {
    switch ($status) {
        case 'Проверено':
            return 'success';
        case 'Ошибка':
            return 'danger';
        case 'В обработке':
            return 'primary';
        default:
            return 'secondary';
    }
}

function logError($message) {
    $logFile = __DIR__ . '/../logs/error.log';
    $logMessage = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

function logCheckProcess($submissionId, $message) {
    $logFile = __DIR__ . '/../logs/check_process.log';
    $logMessage = '[' . date('Y-m-d H:i:s') . '] Submission ID: ' . $submissionId . ' - ' . $message . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}