"""Keeps the Gamecon-specific parts of the Dependabot brief honest: the instructions, the Makefile targets they name, the wrapper and the workflow."""
import importlib.util
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
import textwrap
import unittest

sys.dont_write_bytecode = True
ROOT = os.path.normpath(os.path.join(os.path.dirname(__file__), '..', '..'))
INSTRUCTIONS_PATH = os.path.join(ROOT, '.github', 'security-update-brief.md')
WORKFLOW_PATH = os.path.join(ROOT, '.github', 'workflows', 'security-update-brief.yml')
WRAPPER_PATH = os.path.join(ROOT, 'bin', 'security-update-brief.sh')
specification = importlib.util.spec_from_file_location('security_update_brief', os.path.join(ROOT, '.github', 'scripts', 'security-update-brief.py'))
brief = importlib.util.module_from_spec(specification)
specification.loader.exec_module(brief)


def read(path):
    with open(path) as handle:
        return handle.read()


def run_blocks(workflow):
    """The text of every multi-line `run:` and one-line `run: command` of a workflow, without parsing YAML."""
    blocks, lines, index = [], workflow.split('\n'), 0
    while index < len(lines):
        match = re.match(r'^(\s*)(?:-\s+)?run:\s*(.*)$', lines[index])
        index += 1
        if not match:
            continue
        indentation, first = len(match.group(1)), match.group(2)
        block = [] if first in ('|', '>', '') else [first]
        while index < len(lines) and (not lines[index].strip() or len(lines[index]) - len(lines[index].lstrip()) > indentation):
            block.append(lines[index])
            index += 1
        blocks.append('\n'.join(block).rstrip())
    return blocks


class InstructionsTest(unittest.TestCase):
    def setUp(self):
        self.text = brief.read_instructions(INSTRUCTIONS_PATH)

    def test_the_instructions_are_one_details_block(self):
        self.assertTrue(self.text.startswith('<details>'))
        self.assertTrue(self.text.endswith('</details>'))
        self.assertEqual(1, self.text.count('<details>'))
        self.assertEqual(1, self.text.count('</details>'))

    def test_the_steps_follow_the_order_judge_add_tests_reproduce_open_our_pull_request(self):
        positions = [self.text.index(needle) for needle in (
            '4. Give each package one verdict', '5. For every `AFFECTED_NOT_COVERED`', '6. Reproduce the update', '7. Open our pull request')]
        self.assertEqual(sorted(positions), positions)
        self.assertIn('Never merge, approve or rebase this one, and never push to its branch', self.text)
        self.assertIn('a person merges it', self.text)

    def test_they_point_to_the_workflow_that_folds_the_composer_pull_requests_and_it_exists(self):
        self.assertIn('the workflow `fold-dependabot-composer` (Actions, Run workflow, the branch `main`)', self.text)
        self.assertTrue(os.path.isfile(FOLD_WORKFLOW_PATH))
        self.assertTrue(os.path.isfile(os.path.join(ROOT, 'docs', 'fold-dependabot-composer.md')))

    def test_every_make_target_the_instructions_name_exists(self):
        targets = set(re.findall(r'^([A-Za-z][\w-]*):', read(os.path.join(ROOT, 'Makefile')), re.MULTILINE))
        named = set(re.findall(r'`make ([a-z][\w-]*)`', self.text))
        self.assertTrue({'check-vendor-matches-lock', 'check-dockerignore', 'tests-be', 'phpstan', 'ui-deps', 'test-ui', 'check-ui-bundles'} <= named)
        self.assertEqual(set(), named - targets)

    def test_every_docker_wrapper_and_committed_build_output_the_instructions_name_exists(self):
        for wrapper in set(re.findall(r'\./bin-docker/([a-z-]+)', self.text)):
            self.assertTrue(os.path.isfile(os.path.join(ROOT, 'bin-docker', wrapper)), wrapper)
        for directory in set(re.findall(r'`((?:web/soubory|admin/files)/ui)`', self.text)):
            self.assertTrue(os.path.isdir(os.path.join(ROOT, directory)), directory)

    def test_the_composer_patches_and_the_ui_lockfile_the_instructions_name_exist(self):
        self.assertTrue(os.path.isdir(os.path.join(ROOT, 'patches')))
        self.assertTrue(os.path.isfile(os.path.join(ROOT, 'ui', 'yarn.lock')))

    def test_the_instructions_name_the_packages_without_namespaces_that_gamecon_really_has(self):
        lock = brief.parse_composer_lock(read(os.path.join(ROOT, 'composer.lock')))
        for name in re.findall(r'`((?:setasign|dg)/[a-z0-9.-]+)`', self.text):
            self.assertTrue(all(package.classes_only for package in lock[name]), name)


class WrapperTest(unittest.TestCase):
    def setUp(self):
        self.text = read(WRAPPER_PATH)

    def test_every_scanned_directory_exists_and_the_list_is_sorted(self):
        arguments = re.search(r'--scan-directories ([^\\\n]+)', self.text).group(1).split()
        self.assertEqual(sorted(arguments), arguments)
        for directory in arguments:
            self.assertTrue(os.path.isdir(os.path.join(ROOT, directory)), directory)

    def test_the_scanned_directories_hold_the_code_that_uses_vendor_packages(self):
        arguments = set(re.search(r'--scan-directories ([^\\\n]+)', self.text).group(1).split())
        self.assertTrue({'model', 'web', 'admin', 'symfony', 'tests'} <= arguments)
        self.assertTrue(arguments.isdisjoint({'vendor', 'ui', 'cache', 'logy', 'node_modules'}))

    def test_the_repository_instructions_are_passed_and_exist(self):
        instructions = re.search(r'--instructions-file "\$\{BRIEF_INSTRUCTIONS:-([^}]+)\}"', self.text).group(1)
        self.assertEqual(INSTRUCTIONS_PATH, os.path.join(ROOT, instructions))

    def test_the_scripts_are_executable(self):
        for name in ('security-update-brief.sh', 'test-security-brief.sh', 'fold-dependabot-composer.sh'):
            self.assertTrue(os.access(os.path.join(ROOT, 'bin', name), os.X_OK), name)


