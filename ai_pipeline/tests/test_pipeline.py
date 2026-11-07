"""Offline runner tests: no database, agent, network or files outside this folder."""
import copy
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch

WORKER = Path(__file__).resolve().parents[1] / "worker"
sys.path.insert(0, str(WORKER))
import task_runner as runner
import runtime
import config
import test_environment


class PipelineTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(dir=Path(__file__).parent)
        self.folder = Path(self.temp.name)
        self.state = json.loads((WORKER.parent / "ai_tasks.json").read_text())
        self.cfg = {"max_attempts": 3, "php_executable": sys.executable,
                    "agent_executable": sys.executable, "test_database": "cea_standardisation_test_fake",
                    "task_timeout_seconds": 10, "verification_timeout_seconds": 10}

    def tearDown(self):
        self.temp.cleanup()

    def test_roadmap_is_valid_and_scoped(self):
        runner.validate_roadmap(self.state)
        bad = copy.deepcopy(self.state)
        bad['tasks'][0]['required_outputs'] = ['outside.txt']
        with self.assertRaises(ValueError):
            runner.validate_roadmap(bad)

    def test_forward_dependencies_and_duplicate_ids_rejected(self):
        for mutate in (lambda s: s['tasks'][0].update(depends_on=['TASK-02']),
                       lambda s: s['tasks'][1].update(id='TASK-01')):
            bad = copy.deepcopy(self.state)
            mutate(bad)
            with self.assertRaises(ValueError):
                runner.validate_roadmap(bad)

    def test_failed_or_blocked_dependency_stops_dispatch(self):
        for status in ('FAILED', 'BLOCKED', 'IN_PROGRESS'):
            self.state['tasks'][0]['status'] = status
            self.assertIsNone(runner.choose_task(self.state))
        self.state['tasks'][0]['status'] = 'TESTED_DONE'
        self.assertEqual(runner.choose_task(self.state)['id'], 'TASK-02')

    def test_atomic_state_and_summary(self):
        path = self.folder / 'state.json'
        runner.save_state(path, self.state)
        loaded = json.loads(path.read_text())
        self.assertEqual(loaded['status_summary']['pending'], 8)
        self.assertFalse(path.with_name('state.json.tmp').exists())
        runner.save_state(path, loaded)
        self.assertEqual(json.loads(path.read_text())['tasks'], loaded['tasks'])

    def test_overlap_lock_is_released(self):
        path = self.folder / 'run.lock'
        with runtime.exclusive_lock(path):
            with self.assertRaises(OSError):
                with runtime.exclusive_lock(path):
                    pass
        with runtime.exclusive_lock(path):
            pass

    def test_nonzero_and_timeout_are_failures(self):
        log = self.folder / 'child.log'
        with self.assertRaises(RuntimeError):
            runtime.run_command([sys.executable, '-c', 'raise SystemExit(7)'], self.folder, log, 5)
        with self.assertRaises(subprocess.TimeoutExpired):
            runtime.run_command([sys.executable, '-c', 'import time; time.sleep(10)'], self.folder, log, .1)

    def test_stale_in_progress_requires_explicit_recovery(self):
        self.state['tasks'][0]['status'] = 'IN_PROGRESS'
        with patch.object(runner, 'run_command') as command:
            with self.assertRaises(RuntimeError):
                runner.run_task(self.cfg, self.folder, self.state, self.folder / 'state.json')
            command.assert_not_called()
        self.assertEqual(self.state['tasks'][0]['status'], 'FAILED')
        self.assertIsNone(runner.choose_task(self.state))

    def fake_agent(self, args, cwd, log, timeout, env):
        if 'AI_PIPELINE_ASSIGNMENT' not in env:
            return
        assignment = json.loads(Path(env['AI_PIPELINE_ASSIGNMENT']).read_text())
        runtime.write_json(assignment['result_path'], {
            'task_id': assignment['task']['id'], 'run_id': assignment['run_id'],
            'status': 'COMPLETE', 'summary': 'Offline fixture only',
            'acceptance_evidence': ['Fixture evidence'] * len(assignment['task']['acceptance_criteria'])})

    def test_missing_outputs_cannot_complete_task(self):
        self.state["tasks"][0]["required_outputs"] = ["ai_pipeline/tests/does_not_exist_fixture.php"]
        with patch.object(runner, 'run_command', side_effect=self.fake_agent):
            with self.assertRaises(ValueError):
                runner.run_task(self.cfg, self.folder, self.state, self.folder / 'state.json')
        self.assertEqual(self.state['tasks'][0]['status'], 'FAILED')

    def test_passing_agent_does_not_override_failed_verification(self):
        task = self.state['tasks'][0]
        task['required_outputs'] = ['ai_pipeline/README.md']
        def calls(args, cwd, log, timeout, env):
            if '-p' in args:
                self.fake_agent(args, cwd, log, timeout, env)
            else:
                raise RuntimeError('Fixture check failed')
        with patch.object(runner, 'run_command', side_effect=calls):
            with self.assertRaises(RuntimeError):
                runner.run_task(self.cfg, self.folder, self.state, self.folder / 'state.json')
        self.assertEqual(task['status'], 'FAILED')
        self.assertIsNone(task['completed_at'])

    def test_valid_receipt_and_checks_complete_one_task(self):
        self.state['tasks'][0]['required_outputs'] = ['ai_pipeline/README.md']
        with patch.object(runner, 'run_command', side_effect=self.fake_agent):
            self.assertEqual(runner.run_task(self.cfg, self.folder, self.state, self.folder / 'state.json'), 0)
        self.assertEqual(self.state['tasks'][0]['status'], 'TESTED_DONE')
        self.assertEqual(self.state['tasks'][1]['status'], 'PENDING')

    def test_missing_receipt_never_completes(self):
        with patch.object(runner, 'run_command'):
            with self.assertRaises(FileNotFoundError):
                runner.run_task(self.cfg, self.folder, self.state, self.folder / 'state.json')
        self.assertEqual(self.state['tasks'][0]['status'], 'FAILED')

    def test_wrong_run_receipt_rejected(self):
        with self.assertRaises(ValueError):
            runner.validate_receipt({'task_id': 'TASK-01', 'run_id': 'old'}, self.state['tasks'][0], 'new')

    def test_environment_model_absence_fails(self):
        cfg = {**self.cfg, 'environment': 'staging', 'ollama_endpoint': 'http://127.0.0.1:11434', 'ollama_model': 'gemma2:2b'}
        from unittest.mock import MagicMock
        opener = MagicMock()
        response = opener.open.return_value.__enter__.return_value
        response.status = 200
        response.read.return_value = b'{"models":[{"name":"another-model:latest"}]}'
        with patch.object(test_environment, 'load_config', return_value=cfg), patch.object(sys, 'argv', ['test_environment.py']), patch.object(test_environment.subprocess, 'run'), patch.object(test_environment, 'build_opener', return_value=opener):
            with self.assertRaisesRegex(ValueError, 'not installed'):
                test_environment.main()

    def test_config_has_no_database_fallback_and_rejects_web_runtime(self):
        with patch.dict(os.environ, {}, clear=True):
            with self.assertRaises(ValueError):
                config.load_config()
        public = self.folder / 'public'
        public.mkdir()
        path = self.folder / 'private-config.json'
        cfg = json.loads((WORKER.parent / 'pipeline_config.example.json').read_text())
        cfg.update(runtime_dir=str(public / 'runtime'), php_executable=sys.executable, agent_executable=sys.executable)
        runtime.write_json(path, cfg)
        with patch.object(config, 'PROJECT_DIR', public), patch.dict(os.environ, {'AI_PIPELINE_CONFIG': str(path)}):
            with self.assertRaisesRegex(ValueError, 'runtime_dir'):
                config.load_config()


if __name__ == '__main__':
    unittest.main()
