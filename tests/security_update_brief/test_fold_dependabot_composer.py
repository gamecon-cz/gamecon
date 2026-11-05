"""Runs the real bin/fold-dependabot-composer.sh on throwaway repositories, with stand-ins for gh, composer and php."""
import json
import os
import shutil
import subprocess
import sys
import tempfile
import unittest

sys.dont_write_bytecode = True
ROOT = os.path.normpath(os.path.join(os.path.dirname(__file__), '..', '..'))
SCRIPT = os.path.join(ROOT, 'bin', 'fold-dependabot-composer.sh')
BUILD_SCRIPT = os.path.join(ROOT, 'bin', 'fold-dependabot-composer-build.sh')

# one line per package, with filler between them: changes to different packages merge, changes to the same one conflict
LOCK_TEMPLATE = """{{
    "packages": [
        {{"name": "a/one", "version": "{one}"}},
        "filler 1",
        "filler 2",
        "filler 3",
        "filler 4",
        "filler 5",
        {{"name": "b/two", "version": "{two}"}},
        "filler 6",
        "filler 7",
        "filler 8",
        "filler 9",
        "filler 10",
        {{"name": "c/three", "version": "{three}"}}
    ]
}}
"""

FAKE_COMPOSER = r"""#!/usr/bin/env python3
import os, re, sys
if os.environ.get('FAKE_COMPOSER_FAIL'):
    sys.exit('composer failed')
packages = re.findall(r'"name": "([^"]+)", "version": "([^"]+)"', open('composer.lock').read())
for name, version in packages:
    os.makedirs('vendor/' + name, exist_ok=True)
    open('vendor/' + name + '/VERSION', 'w').write(version)
root = "array('name' => 'other/root')" if os.environ.get('FAKE_ROOT_FLIP') else "array('name' => 'gamecon/gamecon')"
versions = ', '.join("'%s' => '%s'" % package for package in packages)
open('vendor/composer/installed.php', 'w').write("<?php return array('root' => %s, 'versions' => array(%s));\n" % (root, versions))
if os.environ.get('FAKE_STRAY'):
    open('stray.txt', 'w').write('x')
if os.environ.get('FAKE_NOISE'):
    print('skipped 999: injected @someone')
if os.environ.get('FAKE_SPACE'):
    os.makedirs('vendor/sp ace', exist_ok=True)
    open('vendor/sp ace/file.txt', 'w').write('x')
"""