class ScriptCopyTest(unittest.TestCase):
    def test_the_script_is_a_copy_that_says_which_version_of_the_shared_script_it_is(self):
        self.assertRegex(read(os.path.join(ROOT, '.github', 'scripts', 'security-update-brief.py')), r'(?m)^Version: \d+\.\d+\.\d+')


class WrapperRunTest(unittest.TestCase):
    """Runs the real wrapper on a throwaway repository, with a stand-in `composer` that finds no advisories."""

    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.root = os.path.join(self.directory.name, 'repository')
        for relative in ('bin/security-update-brief.sh', '.github/scripts/security-update-brief.py', '.github/security-update-brief.md', '.github/security-update-brief-batch.md'):
            os.makedirs(os.path.dirname(os.path.join(self.root, relative)), exist_ok=True)
            shutil.copy2(os.path.join(ROOT, relative), os.path.join(self.root, relative))
        self.fake_bin = os.path.join(self.directory.name, 'fake-bin')
        os.makedirs(self.fake_bin)
        composer = os.path.join(self.fake_bin, 'composer')
        with open(composer, 'w') as handle:
            handle.write('#!/bin/sh\necho \'{"advisories": []}\'\n')
        os.chmod(composer, 0o755)
        self.git('init', '--quiet', '-b', 'main')
        self.git('config', 'user.email', 'test@example.com')
        self.git('config', 'user.name', 'Test')
        self.git('config', 'commit.gpgsign', 'false')
        self.commit('1.0.0', 'old')
        self.commit('1.0.1', 'new')

    def tearDown(self):
        self.directory.cleanup()

    @staticmethod
    def environment():
        """The caller's environment without the variables that would aim git at another repository."""
        return {name: value for name, value in os.environ.items() if not name.startswith('GIT_')}

    def git(self, *arguments):
        result = subprocess.run(['git', '-C', self.root, *arguments], capture_output=True, text=True, env=self.environment())
        self.assertEqual(0, result.returncode, result.stderr)

    def commit(self, version, reference):
        lock = {'packages': [{'name': 'a/b', 'version': version, 'source': {'type': 'git', 'url': 'https://github.com/a/b.git', 'reference': reference},
                              'autoload': {'psr-4': {'A\\B\\': 'src/'}}}]}
        files = {'composer.json': '{}', 'composer.lock': json.dumps(lock), 'model/Thing.php': '<?php use A\\B\\C;', 'src/Other.php': '<?php use A\\B\\C;'}
        for path, content in files.items():
            os.makedirs(os.path.dirname(os.path.join(self.root, path)), exist_ok=True)
            with open(os.path.join(self.root, path), 'w') as handle:
                handle.write(content)
        self.git('add', '-A')
        self.git('commit', '--quiet', '-m', 'commit')

    def run_wrapper(self, *arguments, **extra_environment):
        environment = {**self.environment(), 'PATH': self.fake_bin + os.pathsep + os.environ['PATH'], **extra_environment}
        return subprocess.run(['bash', os.path.join(self.root, 'bin', 'security-update-brief.sh'), *arguments], cwd=self.directory.name,
                              env=environment, capture_output=True, text=True)

    def test_the_instructions_of_the_folded_pull_request_are_used_when_the_caller_names_them(self):
        result = self.run_wrapper('HEAD~1', 'HEAD', 'out.md', BRIEF_INSTRUCTIONS='.github/security-update-brief-batch.md')
        self.assertEqual(0, result.returncode, result.stderr)
        text = read(os.path.join(self.directory.name, 'out.md'))
        self.assertIn('nothing has to be reproduced by hand', text)
        self.assertNotIn('It is not merged on GitHub', text)

    def test_the_brief_has_the_instructions_of_gamecon_the_directories_of_gamecon_and_lands_where_the_caller_asked(self):
        result = self.run_wrapper('HEAD~1', 'HEAD', 'out.md', 'a-label')
        self.assertEqual(0, result.returncode, result.stderr)
        text = read(os.path.join(self.directory.name, 'out.md'))
        self.assertFalse(os.path.exists(os.path.join(self.root, 'out.md')))
        self.assertIn('It is not merged on GitHub', text)
        self.assertNotIn('make e2e-shop', text)
        self.assertIn('Head `a-label`', text)
        self.assertIn('"used_in":{"model":1}', text)

    def test_the_head_is_the_label_when_none_is_given(self):
        self.assertEqual(0, self.run_wrapper('HEAD~1', 'HEAD', 'out.md').returncode)
        self.assertIn('Head `HEAD`', read(os.path.join(self.directory.name, 'out.md')))

    def test_missing_arguments_say_how_to_call_it(self):
        for arguments in ((), ('HEAD~1',), ('HEAD~1', 'HEAD')):
            result = self.run_wrapper(*arguments)
            self.assertNotEqual(0, result.returncode)
            self.assertIn('usage: bin/security-update-brief.sh', result.stderr)

    def test_an_unknown_commit_fails_with_the_message_of_git(self):
        result = self.run_wrapper('no-such-commit', 'HEAD', 'out.md')
        self.assertNotEqual(0, result.returncode)
        self.assertIn('git diff --name-only failed', result.stderr)
        self.assertFalse(os.path.exists(os.path.join(self.directory.name, 'out.md')))


