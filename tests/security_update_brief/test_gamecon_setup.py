"""Keeps the Gamecon-specific parts of the Dependabot brief honest: the instructions, the Makefile targets they name, the wrapper and the workflow."""
import importlib.util
import json
import os
import re
import shlex
import shutil
import subprocess
import sys
import tempfile
import unittest
from unittest import mock

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

    def test_they_point_to_the_local_script_that_folds_the_composer_pull_requests_and_it_exists(self):
        self.assertIn('`bin/fold-dependabot-composer.sh`', self.text)
        self.assertTrue(os.access(os.path.join(ROOT, 'bin', 'fold-dependabot-composer.sh'), os.X_OK))

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
        instructions = re.search(r'--instructions-file (\S+)', self.text).group(1)
        self.assertEqual(INSTRUCTIONS_PATH, os.path.join(ROOT, instructions))

    def test_the_scripts_are_executable(self):
        for name in ('security-update-brief.sh', 'test-security-brief.sh', 'fold-dependabot-composer.sh'):
            self.assertTrue(os.access(os.path.join(ROOT, 'bin', name), os.X_OK), name)

    def test_the_audit_directory_of_a_run_is_ignored_by_git(self):
        self.assertIn('/.security-update-brief-audit.*/', read(os.path.join(ROOT, '.gitignore')).splitlines())


class WrapperHelperChoiceTest(unittest.TestCase):
    """The wrapper of this tree, copied as it is into a project of its own, with stand-in tools in a PATH that holds nothing else, among them a python3
    that writes down the arguments of the script. No helper runs here: the tests read which composer command the wrapper hands to the script."""

    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.project = os.path.join(os.path.realpath(self.directory.name), 'project')
        os.makedirs(os.path.join(self.project, 'bin'))
        shutil.copy2(WRAPPER_PATH, os.path.join(self.project, 'bin', 'security-update-brief.sh'))
        self.tools = os.path.join(self.directory.name, 'tools')
        os.makedirs(self.tools)
        for name in ('dirname', 'git', 'mktemp', 'chmod', 'rmdir'):
            os.symlink(shutil.which(name), os.path.join(self.tools, name))
        self.recorded = os.path.join(self.directory.name, 'python-arguments')
        self.add_tool(os.path.join(self.tools, 'python3'),
                      'isolated=""\nif [ "$1" = "-I" ]; then shift; isolated="-I"; fi\n'
                      'if [ "$1" = "-c" ]; then\n  case "$2" in *shlex*) exec "%s" $isolated "$@" ;; esac\n  exit 0\nfi\n'
                      'printf "%%s\\n" "$@" > "%s"\n' % (sys.executable, self.recorded))
        self.add_tool(os.path.join(self.tools, 'docker'), 'exit 0\n')
        self.add_tool(os.path.join(self.tools, 'composer'), 'exit 0\n')

    def tearDown(self):
        self.directory.cleanup()

    @staticmethod
    def add_tool(path, body):
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, 'w') as handle:
            handle.write('#!/bin/sh\n' + body)
        os.chmod(path, 0o755)

    def composer_command(self, *helpers):
        """The composer command the wrapper passes to the script (None when it passes none) in a project that has exactly these executables, docker being installed."""
        for helper in helpers:
            self.add_tool(os.path.join(self.project, helper), 'exit 0\n')
        try:
            result = subprocess.run([shutil.which('bash'), os.path.join(self.project, 'bin', 'security-update-brief.sh'), 'base-sha', 'head-sha', 'out.md'],
                                    env={'PATH': self.tools}, capture_output=True, text=True)
        finally:
            for helper in helpers:
                os.remove(os.path.join(self.project, helper))
        self.assertEqual(0, result.returncode, result.stderr)
        arguments = read(self.recorded).splitlines()
        return shlex.split(arguments[arguments.index('--composer-command') + 1]) if '--composer-command' in arguments else None

    def test_with_bin_composer_and_docker_bin_composer_beside_it_the_helper_is_bin_docker_composer_alone(self):
        self.assertEqual([os.path.join(self.project, 'bin-docker', 'composer')],
                         self.composer_command('bin/composer', 'docker-bin/composer', 'bin-docker/composer'))

    def test_a_bin_composer_or_a_docker_bin_composer_without_bin_docker_composer_is_no_helper_and_the_composer_on_path_is_used(self):
        for helpers in (('bin/composer',), ('docker-bin/composer',), ('bin/composer', 'docker-bin/composer')):
            self.assertIsNone(self.composer_command(*helpers), helpers)

    def test_bin_docker_composer_alone_is_the_helper(self):
        self.assertEqual([os.path.join(self.project, 'bin-docker', 'composer')], self.composer_command('bin-docker/composer'))