class FoldTest(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        base = self.directory.name
        self.origin = os.path.join(base, 'origin.git')
        self.root = os.path.join(base, 'work')
        self.fake_bin = os.path.join(base, 'fake-bin')
        os.makedirs(self.fake_bin)
        self.write(os.path.join(self.fake_bin, 'composer'), FAKE_COMPOSER, 0o755)
        self.php_log = os.path.join(base, 'php.log')
        self.write(os.path.join(self.fake_bin, 'php'), '#!/bin/sh\necho "$@" >> "$FAKE_PHP_LOG"\n[ -z "$FAKE_PHP_FAIL" ] || { echo "check failed" >&2; exit 1; }\n', 0o755)
        self.write(os.path.join(self.fake_bin, 'gh'), '#!/bin/sh\ncat "$FAKE_GH_JSON"\n', 0o755)
        self.gh_json = os.path.join(base, 'gh.json')
        subprocess.run(['git', 'init', '--quiet', '--bare', '-b', 'main', self.origin], check=True, env=self.environment())
        os.makedirs(os.path.join(self.root, 'bin'))
        shutil.copy2(SCRIPT, os.path.join(self.root, 'bin', 'fold-dependabot-composer.sh'))
        shutil.copy2(BUILD_SCRIPT, os.path.join(self.root, 'bin', 'fold-dependabot-composer-build.sh'))
        self.git('init', '--quiet', '-b', 'main')
        self.git('config', 'user.email', 'test@example.com')
        self.git('config', 'user.name', 'Test')
        self.git('config', 'commit.gpgsign', 'false')
        self.git('remote', 'add', 'origin', self.origin)
        self.lock('1.0.0', '1.0.0', '1.0.0')
        self.write(os.path.join(self.root, 'bin', 'check-vendor-matches-lock.php'), '<?php')
        self.write(os.path.join(self.root, 'bin', 'check-dockerignore-dev-vendor.php'), '<?php')
        self.write(os.path.join(self.root, 'vendor', 'composer', 'installed.php'),
                   "<?php return array('root' => array('name' => 'gamecon/gamecon'), 'versions' => array());\n")
        self.run_fake_composer()
        self.git('add', '-A')
        self.git('commit', '--quiet', '-m', 'main')
        self.git('push', '--quiet', 'origin', 'main')

    def tearDown(self):
        self.directory.cleanup()

    @staticmethod
    def environment():
        """The caller's environment without the variables that would aim git at another repository."""
        return {name: value for name, value in os.environ.items() if not name.startswith('GIT_')}

    @staticmethod
    def write(path, content, mode=None):
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, 'w') as handle:
            handle.write(content)
        if mode:
            os.chmod(path, mode)

    def git(self, *arguments):
        result = subprocess.run(['git', '-C', self.root, *arguments], capture_output=True, text=True, env=self.environment())
        self.assertEqual(0, result.returncode, result.stderr)
        return result.stdout.strip()

    def lock(self, one, two, three):
        self.write(os.path.join(self.root, 'composer.lock'), LOCK_TEMPLATE.format(one=one, two=two, three=three))

    def run_fake_composer(self):
        subprocess.run([os.path.join(self.fake_bin, 'composer')], cwd=self.root, check=True)

    def pull_request(self, number, one='1.0.0', two='1.0.0', three='1.0.0', extra_file=None):
        """A Dependabot-like branch off main, published as refs/pull/<number>/head; the checkout returns to main."""
        self.git('switch', '--quiet', '-c', 'dependabot/composer/pr-%d' % number, 'main')
        self.lock(one, two, three)
        if extra_file:
            self.write(os.path.join(self.root, extra_file), '{}')
        self.git('add', '-A')
        self.git('commit', '--quiet', '-m', 'Bump pr %d' % number)
        self.git('push', '--quiet', '--force', 'origin', 'HEAD:refs/pull/%d/head' % number)
        head = self.git('rev-parse', 'HEAD')
        self.git('switch', '--quiet', 'main')
        return head

    def pull_request_with_commits(self, number, locks):
        """A branch with one commit per (one, two, three) of `locks`, each on top of the one before."""
        self.git('switch', '--quiet', '-c', 'dependabot/composer/pr-%d' % number, 'main')
        for index, (one, two, three) in enumerate(locks):
            self.lock(one, two, three)
            self.git('add', '-A')
            self.git('commit', '--quiet', '-m', 'Bump pr %d part %d' % (number, index + 1))
        self.git('push', '--quiet', 'origin', 'HEAD:refs/pull/%d/head' % number)
        self.git('switch', '--quiet', 'main')

    def fold(self, *numbers, **extra_environment):
        environment = {**self.environment(), 'PATH': self.fake_bin + os.pathsep + os.environ['PATH'], 'DEV_IMAGE_RUN': 'env', 'FAKE_PHP_LOG': self.php_log,
                       'GIT_AUTHOR_NAME': 'Bot', 'GIT_AUTHOR_EMAIL': 'bot@example.com', 'GIT_COMMITTER_NAME': 'Bot', 'GIT_COMMITTER_EMAIL': 'bot@example.com',
                       **extra_environment}
        return subprocess.run(['bash', os.path.join(self.root, 'bin', 'fold-dependabot-composer.sh'), *numbers], cwd=self.root,
                              env=environment, capture_output=True, text=True)

    def subjects(self):
        return self.git('log', '--format=%s', 'origin/main..HEAD').split('\n')

    def vendor_version(self, name):
        with open(os.path.join(self.root, 'vendor', name, 'VERSION')) as handle:
            return handle.read()

    def test_the_lock_commits_are_taken_as_they_are_and_vendor_is_synced_in_one_more_commit(self):
        self.pull_request(101, one='1.0.1')
        self.pull_request(102, two='2.0.0')
        result = self.fold('101', '102')
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual(['Sync vendor/ with composer.lock', 'Bump pr 102', 'Bump pr 101'], self.subjects())
        self.assertEqual('1.0.1', self.vendor_version('a/one'))
        self.assertEqual('2.0.0', self.vendor_version('b/two'))
        self.assertEqual('', self.git('status', '--porcelain'))
        self.assertIn('folded 101', result.stdout)
        self.assertIn('folded 102', result.stdout)
        self.assertIn('synced vendor/ for 101 102', result.stdout)

    def test_both_checks_run_after_the_sync(self):
        self.pull_request(101, one='1.0.1')
        self.assertEqual(0, self.fold('101').returncode)
        with open(self.php_log) as handle:
            calls = handle.read().split('\n')
        self.assertEqual(['bin/check-vendor-matches-lock.php', 'bin/check-dockerignore-dev-vendor.php', ''], calls)

    def test_a_pull_request_whose_later_commit_conflicts_is_dropped_whole_not_half_applied(self):
        self.pull_request(101, one='1.0.1')
        # the first commit applies, the second conflicts with 101, the third would apply again
        self.pull_request_with_commits(102, [('1.0.0', '1.0.0', '3.0.0'), ('1.0.9', '1.0.0', '3.0.0'), ('1.0.9', '2.0.0', '3.0.0')])
        result = self.fold('101', '102')
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertIn('skipped 102: its lock commit does not apply to this branch', result.stdout)
        self.assertEqual(['Sync vendor/ with composer.lock', 'Bump pr 101'], self.subjects())
        self.assertEqual('1.0.0', self.vendor_version('c/three'))
        self.assertEqual('1.0.0', self.vendor_version('b/two'))

    def test_what_composer_prints_never_reaches_the_stdout_that_goes_into_a_pull_request(self):
        self.pull_request(101, one='1.0.1')
        result = self.fold('101', FAKE_NOISE='1')
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual('folded 101\nsynced vendor/ for 101\n', result.stdout)
        self.assertIn('skipped 999: injected', result.stderr)

    def test_a_file_with_a_space_in_its_name_inside_vendor_is_not_taken_for_one_outside_it(self):
        self.pull_request(101, one='1.0.1')
        result = self.fold('101', FAKE_SPACE='1')
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertIn('vendor/sp ace/file.txt', self.git('show', '--name-only', '--format=', 'HEAD'))

    def test_a_merge_commit_in_the_pull_request_from_update_branch_is_left_out_and_the_lock_commit_is_folded(self):
        self.git('switch', '--quiet', '-c', 'dependabot/composer/pr-101', 'main')
        self.lock('1.0.1', '1.0.0', '1.0.0')
        self.git('add', '-A')
        self.git('commit', '--quiet', '-m', 'Bump pr 101')
        self.git('switch', '--quiet', 'main')
        self.write(os.path.join(self.root, 'other.txt'), 'main moved')
        self.git('add', '-A')
        self.git('commit', '--quiet', '-m', 'main moved')
        self.git('push', '--quiet', 'origin', 'main')
        self.git('switch', '--quiet', 'dependabot/composer/pr-101')
        self.git('merge', '--quiet', '--no-ff', '-m', 'Merge main', 'main')
        self.git('push', '--quiet', 'origin', 'HEAD:refs/pull/101/head')
        self.git('switch', '--quiet', 'main')
        result = self.fold('101')
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual(['Sync vendor/ with composer.lock', 'Bump pr 101'], self.subjects())
        self.assertEqual('1.0.1', self.vendor_version('a/one'))

    def test_a_second_run_in_the_same_checkout_follows_a_pull_request_that_dependabot_force_pushed(self):
        self.pull_request(101, one='1.0.1')
        self.assertEqual(0, self.fold('101').returncode)
        self.git('reset', '--quiet', '--hard', 'origin/main')
        self.git('branch', '-D', 'dependabot/composer/pr-101')
        self.pull_request(101, one='1.0.2')
        result = self.fold('101')
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual('1.0.2', self.vendor_version('a/one'))

    def test_the_sync_commit_holds_only_vendor(self):
        self.pull_request(101, one='1.0.1')
        self.assertEqual(0, self.fold('101').returncode)
        self.assertEqual(['vendor/a/one/VERSION', 'vendor/composer/installed.php'], self.git('show', '--name-only', '--format=', 'HEAD').split('\n'))

    def test_a_pull_request_whose_change_is_already_on_the_branch_is_skipped_and_nothing_is_committed(self):
        self.pull_request(101, one='1.0.1')
        self.lock('1.0.1', '1.0.0', '1.0.0')
        self.run_fake_composer()
        self.git('add', '-A')
        self.git('commit', '--quiet', '-m', 'the same change by hand')
        self.git('push', '--quiet', 'origin', 'main')
        result = self.fold('101')
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertIn('skipped 101: already applied', result.stdout)
        self.assertIn('nothing to fold', result.stdout)
        self.assertEqual([''], self.subjects())

    def test_a_lock_commit_that_conflicts_is_skipped_and_the_others_are_still_folded(self):
        self.pull_request(101, one='1.0.1')
        self.pull_request(102, one='1.0.2')
        self.pull_request(103, three='3.0.0')
        result = self.fold('101', '102', '103')
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertIn('skipped 102: its lock commit does not apply to this branch', result.stdout)
        self.assertEqual(['Sync vendor/ with composer.lock', 'Bump pr 103', 'Bump pr 101'], self.subjects())
        self.assertEqual('1.0.1', self.vendor_version('a/one'))
        self.assertEqual('3.0.0', self.vendor_version('c/three'))
        self.assertEqual('', self.git('status', '--porcelain'))

    def test_a_pull_request_that_changes_more_than_the_lock_is_not_folded(self):
        self.pull_request(101, one='1.0.1', extra_file='composer.json')
        result = self.fold('101')
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertIn('skipped 101: it changes more than composer.lock', result.stdout)
        self.assertIn('nothing to fold', result.stdout)

    def test_a_failing_composer_install_stops_before_any_sync_commit(self):
        self.pull_request(101, one='1.0.1')
        result = self.fold('101', FAKE_COMPOSER_FAIL='1')
        self.assertNotEqual(0, result.returncode)
        self.assertEqual(['Bump pr 101'], self.subjects())

    def test_files_outside_vendor_changed_by_the_install_stop_it(self):
        self.pull_request(101, one='1.0.1')
        result = self.fold('101', FAKE_STRAY='1')
        self.assertNotEqual(0, result.returncode)
        self.assertIn('composer install changed files outside vendor/', result.stderr)
        self.assertIn('stray.txt', result.stderr)
        self.assertEqual(['Bump pr 101'], self.subjects())

    def test_a_changed_root_block_of_installed_php_stops_it(self):
        self.pull_request(101, one='1.0.1')
        result = self.fold('101', FAKE_ROOT_FLIP='1')
        self.assertNotEqual(0, result.returncode)
        self.assertIn('The root block of vendor/composer/installed.php changed', result.stderr)
        self.assertEqual(['Bump pr 101'], self.subjects())

    def test_a_failing_check_after_the_sync_fails_the_run(self):
        self.pull_request(101, one='1.0.1')
        result = self.fold('101', FAKE_PHP_FAIL='1')
        self.assertNotEqual(0, result.returncode)
        self.assertIn('check failed', result.stderr)

    def test_a_pull_request_listed_with_its_head_is_folded_while_it_has_that_head(self):
        head = self.pull_request(101, one='1.0.1')
        result = self.fold('101@' + head)
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertIn('folded 101', result.stdout)

    def test_a_pull_request_that_has_moved_on_since_the_list_was_made_is_skipped(self):
        self.pull_request(101, one='1.0.1')
        result = self.fold('101@' + '0' * 40)
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertIn('skipped 101: its head moved since the list was made', result.stdout)
        self.assertIn('nothing to fold', result.stdout)
        self.assertEqual([''], self.subjects())

    def run_build(self, heads):
        self.runner_temp = os.path.join(self.directory.name, 'runner-temp')
        os.makedirs(self.runner_temp, exist_ok=True)
        self.github_output = os.path.join(self.directory.name, 'github-output')
        open(self.github_output, 'w').close()
        environment = {**self.environment(), 'PATH': self.fake_bin + os.pathsep + os.environ['PATH'], 'DEV_IMAGE_RUN': 'env', 'FAKE_PHP_LOG': self.php_log,
                       'GIT_AUTHOR_NAME': 'Bot', 'GIT_AUTHOR_EMAIL': 'bot@example.com', 'GIT_COMMITTER_NAME': 'Bot', 'GIT_COMMITTER_EMAIL': 'bot@example.com',
                       'HEADS': heads, 'RUNNER_TEMP': self.runner_temp, 'GITHUB_SHA': self.git('rev-parse', 'HEAD'), 'GITHUB_OUTPUT': self.github_output}
        return subprocess.run(['bash', os.path.join(self.root, 'bin', 'fold-dependabot-composer-build.sh')], cwd=self.root, env=environment, capture_output=True, text=True)

    def test_the_build_step_makes_a_bundle_of_what_was_folded_and_says_so_in_its_output(self):
        head = self.pull_request(101, one='1.0.1')
        result = self.run_build('101@' + head)
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual('folded=true\n', open(self.github_output).read())
        bundle = os.path.join(self.runner_temp, 'fold.bundle')
        self.assertEqual(0, subprocess.run(['git', '-C', self.root, 'bundle', 'verify', bundle], capture_output=True, env=self.environment()).returncode)
        self.assertIn('folded 101', open(os.path.join(self.runner_temp, 'fold-summary.txt')).read())

    def test_the_build_step_makes_no_bundle_when_nothing_was_folded(self):
        self.pull_request(101, one='1.0.1')
        result = self.run_build('101@' + '0' * 40)
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual('folded=false\n', open(self.github_output).read())
        self.assertFalse(os.path.exists(os.path.join(self.runner_temp, 'fold.bundle')))

    def test_a_failing_fold_fails_the_build_step_although_tee_follows_it(self):
        head = self.pull_request(101, one='1.0.1')
        self.write(os.path.join(self.root, 'composer.lock'), 'dirty')
        result = self.run_build('101@' + head)
        self.assertNotEqual(0, result.returncode)
        self.assertEqual('', open(self.github_output).read())

    def test_a_dirty_tree_is_refused(self):
        self.pull_request(101, one='1.0.1')
        self.write(os.path.join(self.root, 'composer.lock'), 'changed')
        result = self.fold('101')
        self.assertEqual(1, result.returncode)
        self.assertIn('The working tree is not clean', result.stderr)

    def test_an_untracked_file_is_refused_too(self):
        self.pull_request(101, one='1.0.1')
        self.write(os.path.join(self.root, 'untracked.txt'), 'x')
        result = self.fold('101')
        self.assertEqual(1, result.returncode)
        self.assertIn('The working tree is not clean', result.stderr)

    def test_without_arguments_it_says_how_to_call_it(self):
        result = self.fold()
        self.assertNotEqual(0, result.returncode)
        self.assertIn('usage: bin/fold-dependabot-composer.sh', result.stderr)

    def test_the_list_has_the_open_plain_composer_bumps_in_number_order_and_says_why_it_leaves_one_out(self):
        pull_requests = [
            {'number': 20, 'headRefName': 'dependabot/composer/b', 'headRefOid': 'bbb', 'files': [{'path': 'composer.lock'}]},
            {'number': 10, 'headRefName': 'dependabot/composer/a', 'headRefOid': 'aaa', 'files': [{'path': 'composer.lock'}]},
            {'number': 30, 'headRefName': 'dependabot/composer/c', 'headRefOid': 'ccc', 'files': [{'path': 'composer.lock'}, {'path': 'composer.json'}]},
            {'number': 40, 'headRefName': 'dependabot/npm_and_yarn/ui/d', 'headRefOid': 'ddd', 'files': [{'path': 'ui/yarn.lock'}]},
            {'number': 50, 'headRefName': 'feature/e', 'headRefOid': 'eee', 'files': [{'path': 'composer.lock'}]},
        ]
        self.write(self.gh_json, json.dumps(pull_requests))
        result = self.fold('--list', GH=os.path.join(self.fake_bin, 'gh'), FAKE_GH_JSON=self.gh_json)
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual('10 aaa\n20 bbb\n', result.stdout)
        self.assertEqual('skipped 30: it changes composer.lock, composer.json, not only composer.lock\n', result.stderr)

    def test_an_empty_list_is_empty_output(self):
        self.write(self.gh_json, '[]')
        result = self.fold('--list', GH=os.path.join(self.fake_bin, 'gh'), FAKE_GH_JSON=self.gh_json)
        self.assertEqual((0, ''), (result.returncode, result.stdout))


if __name__ == '__main__':
    unittest.main()
