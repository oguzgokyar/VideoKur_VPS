@echo off
REM Test resume API call
echo Testing Resume API...
echo.

set JOB_ID=job_69d358ae2cc3d5.34321588

curl -X PATCH http://localhost:8080/api/jobs.php ^
  -H "Content-Type: application/json" ^
  -d "{\"action\":\"resume\",\"jobId\":\"%JOB_ID%\"}"

echo.
echo.
pause
