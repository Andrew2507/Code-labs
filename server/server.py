from fastapi import FastAPI, UploadFile, File, Form, HTTPException, Request
from fastapi.responses import JSONResponse
from pydantic import BaseModel, field_validator, ConfigDict
from typing import List, Optional, Dict, Any
import json
import os
import subprocess
from tempfile import TemporaryDirectory
import logging
from datetime import datetime
from openai import OpenAI

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[logging.StreamHandler()]
)
logger = logging.getLogger(__name__)

app = FastAPI()

ai_client = OpenAI(base_url="", api_key='')

class TestCase(BaseModel):
    input: str
    output: str
    timeout: float = 2.0

class TaskData(BaseModel):
    model_config = ConfigDict(extra='forbid')
    
    id: int
    tests: List[TestCase]
    required_regex: Optional[str] = None

    @field_validator('id', mode='before')
    @classmethod
    def convert_id_to_int(cls, v):
        if isinstance(v, str):
            try:
                return int(v)
            except ValueError:
                raise ValueError("ID must be a number")
        return v

@app.post("/check_solution")
async def check_solution(
    task: str = Form(...),
    file: UploadFile = File(...)
):
    start_time = datetime.now()
    try:
        task_dict = json.loads(task)
        task_obj = TaskData(**task_dict)
        
        code = (await file.read()).decode('utf-8')
        
        results = []
        with TemporaryDirectory() as temp_dir:
            code_path = os.path.join(temp_dir, "solution.py")
            with open(code_path, 'w', encoding='utf-8') as f:
                f.write(code)
            
            for i, test in enumerate(task_obj.tests, 1):
                result = execute_test(
                    code_path=code_path,
                    input_data=test.input,
                    expected_output=test.output,
                    timeout=test.timeout
                )
                
                if result['status'] != 'success':
                    error_msg = result.get('message', 'Unknown error')
                    result['ai_feedback'] = await get_ai_feedback(code, error_msg, test)
                
                results.append(result)

        passed = sum(1 for r in results if r['status'] == 'success')
        duration = (datetime.now() - start_time).total_seconds()
        
        return {
            "status": "success" if passed == len(task_obj.tests) else "failed",
            "passed": passed,
            "total": len(task_obj.tests),
            "execution_time": duration,
            "details": results
        }

    except Exception as e:
        logger.error(f"Error: {str(e)}", exc_info=True)
        raise HTTPException(500, detail=str(e))

async def get_ai_feedback(code: str, error: str, test_case: TestCase) -> str:
    try:
        prompt = (
            f"Анализ ошибки:\n"
            f"* Вход теста: {test_case.input}\n"
            f"* Ожидаемый вывод: {test_case.output}\n"
            f"* Полученный вывод: {error}\n\n"
            "Назови конкретную техническую причину ошибки (3-5 слов) на русском.\n"
            "Только факты, без общих фраз. Примеры хороших ответов:\n"
            "- 'Не проверяется число 2'\n"
            "- 'Неправильное условие цикла'\n"
            "- 'Ложное срабатывание для 9'\n"
            "- 'Пропущен break в цикле'"
        )
        
        response = ai_client.chat.completions.create(
            model="zukigm-1",
            messages=[
                {
                    "role": "system", 
                    "content": "Ты должен назвать конкретную техническую причину ошибки строго в 3-5 словах. "
                               "Только факты, без вводных слов. Язык: русский."
                },
                {"role": "user", "content": prompt}
            ],
            max_tokens=20,
            temperature=0.1
        )
        
        feedback = response.choices[0].message.content
        
        feedback = feedback.replace("Ошибка:", "").replace("Причина:", "").strip()
        feedback = ' '.join(word for word in feedback.split() if not word.lower() in ['в', 'коде', 'задача'])
        
        return ' '.join(feedback.split()[:5]).strip('".,').capitalize()

    except Exception as e:
        logger.error(f"AI feedback error: {str(e)}")
        return "Ошибка в алгоритме"

def execute_test(code_path: str, input_data: str, expected_output: str, timeout: float) -> Dict[str, Any]:
    try:
        process = subprocess.run(
            ['python', code_path],
            input=input_data.encode(),
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            timeout=timeout
        )

        if process.returncode != 0:
            return {
                "status": "runtime_error",
                "message": process.stderr.decode().strip(),
                "expected": expected_output.strip(),
                "actual": "N/A (Runtime Error)"
            }

        actual_output = process.stdout.decode().strip()
        return {
            "status": "success" if actual_output == expected_output.strip() else "wrong_answer",
            "expected": expected_output.strip(),
            "actual": actual_output,
            "message": "Correct" if actual_output == expected_output.strip() else "Wrong answer"
        }

    except subprocess.TimeoutExpired:
        return {
            "status": "timeout",
            "expected": expected_output.strip(),
            "actual": "N/A (Timeout)",
            "message": "Test timed out"
        }
    except Exception as e:
        return {
            "status": "execution_error",
            "message": str(e),
            "expected": expected_output.strip(),
            "actual": "N/A (Execution Error)"
        }

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=7777)