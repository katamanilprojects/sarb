@echo off
setlocal DisableDelayedExpansion
if not defined AI_PIPELINE_CONFIG (
    echo ERROR: Set AI_PIPELINE_CONFIG to the private configuration file.
    exit /b 2
)
if not defined AI_PIPELINE_PYTHON set "AI_PIPELINE_PYTHON=python"
"%AI_PIPELINE_PYTHON%" "%~dp0worker\task_runner.py" run
set "PIPELINE_EXIT=%ERRORLEVEL%"
exit /b %PIPELINE_EXIT%