class WorkflowTest(unittest.TestCase):
    def setUp(self):
        self.text = read(WORKFLOW_PATH)

    def test_the_workflow_builds_the_brief_with_the_wrapper_so_that_it_cannot_lose_the_directories_or_the_instructions(self):
        self.assertRegex(self.text, r'pull_request_head=\$\(git rev-parse --verify HEAD\^2\)\s+bin/security-update-brief\.sh HEAD\^1 HEAD brief\.md "\$pull_request_head"')
        self.assertNotIn('--scan-directories', self.text)
        self.assertNotIn('security-update-brief.py brief', self.text)

    def test_runs_queue_instead_of_cancelling_so_that_a_stale_run_cannot_cost_the_newer_one_its_brief(self):
        self.assertIn('cancel-in-progress: false', self.text)
        self.assertNotIn('cancel-in-progress: true', self.text)

    def test_only_dependabot_pull_requests_get_a_brief_and_the_token_may_write_only_pull_requests(self):
        self.assertIn("if: github.event.pull_request.user.login == 'dependabot[bot]'", self.text)
        self.assertIn('permissions: {}', self.text)
        self.assertEqual(1, self.text.count('pull-requests: write'))
        self.assertNotIn('contents: write', self.text)
        self.assertNotIn('pull_request_target', self.text)

    def test_the_workflow_ignores_the_checkout_credentials_and_edits_the_body_in_one_step(self):
        self.assertIn('persist-credentials: false', self.text)
        self.assertEqual(1, self.text.count('--method PATCH'))
        self.assertIn('env -u GH_TOKEN python3 -I .github/scripts/security-update-brief.py splice', self.text)

    def test_no_expression_is_interpolated_into_a_shell_command(self):
        blocks = run_blocks(self.text)
        self.assertEqual(2, len(blocks))
        for block in blocks:
            self.assertNotIn('${{', block)

    def test_a_run_whose_head_is_no_longer_the_head_of_the_pull_request_writes_nothing(self):
        self.assertIn('built_for=$(git rev-parse --verify HEAD^2)', self.text)
        self.assertIn('current_head=$(jq -r .head.sha pull-request.json)', self.text)
        guard = re.search(r'if \[ "\$built_for" != "\$current_head" \]; then\n\s+echo "::notice::[^\n]*"\n\s+exit 0\n\s+fi\n', self.text)
        self.assertIsNotNone(guard)
        self.assertLess(guard.end(), self.text.index('--method PATCH'))
        self.assertLess(self.text.index('gh api "$PULL_REQUEST_URL" > pull-request.json'), guard.start())


FOLD_WORKFLOW_PATH = os.path.join(ROOT, '.github', 'workflows', 'fold-dependabot-composer.yml')
BATCH_INSTRUCTIONS_PATH = os.path.join(ROOT, '.github', 'security-update-brief-batch.md')


def job_sections(workflow):
    """The text of each job of the fold workflow, by its name."""
    parts = re.split(r'(?m)^    (plan|build|publish):\n', workflow.split('\njobs:\n')[1])
    return {parts[index]: parts[index + 1] for index in range(1, len(parts), 2)}


class BatchInstructionsTest(unittest.TestCase):
    def setUp(self):
        self.text = brief.read_instructions(BATCH_INSTRUCTIONS_PATH)

    def test_the_instructions_are_one_details_block_that_say_nothing_has_to_be_reproduced(self):
        self.assertTrue(self.text.startswith('<details>') and self.text.endswith('</details>'))
        self.assertEqual(1, self.text.count('<details>'))
        self.assertIn('nothing has to be reproduced by hand', self.text)

    def test_a_person_merges_and_nobody_pushes_to_the_branch_or_touches_the_dependabot_pull_requests(self):
        self.assertIn('Do not push to its branch', self.text)
        self.assertIn('a person merges this pull request', self.text)
        self.assertIn('Never merge, approve or rebase a Dependabot pull request itself', self.text)

    def test_the_steps_follow_the_order_judge_add_tests_wait_for_the_tests(self):
        positions = [self.text.index(needle) for needle in ('4. Give each package one verdict', '5. For every `AFFECTED_NOT_COVERED`', '6. The workflow has started')]
        self.assertEqual(sorted(positions), positions)

    def test_the_wrapper_can_be_told_to_use_these_instructions(self):
        self.assertIn('"${BRIEF_INSTRUCTIONS:-.github/security-update-brief.md}"', read(WRAPPER_PATH))
        self.assertIn('BRIEF_INSTRUCTIONS: .github/security-update-brief-batch.md', read(FOLD_WORKFLOW_PATH))