class WrapperChainTest(unittest.TestCase):
    """The wrapper, the script and bin-docker/composer of this tree (with its _tty.sh), copied as they are into a git project of their own and run together
    over a lock change. Only the tools around them are stand-ins: a python3 that is the real one, and a `docker` that says `web` is up and answers `compose exec`
    the way composer would in the directory of the container that the helper names, by looking in the matching directory of the project."""

    CONTAINER_DIRECTORY = '/var/www/html/gamecon'
    ADVISORIES = ('{"advisories": {"a/b": [{"advisoryId": "PKSA-1", "sources": [{"name": "GitHub", "remoteId": "GHSA-1"}], "severity": "high", "title": "T",'
                  ' "affectedVersions": "<1.0.1", "link": "https://x"}]}}')
    DOCKER = ('#!/bin/sh\n'
              'case "$1 $2" in\n'
              '"compose ps") echo "web running Up"; exit 0 ;;\n'
              '"compose exec")\n'
              '  workdir=""\n'
              '  for argument in "$@"; do case "$argument" in --workdir=*) workdir="${argument#--workdir=}" ;; esac; done\n'
              '  printf "%s\\t%s\\n" "$PWD" "$workdir" >> "__LOG__"\n'
              '  cd "__PROJECT__${workdir#__CONTAINER__}" || exit 1\n'
              '  if grep -q \'"old"\' composer.lock; then echo \'__ADVISORIES__\'; exit 1; fi\n'
              '  echo \'{"advisories": []}\' ;;\n'
              'esac\n')

    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.project = os.path.join(os.path.realpath(self.directory.name), 'project')
        for relative in ('bin/security-update-brief.sh', 'bin-docker/composer', 'bin-docker/_tty.sh', '.github/scripts/security-update-brief.py'):
            os.makedirs(os.path.dirname(os.path.join(self.project, relative)), exist_ok=True)
            shutil.copy2(os.path.join(ROOT, relative), os.path.join(self.project, relative))
        with open(os.path.join(self.project, '.github', 'security-update-brief.md'), 'w') as handle:
            handle.write('<details>\n<summary>Our steps</summary>\n\nRun it.\n</details>\n')
        self.log = os.path.join(self.directory.name, 'docker.log')
        self.home = os.path.join(self.directory.name, 'home')
        os.makedirs(self.home)
        self.tools = os.path.join(self.directory.name, 'tools')
        os.makedirs(self.tools)
        for name in ('dirname', 'mkdir', 'rmdir', 'git', 'grep', 'mktemp', 'chmod', 'env', 'bash'):
            os.symlink(shutil.which(name), os.path.join(self.tools, name))
        os.symlink(sys.executable, os.path.join(self.tools, 'python3'))
        docker = os.path.join(self.tools, 'docker')
        with open(docker, 'w') as handle:
            handle.write(self.DOCKER.replace('__LOG__', self.log).replace('__PROJECT__', self.project)
                         .replace('__CONTAINER__', self.CONTAINER_DIRECTORY).replace('__ADVISORIES__', self.ADVISORIES))
        os.chmod(docker, 0o755)
        environment = {name: value for name, value in os.environ.items() if not name.startswith('GIT_')}
        git = ['git', '-c', 'user.email=t@example.com', '-c', 'user.name=T', '-c', 'commit.gpgsign=false', '-C', self.project]
        subprocess.run([*git, 'init', '--quiet', '-b', 'main'], check=True, env=environment)
        for version, reference in (('1.0.0', 'old'), ('1.0.1', 'new')):
            lock = {'packages': [{'name': 'a/b', 'version': version, 'source': {'type': 'git', 'url': 'https://github.com/a/b.git', 'reference': reference}}]}
            for name, content in (('composer.lock', json.dumps(lock)), ('composer.json', '{}')):
                with open(os.path.join(self.project, name), 'w') as handle:
                    handle.write(content)
            subprocess.run([*git, 'add', '-A'], check=True, env=environment)
            subprocess.run([*git, 'commit', '--quiet', '-m', 'commit'], check=True, env=environment)

    def tearDown(self):
        self.directory.cleanup()

    def audit_directories(self):
        return sorted(name for name in os.listdir(self.project) if name.startswith('.security-update-brief-audit.'))

    def test_the_lookup_of_each_lock_runs_composer_in_the_directory_of_the_container_that_holds_that_lock_inside_the_audit_directory_the_wrapper_made(self):
        output = os.path.join(self.directory.name, 'brief.md')
        result = subprocess.run([shutil.which('bash'), os.path.join(self.project, 'bin', 'security-update-brief.sh'), 'HEAD~1', 'HEAD', output],
                                env={'PATH': self.tools, 'HOME': self.home}, capture_output=True, text=True)
        self.assertEqual(0, result.returncode, result.stderr)
        calls = [line.split('\t') for line in read(self.log).splitlines()]
        self.assertEqual(2, len(calls), calls)
        for host_directory, container_directory in calls:
            audit_directory, temporary_directory = os.path.relpath(host_directory, self.project).split(os.sep)
            self.assertRegex(audit_directory, r'^\.security-update-brief-audit\.[A-Za-z0-9]{6}$')
            self.assertEqual('%s/%s/%s' % (self.CONTAINER_DIRECTORY, audit_directory, temporary_directory), container_directory)
        self.assertEqual(1, len({os.path.dirname(host_directory) for host_directory, _ in calls}))
        text = read(output)
        self.assertIn('"fixes":["GHSA-1"]', text)
        self.assertIn('Advisories remaining after the update: 0', text)
        self.assertIn('<summary>Our steps</summary>', text)
        self.assertEqual([], self.audit_directories())


