# CodeLabs - Платформа для обучения программированию

## 📁 Ресурсы
<div align="center">
  <table>
    <tr>
      <td align="center">
        <img src="assets/qr1.png" width="150" alt="Маркетинг"><br>
        Маркетинг
      </td>
      <td align="center">
        <img src="assets/qr2.png" width="150" alt="Figma"><br>
        Макеты
      </td>
      <td align="center">
        <img src="assets/qr3.png" width="150" alt="Демо"><br>
        Демо
      </td>
    </tr>
  </table>
</div>

## 📌 О проекте

CodeLabs - интерактивная платформа для обучения программированию с автоматической проверкой решений и AI-фидбеком.

📂 Структура проекта

```
CodeLabs/
├── site/
│   ├── index.php
│   ├── dashboard.php
│   ├── task.php
│   ├── api.php
│   ├── assets/
│   │   ├── css/
│   │   │   └── style.css
│   │   └── js/
│   │       └── script.js
│   └── includes/
│       ├── db.php
│       ├── check_background.php
│       └── auth.php
└── server/
    └── server.py
```

🌐 API Endpoints
Проверка решения
http
POST /check_solution
Content-Type: multipart/form-data
Параметры:

task: JSON с описанием задачи

file: Файл с решением (Python)

Пример запроса:

```bash
curl -X POST http://localhost:7777/check_solution \
  -F "task={\"id\":1,\"tests\":[{\"input\":\"5\",\"output\":\"120\"}]}" \
  -F "file=@solution.py"
```
Успешный ответ (200 OK):

```json
{
  "status": "success",
  "passed": 1,
  "total": 1,
  "execution_time": 0.45,
  "details": [{
    "status": "success",
    "expected": "120",
    "actual": "120",
    "message": "Correct"
  }]
}
```
Ошибка в решении (200 OK):

```
json
{
  "status": "failed",
  "passed": 0,
  "total": 1,
  "execution_time": 0.38,
  "details": [{
    "status": "wrong_answer",
    "expected": "120",
    "actual": "121",
    "message": "Wrong answer",
    "ai_feedback": "Неправильное вычисление факториала"
  }]
}
```

<br>

<div align="center"> <img src="assets/logo.png" width="100" alt="CodeLabs Logo"> <p>✨ Сделано с любовью для будущих разработчиков ✨</p> </div>