class FoldWorkflowTest(unittest.TestCase):
    def setUp(self):
        self.text = read(FOLD_WORKFLOW_PATH)
        self.jobs = job_sections(self.text)

    def test_it_is_started_by_hand_only_and_only_on_main(self):
        self.assertIn('workflow_dispatch:', self.text)
        self.assertNotRegex(self.text, r'(?m)^\s+(pull_request|pull_request_target|push|schedule|workflow_run):')
        self.assertIn("if: github.ref == 'refs/heads/main'", self.jobs['plan'])

    def test_a_run_is_never_cancelled(self):
        self.assertIn('cancel-in-progress: false', self.text)
        self.assertNotIn('cancel-in-progress: true', self.text)

    def test_the_job_that_runs_packages_has_no_token_and_no_secrets(self):
        build = self.jobs['build']
        self.assertRegex(build, r'permissions:\n            contents: read\n        outputs:')
        self.assertIn('persist-credentials: false', build)
        for needle in ('GH_TOKEN', 'github.token', 'secrets.', 'pull-requests', 'contents: write'):
            self.assertNotIn(needle, build)

    def test_each_job_runs_only_when_the_one_before_found_something_to_do(self):
        self.assertIn("if: needs.plan.outputs.fold == 'true'", self.jobs['build'])
        self.assertIn("if: needs.build.outputs.folded == 'true'", self.jobs['publish'])
        self.assertIn('needs: [plan, build]', self.jobs['publish'])

    def test_the_job_that_has_the_token_runs_none_of_the_packages_nor_the_folding(self):
        publish = self.jobs['publish']
        for needle in ('contents: write', 'pull-requests: write', 'actions: write'):
            self.assertIn(needle, publish)
        for needle in ('fold-dependabot-composer.sh', 'composer install', 'docker run'):
            self.assertNotIn(needle, publish)

    def test_only_the_planning_job_and_the_publishing_job_read_a_token_and_the_planning_one_cannot_write(self):
        plan = self.jobs['plan']
        self.assertRegex(plan, r'permissions:\n            contents: read\n            pull-requests: read\n')
        self.assertNotIn('write', plan)
        self.assertEqual(1, plan.count('GH_TOKEN: ${{ github.token }}'))

    def test_the_publishing_job_checks_the_build_before_it_writes_anything_and_pushes_once_with_the_lease_of_the_plan(self):
        publish = self.jobs['publish']
        self.assertLess(publish.index('name: Check what the build produced'), publish.index('name: Write the brief and the body'))
        self.assertLess(publish.index('name: Write the brief and the body'), publish.index('push --force-with-lease'))
        self.assertIn('push --force-with-lease="refs/heads/$BATCH_BRANCH:$BRANCH_TIP"', publish)
        self.assertIn('BRANCH_TIP: ${{ needs.plan.outputs.branch_tip }}', publish)
        self.assertEqual(1, publish.count('push --force-with-lease'))
        self.assertEqual(1, len(re.findall(r'(?m)^\s+(?:git .*)?push ', publish)))
        self.assertNotIn('fsckObjects', publish)

    def test_no_credentials_stay_in_the_checkout_and_only_the_steps_that_talk_to_github_get_the_token(self):
        publish = self.jobs['publish']
        self.assertIn('persist-credentials: false', publish)
        steps = re.split(r'(?m)^            -   ', publish)[1:]
        with_token = [step.split('\n')[0] for step in steps if 'GH_TOKEN' in step]
        self.assertEqual(['name: Push the branch', 'name: Open or update the pull request', 'name: Start the tests',
                          'name: Record that the pull request is up to date'], with_token)

    def test_the_pull_request_is_marked_up_to_date_only_after_the_tests_were_started(self):
        publish = self.jobs['publish']
        self.assertLess(publish.index('gh workflow run run-tests.yml'), publish.index('name: Record that the pull request is up to date'))
        self.assertIn('<!-- fold-dependabot-composer: pending -->', publish)
        self.assertNotIn('SIGNATURE', publish[:publish.index('name: Record that the pull request is up to date')])

    def test_the_brief_and_the_pull_request_are_made_from_the_commit_the_run_started_from_not_from_the_main_of_now(self):
        publish = self.jobs['publish']
        self.assertNotIn('origin/main', publish)
        self.assertIn('bin/security-update-brief.sh "$GITHUB_SHA" fold-incoming', publish)

    def test_a_pull_request_from_a_fork_with_the_same_branch_name_is_never_taken_for_the_folded_one(self):
        self.assertEqual(2, self.text.count('--json body,isCrossRepository') + self.text.count('--json number,isCrossRepository'))
        self.assertEqual(2, self.text.count('select(.isCrossRepository | not)'))

    def test_the_jq_filter_that_leaves_out_pull_requests_from_forks_keeps_only_ours(self):
        jq = shutil.which('jq')
        if jq is None:
            self.skipTest('jq is not installed here; the runners have it')
        filter_ = re.search(r"--jq '(\[\.\[\] \| select\(\.isCrossRepository \| not\)\] \| \.\[0\]\.number // empty)'", self.text).group(1)
        pull_requests = json.dumps([{'number': 7, 'isCrossRepository': True}, {'number': 8, 'isCrossRepository': False}])
        self.assertEqual('8\n', subprocess.run([jq, '-r', filter_], input=pull_requests, capture_output=True, text=True).stdout)
        self.assertEqual('', subprocess.run([jq, '-r', filter_], input=json.dumps([{'number': 7, 'isCrossRepository': True}]), capture_output=True, text=True).stdout)

    def test_the_tests_are_started_by_dispatch_because_a_pull_request_of_the_workflow_token_starts_none(self):
        self.assertIn('gh workflow run run-tests.yml --ref "$BATCH_BRANCH"', self.text)
        self.assertIn('workflow_dispatch:', read(os.path.join(ROOT, '.github', 'workflows', 'run-tests.yml')))

    def test_no_expression_is_interpolated_into_a_shell_command(self):
        for block in run_blocks(self.text):
            self.assertNotIn('${{', block)

    def test_the_scripts_it_calls_exist_and_run_the_same_code_as_a_local_run(self):
        for name in ('fold-dependabot-composer.sh', 'security-update-brief.sh'):
            self.assertIn('bin/' + name, self.text)
            self.assertTrue(os.access(os.path.join(ROOT, 'bin', name), os.X_OK), name)

    def test_the_default_shell_is_bash_with_pipefail_because_a_failing_fold_in_front_of_tee_must_fail_the_step(self):
        self.assertRegex(self.text, r'defaults:\n    run:\n        shell: bash\n')
        self.assertIn('| tee "$RUNNER_TEMP/fold-summary.txt"', self.text)

    def test_the_build_folds_the_heads_the_plan_listed_so_the_signature_describes_what_was_built(self):
        self.assertIn('HEADS: ${{ needs.plan.outputs.heads }}', self.jobs['build'])
        self.assertIn('bin/fold-dependabot-composer.sh "${heads[@]}"', self.jobs['build'])