class ComposerHelperTest(unittest.TestCase):
    """bin-docker/composer in a copy of the project's bin-docker, with a stand-in `docker` that writes down its arguments."""

    CONTAINER_DIRECTORY = '/var/www/html/gamecon'

    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.home = os.path.join(self.directory.name, 'home')
        os.makedirs(self.home)
        fake_bin = os.path.join(self.directory.name, 'fake-bin')
        os.makedirs(fake_bin)
        self.log = os.path.join(self.directory.name, 'docker.log')
        docker = os.path.join(fake_bin, 'docker')
        with open(docker, 'w') as handle:
            handle.write('#!/bin/sh\nprintf "%%s\\t" "$@" >> "%s"\nprintf "\\n" >> "%s"\n'
                         'if [ "$1 $2" = "compose ps" ] && [ -n "${WEB_IS_UP:-}" ]; then echo "web running Up 3 minutes"; fi\n' % (self.log, self.log))
        os.chmod(docker, 0o755)
        self.path = fake_bin + os.pathsep + os.environ['PATH']

    def tearDown(self):
        self.directory.cleanup()

    def install_project(self, name):
        project = os.path.join(os.path.realpath(self.directory.name), name)
        os.makedirs(os.path.join(project, 'bin-docker'))
        for helper in ('composer', '_tty.sh'):
            shutil.copy2(os.path.join(ROOT, 'bin-docker', helper), os.path.join(project, 'bin-docker', helper))
        return project

    def install_linked_project(self):
        """A project and a symbolic link to it: the paths by which a checkout, and a working directory in it, can be reached."""
        project = self.install_project('project')
        link = os.path.join(self.directory.name, 'link')
        os.symlink(project, link)
        return project, link

    def exec_call(self, project, working_directory, *arguments, running=False):
        """The arguments of the `docker compose exec` that the helper made when called from working_directory, with the web service up or not."""
        os.makedirs(working_directory, exist_ok=True)
        environment = {'PATH': self.path, 'HOME': self.home, 'PWD': working_directory, **({'WEB_IS_UP': '1'} if running else {})}
        result = subprocess.run(['bash', os.path.join(project, 'bin-docker', 'composer'), *arguments], cwd=working_directory, env=environment,
                                capture_output=True, text=True)
        self.assertEqual(0, result.returncode, result.stderr)
        calls = [line.split('\t')[:-1] for line in read(self.log).splitlines()]
        os.remove(self.log)
        return [call for call in calls if call[:2] == ['compose', 'exec']][0]

    def test_composer_runs_in_the_directory_the_helper_was_called_from_so_that_a_lock_copied_inside_the_checkout_is_the_one_audited(self):
        project = self.install_project('project')
        for running in (False, True):
            call = self.exec_call(project, os.path.join(project, '.security-update-brief-audit.abc123', 'tmpxyz'), 'audit', '--locked', running=running)
            self.assertIn('--workdir=%s/.security-update-brief-audit.abc123/tmpxyz' % self.CONTAINER_DIRECTORY, call, running)
            self.assertEqual(['web', 'composer', 'audit', '--locked'], call[-4:], running)

    def test_from_the_root_of_the_project_composer_runs_in_the_root_of_the_container(self):
        project = self.install_project('project')
        for running in (False, True):
            self.assertIn('--workdir=%s' % self.CONTAINER_DIRECTORY, self.exec_call(project, project, 'install', running=running), running)

    def test_a_directory_outside_the_project_does_not_leak_into_the_path_in_the_container(self):
        project, link = self.install_linked_project()
        outside = os.path.join(self.directory.name, 'elsewhere', 'deeper')
        for helper_root in (project, link):
            for running in (False, True):
                self.assertIn('--workdir=%s' % self.CONTAINER_DIRECTORY, self.exec_call(helper_root, outside, 'install', running=running), (helper_root, running))

    def test_a_symlinked_checkout_maps_the_directory_whichever_path_the_helper_and_the_working_directory_are_reached_by(self):
        project, link = self.install_linked_project()
        for helper_root in (project, link):
            for directory_root in (project, link):
                for running in (False, True):
                    call = self.exec_call(helper_root, os.path.join(directory_root, 'sub', 'dir'), 'audit', running=running)
                    self.assertIn('--workdir=%s/sub/dir' % self.CONTAINER_DIRECTORY, call, (helper_root, directory_root, running))

    def test_the_root_of_a_symlinked_checkout_is_the_root_of_the_container_whichever_path_reaches_it(self):
        project, link = self.install_linked_project()
        for helper_root in (project, link):
            for directory_root in (project, link):
                self.assertIn('--workdir=%s' % self.CONTAINER_DIRECTORY, self.exec_call(helper_root, directory_root, 'install'), (helper_root, directory_root))

    def test_a_project_whose_path_holds_a_space_still_maps_the_directory(self):
        project = self.install_project('my project')
        for running in (False, True):
            call = self.exec_call(project, os.path.join(project, 'sub dir'), 'install', running=running)
            self.assertIn('--workdir=%s/sub dir' % self.CONTAINER_DIRECTORY, call, running)

    def test_composer_still_runs_as_the_web_user_of_the_container(self):
        project = self.install_project('project')
        for running in (False, True):
            self.assertIn('--user=www-data', self.exec_call(project, project, 'install', running=running), running)