class FoldWorkflowPlanRunTest(unittest.TestCase):
    """Runs the shell of the planning step against stand-ins for gh, with real git."""

    FAKE_GH = '#!/bin/sh\necho "$*" >> "$FAKE_GH_LOG"\ncase "$*" in *--jq*) cat "$FAKE_BATCH_BODY" 2>/dev/null ;; *) cat "$FAKE_GH_JSON" ;; esac\n'

    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.root = os.path.join(self.directory.name, 'repository')
        os.makedirs(os.path.join(self.root, 'bin'))
        shutil.copy2(os.path.join(ROOT, 'bin', 'fold-dependabot-composer.sh'), os.path.join(self.root, 'bin', 'fold-dependabot-composer.sh'))
        self.fake_bin = os.path.join(self.directory.name, 'fake-bin')
        os.makedirs(self.fake_bin)
        self.gh = os.path.join(self.fake_bin, 'gh')
        with open(self.gh, 'w') as handle:
            handle.write(self.FAKE_GH)
        os.chmod(self.gh, 0o755)
        self.environment = {name: value for name, value in os.environ.items() if not name.startswith('GIT_')}
        for arguments in (('init', '--quiet', '-b', 'main'), ('config', 'user.email', 'test@example.com'), ('config', 'user.name', 'Test'),
                          ('config', 'commit.gpgsign', 'false'), ('add', '-A'), ('commit', '--quiet', '-m', 'main')):
            subprocess.run(['git', '-C', self.root, *arguments], check=True, capture_output=True, env=self.environment)
        self.main = subprocess.run(['git', '-C', self.root, 'rev-parse', 'HEAD'], check=True, capture_output=True, text=True, env=self.environment).stdout.strip()
        self.origin = os.path.join(self.directory.name, 'origin.git')
        for arguments in (['init', '--quiet', '--bare', '-b', 'main', self.origin], ['-C', self.root, 'remote', 'add', 'origin', self.origin],
                          ['-C', self.root, 'push', '--quiet', 'origin', 'main']):
            subprocess.run(['git', *arguments], check=True, capture_output=True, env=self.environment)
        self.pull_requests = os.path.join(self.directory.name, 'pull-requests.json')
        self.batch_body = os.path.join(self.directory.name, 'batch-body.md')
        self.output = os.path.join(self.directory.name, 'github-output')
        self.log = os.path.join(self.directory.name, 'gh.log')
        self.step = textwrap.dedent([block for block in run_blocks(read(FOLD_WORKFLOW_PATH)) if '--list' in block][0])

    def tearDown(self):
        self.directory.cleanup()

    def run_plan(self, pull_requests, requested='', batch_body=None):
        with open(self.pull_requests, 'w') as handle:
            json.dump([{'number': number, 'headRefName': 'dependabot/composer/p%d' % number, 'headRefOid': sha, 'files': [{'path': 'composer.lock'}]}
                       for number, sha in pull_requests], handle)
        with open(self.batch_body, 'w') as handle:
            handle.write(batch_body or '')
        open(self.output, 'w').close()
        environment = {**self.environment, 'PATH': self.fake_bin + os.pathsep + os.environ['PATH'], 'GH': self.gh, 'GH_TOKEN': 'x',
                       'REQUESTED': requested, 'BATCH_BRANCH': 'security-updates/composer', 'GITHUB_OUTPUT': self.output,
                       'FAKE_GH_JSON': self.pull_requests, 'FAKE_BATCH_BODY': self.batch_body, 'FAKE_GH_LOG': self.log}
        return subprocess.run(['bash', '-eo', 'pipefail', '-c', self.step], cwd=self.root, env=environment, capture_output=True, text=True)

    def plan(self, pull_requests, requested='', batch_body=None):
        result = self.run_plan(pull_requests, requested, batch_body)
        self.assertEqual(0, result.returncode, result.stderr)
        return dict(line.split('=', 1) for line in read(self.output).splitlines()), result

    def test_nothing_to_fold_when_no_dependabot_composer_pull_request_is_open(self):
        outputs, result = self.plan([])
        self.assertEqual({'fold': 'false'}, outputs)
        self.assertIn('No open Dependabot composer pull request to fold.', result.stdout)

    def test_every_open_pull_request_is_folded_when_there_is_no_folded_one_yet(self):
        outputs, _ = self.plan([(20, 'bbb'), (10, 'aaa')])
        self.assertEqual({'fold': 'true', 'pull_requests': '10 20', 'heads': '10@aaa 20@bbb',
                          'signature': 'main=%s 10@aaa 20@bbb' % self.main, 'branch_tip': ''}, outputs)

    def test_the_tip_of_the_folded_branch_the_run_saw_is_handed_on_as_the_lease_and_only_that_exact_branch_counts(self):
        for name in ('security-updates/composer', 'x/security-updates/composer'):
            subprocess.run(['git', '-C', self.root, 'push', '--quiet', 'origin', 'main:refs/heads/' + name], check=True, capture_output=True, env=self.environment)
        outputs, _ = self.plan([(10, 'aaa')])
        self.assertEqual(self.main, outputs['branch_tip'])

    def test_a_failing_ls_remote_fails_the_plan_instead_of_saying_that_the_branch_does_not_exist(self):
        subprocess.run(['git', '-C', self.root, 'remote', 'set-url', 'origin', os.path.join(self.directory.name, 'no-such-repository.git')],
                       check=True, capture_output=True, env=self.environment)
        result = self.run_plan([(10, 'aaa')])
        self.assertNotEqual(0, result.returncode)
        self.assertNotIn('fold=true', read(self.output))

    def test_it_asks_gh_for_open_pull_requests_of_dependabot_and_for_the_folded_one_from_this_repository_only(self):
        self.plan([(10, 'aaa')])
        calls = read(self.log).splitlines()
        self.assertEqual(['pr list --state open --author app/dependabot --base main --limit 1000 --json number,headRefName,headRefOid,files',
                          'pr list --head security-updates/composer --state open --json body,isCrossRepository --jq [.[] | select(.isCrossRepository | not)] | .[0].body // ""'],
                         calls)

    def test_a_folded_pull_request_that_has_the_same_inputs_is_left_alone(self):
        body = '<!-- fold-dependabot-composer: main=%s 10@aaa 20@bbb -->\ntext' % self.main
        outputs, result = self.plan([(10, 'aaa'), (20, 'bbb')], batch_body=body)
        self.assertEqual({'fold': 'false'}, outputs)
        self.assertIn('is up to date', result.stdout)

    def test_a_pull_request_whose_tests_were_never_started_is_not_taken_for_up_to_date(self):
        body = '<!-- fold-dependabot-composer: pending -->\ntext'
        self.assertEqual('true', self.plan([(10, 'aaa'), (20, 'bbb')], batch_body=body)[0]['fold'])

    def test_a_new_head_of_a_dependabot_pull_request_or_a_new_main_makes_the_folded_one_stale(self):
        body = '<!-- fold-dependabot-composer: main=%s 10@aaa 20@bbb -->' % self.main
        self.assertEqual('true', self.plan([(10, 'aaa'), (20, 'changed')], batch_body=body)[0]['fold'])
        stale_main = '<!-- fold-dependabot-composer: main=%s 10@aaa 20@bbb -->' % ('0' * 40)
        self.assertEqual('true', self.plan([(10, 'aaa'), (20, 'bbb')], batch_body=stale_main)[0]['fold'])

    def test_a_request_for_some_numbers_folds_only_those(self):
        outputs, _ = self.plan([(10, 'aaa'), (20, 'bbb'), (30, 'ccc')], requested='20 30')
        self.assertEqual('20 30', outputs['pull_requests'])
        self.assertEqual('20@bbb 30@ccc', outputs['heads'])
        self.assertEqual('main=%s 20@bbb 30@ccc' % self.main, outputs['signature'])

    def test_a_request_for_a_number_that_is_not_open_folds_nothing(self):
        self.assertEqual({'fold': 'false'}, self.plan([(10, 'aaa')], requested='99')[0])

    def test_a_request_of_only_blanks_folds_everything_like_no_request(self):
        self.assertEqual('10', self.plan([(10, 'aaa')], requested='  ')[0]['pull_requests'])

    def test_a_request_that_is_not_a_list_of_numbers_fails_instead_of_being_taken_for_a_pattern(self):
        for requested in ('(', '.*', '10 x', '10,20', '-1'):
            with self.subTest(requested=requested):
                result = self.run_plan([(10, 'aaa'), (20, 'bbb')], requested=requested)
                self.assertNotEqual(0, result.returncode)
                self.assertIn('Not a pull request number', result.stdout)


class FoldWorkflowPublishCheckRunTest(unittest.TestCase):
    """Runs the shell that checks what the build job produced, in a repository that has only main, against bundles made elsewhere."""

    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.root = os.path.join(self.directory.name, 'publisher')
        self.producer = os.path.join(self.directory.name, 'producer')
        self.runner_temp = os.path.join(self.directory.name, 'runner-temp')
        os.makedirs(os.path.join(self.runner_temp, 'fold'))
        os.makedirs(self.root)
        self.environment = {name: value for name, value in os.environ.items() if not name.startswith('GIT_')}
        self.workdir = self.root
        self.git('init', '--quiet', '-b', 'main')
        self.git('config', 'user.email', 'test@example.com')
        self.git('config', 'user.name', 'Test')
        self.git('config', 'commit.gpgsign', 'false')
        self.write('composer.lock', 'lock 1')
        self.write('vendor/a.txt', 'a')
        self.write('vendor/pkg/.gitattributes', '* text=auto')
        self.write('bin/check.php', '<?php')
        self.commit('main')
        self.main = self.git('rev-parse', 'HEAD')
        subprocess.run(['git', 'clone', '--quiet', self.root, self.producer], check=True, capture_output=True, env=self.environment)
        self.workdir = self.producer
        self.git('config', 'user.email', 'test@example.com')
        self.git('config', 'user.name', 'Test')
        self.git('config', 'commit.gpgsign', 'false')
        self.workdir = self.root
        # GitHub hands the shell the block without the indentation of the YAML; the Python inside it needs that
        self.step = textwrap.dedent([block for block in run_blocks(read(FOLD_WORKFLOW_PATH)) if 'fold-incoming' in block and 'merge-base' in block][0])

    def tearDown(self):
        self.directory.cleanup()

    def git(self, *arguments):
        result = subprocess.run(['git', '-C', self.workdir, *arguments], capture_output=True, text=True, env=self.environment)
        self.assertEqual(0, result.returncode, result.stderr)
        return result.stdout.strip()

    def write(self, relative, content):
        path = os.path.join(self.workdir, relative)
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, 'w') as handle:
            handle.write(content)

    def commit(self, message):
        self.git('add', '-A')
        self.git('commit', '--quiet', '--allow-empty', '-m', message)

    def bundle(self, build, base=None, summary='folded 1\n'):
        """What `build` does in the producer's clone on top of `base`, as the build job makes it, as a bundle; the publisher has none of its objects."""
        base = base or self.main
        self.workdir = self.producer
        self.git('switch', '--quiet', '--detach', base)
        build()
        self.git('bundle', 'create', os.path.join(self.runner_temp, 'fold', 'fold.bundle'), base + '..HEAD')
        self.git('switch', '--quiet', '--detach', self.main)
        self.workdir = self.root
        subprocess.run(['git', '-C', self.root, 'branch', '-D', 'fold-incoming'], capture_output=True, env=self.environment)
        with open(os.path.join(self.runner_temp, 'fold', 'fold-summary.txt'), 'w') as handle:
            handle.write(summary)

    def check(self, github_sha=None):
        environment = {**self.environment, 'RUNNER_TEMP': self.runner_temp, 'GITHUB_SHA': github_sha or self.main}
        return subprocess.run(['bash', '-eo', 'pipefail', '-c', self.step], cwd=self.root, env=environment, capture_output=True, text=True)

    def change(self, *paths):
        def build():
            for path in paths:
                self.write(path, 'changed')
            self.commit('build')
        return build

    def test_a_lock_and_vendor_change_on_the_commit_the_run_started_from_is_accepted(self):
        self.bundle(self.change('composer.lock', 'vendor/a.txt', 'vendor/new/b.txt'))
        result = self.check()
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual(self.main, self.git('merge-base', self.main, 'fold-incoming'))

    def test_names_with_a_space_or_a_non_ascii_character_inside_vendor_are_accepted(self):
        self.bundle(self.change('vendor/sp ace/file.txt', 'vendor/x/tést.txt'))
        result = self.check()
        self.assertEqual(0, result.returncode, result.stderr)

    def test_a_file_moved_into_vendor_is_refused_because_the_move_deletes_it_where_it_was(self):
        def build():
            self.git('mv', 'bin/check.php', 'vendor/check.php')
            self.commit('build')
        self.bundle(build)
        result = self.check()
        self.assertNotEqual(0, result.returncode)
        self.assertIn('changes more than composer.lock and vendor/', result.stderr)
        self.assertIn('bin/check.php', result.stderr)

    def test_a_path_that_only_starts_like_the_lock_or_vendor_is_refused(self):
        for path in ('composer.lock.php', 'vendor-evil/x', 'bin/check.php'):
            with self.subTest(path=path):
                self.bundle(self.change(path))
                result = self.check()
                self.assertNotEqual(0, result.returncode)
                self.assertIn(path, result.stderr)

    def test_a_directory_in_the_place_of_the_lock_is_refused(self):
        def build():
            self.git('rm', '--quiet', 'composer.lock')
            self.write('composer.lock/x', 'x')
            self.commit('build')
        self.bundle(build)
        result = self.check()
        self.assertNotEqual(0, result.returncode)
        self.assertIn('composer.lock/x', result.stderr)

    def test_a_symlink_a_submodule_or_a_git_attributes_file_inside_vendor_is_refused(self):
        def symlink():
            os.symlink('../.github/workflows', os.path.join(self.producer, 'vendor', 'evil'))
            self.commit('build')

        def submodule():
            self.git('update-index', '--add', '--cacheinfo', '160000,%s,vendor/module' % self.main)
            self.git('commit', '--quiet', '-m', 'build')  # not `commit()`: its `git add -A` would drop the entry that has no working tree behind it
        for name, build, path in (('symlink', symlink, 'vendor/evil'), ('submodule', submodule, 'vendor/module'),
                                  ('gitattributes', self.change('vendor/x/.gitattributes'), 'vendor/x/.gitattributes'),
                                  ('gitmodules', self.change('vendor/x/.gitmodules'), 'vendor/x/.gitmodules')):
            with self.subTest(name=name):
                self.bundle(build)
                result = self.check()
                self.assertNotEqual(0, result.returncode)
                self.assertIn(path, result.stderr)
                self.assertIn('not a plain file', result.stderr)

    def test_a_git_attributes_file_that_a_package_in_vendor_already_has_may_be_changed_or_removed(self):
        self.bundle(self.change('vendor/pkg/.gitattributes'))
        self.assertEqual(0, self.check().returncode)

        def remove():
            self.git('rm', '--quiet', 'vendor/pkg/.gitattributes')
            self.commit('build')
        self.bundle(remove)
        result = self.check()
        self.assertEqual(0, result.returncode, result.stderr)

    def test_a_summary_that_is_missing_from_the_artifact_fails_the_check_instead_of_reading_as_an_empty_one(self):
        self.bundle(self.change('composer.lock'))
        os.remove(os.path.join(self.runner_temp, 'fold', 'fold-summary.txt'))
        self.assertNotEqual(0, self.check().returncode)

    def test_a_branch_built_on_an_older_commit_than_the_one_the_run_started_from_is_refused(self):
        self.bundle(self.change('composer.lock'))
        self.write('later.txt', 'later')
        self.commit('main moved')
        later = self.git('rev-parse', 'HEAD')
        result = self.check(github_sha=later)
        self.assertNotEqual(0, result.returncode)
        self.assertIn('not built on the commit this run started from', result.stderr)

    def test_more_than_a_hundred_commits_are_refused(self):
        def build():
            for number in range(101):
                self.commit('empty %d' % number)
        self.bundle(build)
        result = self.check()
        self.assertNotEqual(0, result.returncode)
        self.assertIn('more than 100 commits', result.stderr)

    def test_only_the_lines_the_script_itself_can_print_reach_the_pull_request(self):
        self.bundle(self.change('composer.lock'), summary=(
            'composer says: skipped 9: @someone look\n'
            'skipped 3: its lock commit does not apply to this branch\n'
            'skipped 4: www.evil.example/x\n'
            'skipped 5: it changes more than composer.lock\n'
            'skipped 6: already applied\n'
            'skipped 7: its head moved since the list was made\n'
            'folded 1\n'
            'folded 2 and more\n'
            'synced vendor/ for 1\n'))
        self.assertEqual(0, self.check().returncode)
        self.assertEqual('skipped 3: its lock commit does not apply to this branch\nskipped 5: it changes more than composer.lock\n'
                         'skipped 6: already applied\nskipped 7: its head moved since the list was made\nfolded 1\n',
                         read(os.path.join(self.runner_temp, 'summary.txt')))