class ScriptCopyTest(unittest.TestCase):
    def test_the_script_is_a_copy_that_says_which_version_of_the_shared_script_it_is(self):
        self.assertRegex(read(os.path.join(ROOT, '.github', 'scripts', 'security-update-brief.py')), r'(?m)^Version: \d+\.\d+\.\d+')


class WrapperRunTest(unittest.TestCase):
    """Runs the real wrapper on a throwaway repository, with a stand-in `composer` that finds no advisories."""

    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.root = os.path.join(self.directory.name, 'repository')
        for relative in ('bin/security-update-brief.sh', '.github/scripts/security-update-brief.py', '.github/security-update-brief.md'):
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
        """The caller's environment without the variables that would aim git at another repository or replace the composer of the run."""
        return {name: value for name, value in os.environ.items() if not name.startswith('GIT_') and name != 'SECURITY_BRIEF_COMPOSER'}

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

    def test_the_workflow_starts_only_for_the_lockfiles_that_the_script_writes_a_brief_for(self):
        section = re.search(r"(?m)^\s+paths:\n((?:\s+- ['\"]\*\*/[^'\"]+['\"]\n)+)", self.text)
        self.assertIsNotNone(section)
        names = re.findall(r"['\"]\*\*/([^'\"/]+)['\"]", section.group(1))
        self.assertEqual(['composer.lock', 'yarn.lock'], names)
        changed = ['composer.lock', 'ui/yarn.lock', 'admin/composer.lock', 'package-lock.json', 'ui/pnpm-lock.yaml', 'composer.json', 'ui/package.json', 'README.md']
        with mock.patch.object(brief, 'git', return_value='\n'.join(changed)):
            handled = brief.changed_lock_paths('.', 'base', 'head')
        self.assertEqual([path for path in changed if os.path.basename(path) in names], handled)

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