class FoldWorkflowPublishBodyRunTest(unittest.TestCase):
    """Runs the shell that writes the body of the folded pull request and the one that records its signature, with stand-ins for the brief and gh."""

    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.root = os.path.join(self.directory.name, 'repository')
        self.runner_temp = os.path.join(self.directory.name, 'runner-temp')
        os.makedirs(self.runner_temp)
        for relative in ('bin', '.github/scripts'):
            os.makedirs(os.path.join(self.root, relative))
        shutil.copy2(os.path.join(ROOT, '.github', 'scripts', 'security-update-brief.py'), os.path.join(self.root, '.github', 'scripts', 'security-update-brief.py'))
        self.stub = os.path.join(self.root, 'bin', 'security-update-brief.sh')
        with open(self.stub, 'w') as handle:
            handle.write('#!/bin/sh\nprintf "<!-- security-update-brief:start -->\\nTHE BRIEF for $BRIEF_INSTRUCTIONS\\n<!-- security-update-brief:end -->\\n" > "$3"\n')
        os.chmod(self.stub, 0o755)
        self.environment = {name: value for name, value in os.environ.items() if not name.startswith('GIT_')}
        for arguments in (('init', '--quiet', '-b', 'main'), ('config', 'user.email', 'test@example.com'), ('config', 'user.name', 'Test'),
                          ('config', 'commit.gpgsign', 'false'), ('add', '-A'), ('commit', '--quiet', '-m', 'main'), ('branch', 'fold-incoming')):
            subprocess.run(['git', '-C', self.root, *arguments], check=True, capture_output=True, env=self.environment)
        self.main = subprocess.run(['git', '-C', self.root, 'rev-parse', 'HEAD'], check=True, capture_output=True, text=True, env=self.environment).stdout.strip()
        blocks = run_blocks(read(FOLD_WORKFLOW_PATH))
        self.body_step = textwrap.dedent([block for block in blocks if 'splice' in block][0])
        self.record_step = textwrap.dedent([block for block in blocks if 'body-final.md' in block][0])
        self.bin = os.path.join(self.directory.name, 'bin')
        os.makedirs(self.bin)
        self.gh_log = os.path.join(self.directory.name, 'gh.log')
        self.body_copy = os.path.join(self.directory.name, 'body-copy.md')
        gh = os.path.join(self.bin, 'gh')
        with open(gh, 'w') as handle:
            handle.write('#!/bin/sh\necho "$*" >> "$FAKE_GH_LOG"\nfor argument in "$@"; do case "$argument" in body=@*) cp "${argument#body=@}" "$FAKE_BODY_COPY" ;; esac; done\n')
        os.chmod(gh, 0o755)

    def tearDown(self):
        self.directory.cleanup()

    def environment_for_step(self, **extra):
        return {**self.environment, 'PATH': self.bin + os.pathsep + os.environ['PATH'], 'RUNNER_TEMP': self.runner_temp, 'GITHUB_SHA': self.main,
                'BATCH_BRANCH': 'security-updates/composer', 'REPOSITORY': 'owner/repository', 'FAKE_GH_LOG': self.gh_log, 'FAKE_BODY_COPY': self.body_copy, **extra}

    def write_body(self, planned, summary):
        with open(os.path.join(self.runner_temp, 'summary.txt'), 'w') as handle:
            handle.write(summary)
        return subprocess.run(['bash', '-eo', 'pipefail', '-c', self.body_step], cwd=self.root, capture_output=True, text=True, env=self.environment_for_step(
            PULL_REQUESTS=planned, BRIEF_INSTRUCTIONS='.github/security-update-brief-batch.md'))

    def test_only_the_pull_requests_that_were_folded_are_listed_as_replaced_and_the_skipped_ones_are_listed_as_not_folded(self):
        result = self.write_body('10 20 30', 'folded 10\nskipped 20: its head moved since the list was made\nfolded 30\n')
        self.assertEqual(0, result.returncode, result.stderr)
        body = read(os.path.join(self.root, 'body.md'))
        self.assertIn('- [PR #10](https://github.com/owner/repository/pull/10)', body)
        self.assertIn('- [PR #30](https://github.com/owner/repository/pull/30)', body)
        self.assertNotIn('[PR #20]', body)
        self.assertIn('Nesloženo:\n- skipped 20: its head moved since the list was made', body)

    def test_the_brief_comes_first_made_with_the_instructions_of_the_folded_pull_request_and_the_signature_is_still_pending(self):
        self.write_body('10', 'folded 10\n')
        body = read(os.path.join(self.root, 'body.md'))
        self.assertTrue(body.startswith('<!-- security-update-brief:start -->\nTHE BRIEF for .github/security-update-brief-batch.md'), body[:120])
        self.assertTrue(body.endswith('\n<!-- fold-dependabot-composer: pending -->\n'), body[-120:])
        self.assertNotIn('Nesloženo', body)

    def test_the_marker_survives_a_body_that_has_to_be_shortened(self):
        with open(self.stub, 'w') as handle:
            handle.write('#!/bin/sh\npython3 -I -c \'print("<!-- security-update-brief:start -->\\n" + "x" * 64950 + "\\n<!-- security-update-brief:end -->")\' > "$3"\n')
        self.assertEqual(0, self.write_body('10', 'folded 10\n').returncode)
        body = read(os.path.join(self.root, 'body.md'))
        self.assertTrue(body.endswith('\n<!-- fold-dependabot-composer: pending -->\n'))
        self.assertLessEqual(len(body), 65536)

    def test_the_signature_replaces_the_pending_marker_in_a_body_that_is_written_to_the_pull_request_that_was_opened(self):
        with open(os.path.join(self.root, 'body.md'), 'w') as handle:
            handle.write('<!-- fold-dependabot-composer: pending -->\ntext\n')
        result = subprocess.run(['bash', '-eo', 'pipefail', '-c', self.record_step], cwd=self.root, capture_output=True, text=True,
                                env=self.environment_for_step(NUMBER='42', SIGNATURE='main=abc 10@def 20@ghi'))
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual('<!-- fold-dependabot-composer: main=abc 10@def 20@ghi -->\ntext\n', read(self.body_copy))
        self.assertEqual('api --method PATCH repos/owner/repository/pulls/42 --field body=@body-final.md', read(self.gh_log).strip())

    def test_a_body_without_the_pending_marker_is_not_written_back_as_if_it_had_a_signature(self):
        with open(os.path.join(self.root, 'body.md'), 'w') as handle:
            handle.write('text without a marker\n')
        result = subprocess.run(['bash', '-eo', 'pipefail', '-c', self.record_step], cwd=self.root, capture_output=True, text=True,
                                env=self.environment_for_step(NUMBER='42', SIGNATURE='main=abc 10@def'))
        self.assertNotEqual(0, result.returncode)
        self.assertFalse(os.path.exists(self.gh_log))


class SuiteWiringTest(unittest.TestCase):
    def test_the_tests_run_in_ci_and_in_make_tests_inside_the_container(self):
        self.assertIn('make test-security-brief', read(os.path.join(ROOT, '.github', 'workflows', 'run-tests.yml')))
        makefile = read(os.path.join(ROOT, 'Makefile'))
        self.assertRegex(makefile, r'(?m)^tests: .*\btest-security-brief\b')
        self.assertIn('test-security-brief:\n\t./bin-docker/docker-bash bin/test-security-brief.sh', makefile)


class RunBlocksTest(unittest.TestCase):
    def test_multi_line_and_one_line_commands_are_found_and_other_keys_are_not(self):
        workflow = ('steps:\n    -   run: one-liner ${{ a }}\n    -   name: x\n        run: |\n            first\n\n            second\n'
                    '        env:\n            KEY: ${{ b }}\n    -   run: >\n            folded\n')
        self.assertEqual(['one-liner ${{ a }}', '            first\n\n            second', '            folded'], run_blocks(workflow))


if __name__ == '__main__':
    unittest.main()
