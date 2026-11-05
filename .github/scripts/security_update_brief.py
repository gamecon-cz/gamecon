#!/usr/bin/env python3
"""Write a review brief for a Dependabot security-update pull request and put it at the top of the body.

The brief lists every package a lockfile change touches (versions, source repository, what requires it,
what it now requires, where we use it), the advisories it fixes and the ones left, as tables and JSON
lines, plus the steps for taking the update: judge each change, add missing tests first, update, merge.

    security_update_brief.py brief  --repository DIR --base-sha SHA --head-sha SHA --output brief.md
                                    [--scan-directories DIR ...] [--instructions-file FILE]
    security_update_brief.py splice --body-file body.md --brief-file brief.md --output new-body.md

Both write the output file only when there is something to write. Files of the pull request are only read.
"""
import argparse
import json
import os
import re
import stat
import subprocess
import sys
import tempfile
import urllib.parse
import urllib.request
from dataclasses import dataclass, field

BRIEF_START = '<!-- security-update-brief:start -->'
BRIEF_END = '<!-- security-update-brief:end -->'
BODY_LIMIT = 65000
ORIGINAL_TEXT_RESERVE = 4000
NPM_BULK_ADVISORY_URL = 'https://registry.npmjs.org/-/npm/v1/security/advisories/bulk'
NPM_BULK_CHUNK = 400
COMPOSER_TIMEOUT = 120
SHOWN_BIG_JUMPS = 15
SHORTENED_NOTE = '_The rest of the original text was shortened._'
CUT_NOTE = '_Cut at the size limit of a pull request body; the lockfile diff has the rest._'
FENCE_LINE = re.compile(r'^(?:[ \t]*>)*[ \t]*(?:(?:[-*+]|\d+[.)])[ \t]+)?(`{3,}|~{3,})(.*)$')
INLINE_CODE = re.compile(r'`[^`]*`')
DETAILS_OPEN = re.compile(r'<details(?=[\s>/]|$)', re.IGNORECASE)
DETAILS_CLOSE = re.compile(r'</details\s*>', re.IGNORECASE)
STABLE_VERSION = re.compile(r'v?\d+(?:\.\d+){0,3}(?:\+[0-9A-Za-z.-]+|-(?:p|patch)\d*)?')
FENCE_OPEN = '\n```jsonl\n'
MAX_TABLE_ROWS = 60
MAX_LISTED_CHANGES = 6
SCAN_DIRECTORIES = ('src', 'config', 'templates', 'tests', 'features', 'packages', 'themes', 'assets', 'adminUi', 'bin')
SCAN_EXTENSIONS = {'php': ('.php', '.yaml', '.yml', '.xml', '.twig', '.neon', '.dist'), 'js': ('.js', '.jsx', '.ts', '.tsx', '.mts', '.cts', '.svelte', '.mjs', '.cjs')}
MAX_INSTRUCTIONS = 8000
VENDORED_DIRECTORIES = ('vendor', 'node_modules')
SKIPPED_DIRECTORIES = {'node_modules', 'vendor', 'var', '.svelte-kit', '.yarn', 'build', '.git'}
MAX_SCANNED_FILE = 1000000
SAFE_URL = re.compile(r'https://[A-Za-z0-9._~:/?#@!$&*+,;=%-]+')
MAX_LINK_LENGTH = 300  # a link address is repeated in every row of a table that the size budget never shrinks
TRUSTED_REPOSITORY_HOSTS = ('github.com', 'gitlab.com', 'bitbucket.org')  # a package name looks like a trusted reference, so its link may not lead anywhere else
GITHUB_ADVISORY = re.compile(r'GHSA(-[a-z0-9]{4}){3}')
NPM_NAME = re.compile(r'(@[a-z0-9~-][a-z0-9._~-]*/)?[a-z0-9~-][a-z0-9._~-]*')  # no `.` or `..` segment that could climb out of /package/

REVIEW_INSTRUCTIONS = """\
<details>
<summary>How to take this update (instructions for Claude)</summary>

This pull request raises locked dependencies to fix security advisories. Take it in this order: decide for each package whether the update can change something our application relies on and whether a test would notice, add the missing tests first, then update, run the e2e specs, wait for green and merge.

1. Read the tables and the `jsonl` blocks below, one JSON object per line. A package with `fixes` is the reason for this pull request; one without was pulled in by the resolver, and for PHP `required_by` says by whom. Look hardest at:
   - `"jump": "major"` and `"minor"`; `"deduplicated"` and `"duplicated"` mean the lockfile now holds fewer or more versions of the package (whoever used the dropped version now gets `to`); `"reference"` is the same version from a newer commit.
   - `patches`: a composer-patches patch on that package, which has to still apply to the new version.
   - `requires`: what the new version needs that the old one did not (including the PHP version), or no longer needs.
   - `constraint` and `constraint_was`: the `composer.json` range of a direct dependency, and the one before this pull request.
   - Lines `{"companions":[[package, from, to, jump], ...]}` hold the resolver-only packages of a large update in short form.
2. Fetch the change between the two versions.
   - PHP: `repository` with `from_ref` and `to_ref` (GitHub: `<repository>/compare/<from_ref>...<to_ref>`); clone it and `git diff <from_ref> <to_ref>`, and read the CHANGELOG, UPGRADE files and release notes in between. `composer show <package>` and `composer why <package>` give more.
   - JS: `npm diff --diff=<package>@<from> --diff=<package>@<to>`; read the package's changelog.
3. See what we use. `used_in` counts the files per directory (`src`, `config`, `tests`, … for PHP; for JS the project directory and the first directory below it, such as `adminUi/src`) that mention the package's `namespaces` (PHP) or its name in quotes, with or without a subpath (JS). It is a grep hint, and a missing `tests` entry does not prove a gap: Behat and Playwright tests reach code indirectly. Search for the classes, services, config keys, Twig functions and imports the diff touches yourself.
4. Give each package one verdict.
   - `NOT_AFFECTED`: nothing we use has changed.
   - `AFFECTED_AND_COVERED`: we use changed behaviour; name the test that would fail.
   - `AFFECTED_NOT_COVERED`: we use changed behaviour and no test would notice.
5. For every `AFFECTED_NOT_COVERED`, write the missing test first, in a separate pull request off `main`; it must pass on the old versions. Merge that, then update this pull request (`@dependabot rebase`).
6. If the repository has Playwright e2e specs (`tests/playwright`, run with `make e2e-shop` and `make e2e-admin`; use the repository's own names otherwise), note that `Run Tests` does not run them, and that `make e2e-*` does not install anything: it runs whatever is installed. On the updated pull request branch first install what the update changed (`make init`: composer install, yarn install and build; `make e2e-install` for the e2e project's own packages), then run every spec set that a changed part maps to: shop and admin for a PHP update (API Platform and Sylius serve the admin UI too), admin for a change under `/adminUi`, shop for one under `/` (the storefront assets), both for one under `/tests/playwright`. A pull request that touches several parts needs all of them. Read the result before merging.
7. Wait for a green `Run Tests` on this pull request, then merge it. A green run only shows that the existing tests pass; it says nothing about behaviour no test covers.
</details>"""


@dataclass
class LockedPackage:
    name: str
    version: str
    repository: str | None = None
    reference: str | None = None
    dev: bool | None = None
    released: str | None = None
    kind: str | None = None
    requires: dict[str, str] = field(default_factory=dict)
    namespaces: list[str] = field(default_factory=list)
    classes_only: bool = False


@dataclass
class Advisory:
    advisory_id: str
    package: str
    severity: str
    title: str
    affected: str
    url: str
    cve: str | None = None


@dataclass
class Lock:
    path: str
    ecosystem: str
    base: dict[str, list[LockedPackage]]
    head: dict[str, list[LockedPackage]]
    direct_names: set[str] = field(default_factory=set)
    requirements: dict[str, str] = field(default_factory=dict)
    base_requirements: dict[str, str] = field(default_factory=dict)
    patches: dict[str, list[str]] = field(default_factory=dict)
    usage: dict[str, dict[str, int]] = field(default_factory=dict)


def split_yarn_specifier(specifier):
    """'@babel/core@^7.0.0' -> '@babel/core'; the scope's leading @ is not the separator."""
    separator = specifier.index('@', 1) if specifier.startswith('@') else specifier.index('@')
    return specifier[:separator]


def package_name_of(specifier):
    """Like split_yarn_specifier, but an alias 'foo@npm:bar@^1' names 'bar', the package that is really installed."""
    if '@npm:' in specifier:
        target = specifier.split('@npm:', 1)[1]
        if '@' in target[1:]:
            return split_yarn_specifier(target)
    return split_yarn_specifier(specifier)


def parse_composer_lock(text):
    lock = json.loads(text)
    packages = {}
    for section, is_dev in (('packages', False), ('packages-dev', True)):
        for entry in lock.get(section) or []:
            source = entry.get('source') if isinstance(entry.get('source'), dict) else {}
            repository = source.get('url') or ''
            if repository.endswith('.git'):
                repository = repository[:-4]
            requires = entry.get('require') if isinstance(entry.get('require'), dict) else {}
            autoload = entry.get('autoload') if isinstance(entry.get('autoload'), dict) else {}
            prefixes = sorted({prefix for style in ('psr-4', 'psr-0') if isinstance(autoload.get(style), dict) for prefix in autoload[style] if prefix})
            packages.setdefault(entry['name'], []).append(LockedPackage(
                name=entry['name'], version=entry['version'], repository=repository or None,
                reference=source.get('reference'), dev=is_dev, released=str(entry.get('time') or '')[:10] or None,
                kind=entry.get('type'), requires={name: str(constraint) for name, constraint in requires.items()},
                namespaces=prefixes, classes_only=not prefixes and any(autoload.get(style) for style in ('classmap', 'files', 'psr-4', 'psr-0')),
            ))
    return packages


def parse_yarn_lock(text):
    """Read yarn 1 (`version "1.2.3"`) and yarn berry (`version: 1.2.3`) lockfiles."""
    packages = {}
    for block in re.split(r'\n\s*\n', text):
        lines = [line for line in block.splitlines() if line and not line.startswith('#')]
        if not lines or lines[0].startswith(' ') or not lines[0].rstrip().endswith(':'):
            continue
        header = lines[0].rstrip()[:-1]
        version = resolution = None
        for line in lines[1:]:
            version_match = re.match(r'^  version:? "?([^"\s]+)"?\s*$', line)
            resolution_match = re.match(r'^  resolution:? "?([^"]+)"?\s*$', line)
            version = version_match.group(1) if version_match else version
            resolution = resolution_match.group(1) if resolution_match else resolution
        if version is None or (resolution and '@workspace:' in resolution):
            continue
        specifiers = [specifier.strip().strip('"') for specifier in header.split(',')]
        for name in {package_name_of(specifier) for specifier in specifiers if '@' in specifier[1:]}:
            packages.setdefault(name, []).append(LockedPackage(name=name, version=version))
    return packages


def requirements_of(manifest, ecosystem):
    """name -> version range of every direct dependency in a composer.json or package.json."""
    sections = ('require', 'require-dev') if ecosystem == 'php' else ('dependencies', 'devDependencies')
    requirements = {}
    for section in sections:
        listed = manifest.get(section) if isinstance(manifest, dict) else None
        for name, requirement in (listed.items() if isinstance(listed, dict) else ()):
            requirements[name] = requirement if isinstance(requirement, str) else ''
            if isinstance(requirement, str) and requirement.startswith('npm:') and '@' in requirement[5:]:
                requirements[split_yarn_specifier(requirement[4:])] = requirement  # an alias is listed under its own name, installed as another
    return requirements


def composer_patches(manifest):
    """package -> patch files, from the cweagans/composer-patches section of composer.json (v1 and v2 shapes)."""
    extra = manifest.get('extra') if isinstance(manifest, dict) else None
    listed = extra.get('patches') if isinstance(extra, dict) else None
    patches = {}
    for name, entries in (listed.items() if isinstance(listed, dict) else ()):
        items = list(entries.values()) if isinstance(entries, dict) else list(entries) if isinstance(entries, list) else []
        files = [item.get('url') if isinstance(item, dict) else item for item in items]
        patches[name] = [str(patch) for patch in files if isinstance(patch, str)]
    return patches


def version_parts(version):
    match = re.match(r'v?(\d{1,18})(?:\.(\d{1,18}))?(?:\.(\d{1,18}))?(?:\.(\d{1,18}))?', version)
    if not match:
        return None
    return tuple(int(part) if part else 0 for part in match.groups())


def jump_between(old_versions, new_versions):
    if not old_versions or not new_versions:
        return 'other'
    if old_versions[-1] == new_versions[-1]:
        return 'reference'
    old_parts, new_parts = version_parts(old_versions[-1]), version_parts(new_versions[-1])
    if old_parts is None or new_parts is None or old_parts == new_parts:
        return 'other'
    if new_parts < old_parts:
        return 'downgrade'
    for level, name in enumerate(('major', 'minor', 'patch', 'patch')):
        if old_parts[level] != new_parts[level]:
            return name
    return 'other'


def locked_entries(packages):
    """(version, reference) pairs, so a branch package whose commit moved counts as changed."""
    return {(package.version, package.reference) for package in packages}


def natural_key(text):
    """Digit runs compare as numbers, so rc.2 sorts before rc.10."""
    return [(0, int(chunk), '') if re.fullmatch('[0-9]+', chunk) else (1, 0, chunk) for chunk in re.split('([0-9]{1,18})', text) if chunk]


def version_order(version):
    """Numbers first; a pre-release sorts before the stable release of the same numbers, build and patch suffixes do not make one."""
    return version_parts(version) or (0,), STABLE_VERSION.fullmatch(version) is not None, natural_key(version), version


def ordered_versions(entries):
    return sorted({version for version, _ in entries}, key=version_order)


def ordered_entries(entries):
    return sorted(entries, key=lambda entry: (version_order(entry[0]), entry[1] or ''))


def one_or_list(versions):
    return versions[0] if len(versions) == 1 else versions or None


def version_text(version):
    if version is None:
        return '—'
    return '/'.join(version) if isinstance(version, list) else str(version)


def plain_text(text, limit=300):
    """Tool output reduced to one line that cannot open markup, a mention or our markers."""
    flattened = ' '.join(str(text).split()).replace('`', "'").replace('<', '').replace('>', '').replace('@', '(at)')
    return flattened[:limit]


def code_span(text, limit=300):
    """Text shown verbatim in an inline code span, where markdown, mentions and issue references do not apply."""
    return '`%s`' % ' '.join(str(text).split()).replace('`', "'").replace('<', '').replace('>', '')[:limit]


def write_audit_project(directory, repository_directory, base_sha, lock_path_directory, lock_text):
    """A composer project that holds only the lock and the audit settings of the base branch, never the pull request's composer.json."""
    base_json = file_at(repository_directory, base_sha, os.path.join(lock_path_directory, 'composer.json'))
    audit_settings = ((json.loads(base_json).get('config') or {}).get('audit') if base_json else None) or {}
    with open(os.path.join(directory, 'composer.json'), 'w') as manifest_file:
        json.dump({'config': {'audit': audit_settings}} if audit_settings else {}, manifest_file)
    with open(os.path.join(directory, 'composer.lock'), 'w') as lock_file:
        lock_file.write(lock_text)


def composer_advisories(project_directory):
    result = subprocess.run(
        ['composer', 'audit', '--locked', '--format=json', '--no-plugins', '--no-scripts', '--no-interaction'],
        cwd=project_directory, capture_output=True, text=True, timeout=COMPOSER_TIMEOUT,
    )
    try:
        audit = json.loads(result.stdout)
    except json.JSONDecodeError:
        raise RuntimeError('composer audit gave no JSON: ' + (result.stderr or result.stdout)[:300])
    advisories = []
    for package, items in (audit.get('advisories') or {}).items():
        for item in items:
            github_ids = [source['remoteId'] for source in item.get('sources', []) if source.get('name') == 'GitHub']
            advisories.append(Advisory(
                advisory_id=github_ids[0] if github_ids else item['advisoryId'], package=package,
                severity=item.get('severity') or 'unknown', title=item.get('title', ''),
                affected=item.get('affectedVersions', ''), url=item.get('link') or '', cve=item.get('cve'),
            ))
    return advisories


def npm_advisories(versions_by_package):
    advisories = []
    names = sorted(versions_by_package)
    for start in range(0, len(names), NPM_BULK_CHUNK):
        chunk = {name: versions_by_package[name] for name in names[start:start + NPM_BULK_CHUNK]}
        request = urllib.request.Request(
            NPM_BULK_ADVISORY_URL, data=json.dumps(chunk).encode(), headers={'Content-Type': 'application/json'},
        )
        with urllib.request.urlopen(request, timeout=60) as response:
            payload = json.load(response)
        for package, items in payload.items():
            for item in items:
                url = item.get('url') or ''
                advisories.append(Advisory(
                    advisory_id=url.rstrip('/').split('/')[-1] or str(item.get('id')), package=package,
                    severity=item.get('severity') or 'unknown', title=item.get('title', ''),
                    affected=item.get('vulnerable_versions', ''), url=url,
                ))
    return advisories


def package_at(packages, version, reference):
    return next((package for package in packages if (package.version, package.reference) == (version, reference)), None)


def compare_url(repository, old_reference, new_reference):
    if repository and repository.startswith('https://github.com/') and old_reference and new_reference:
        return '%s/compare/%s...%s' % (repository, old_reference, new_reference)
    return None


def requirement_changes(old_requires, new_requires):
    """What a package version now requires that the one before did not, no longer requires, or requires in another range."""
    added = {name: new_requires[name] for name in sorted(new_requires) if name not in old_requires}
    removed = sorted(name for name in old_requires if name not in new_requires)
    changed = {name: [old_requires[name], new_requires[name]] for name in sorted(new_requires)
               if name in old_requires and old_requires[name] != new_requires[name]}
    changes = {}
    for key, value in (('added', added), ('removed', removed), ('changed', changed)):
        if value:
            shown = dict(list(value.items())[:MAX_LISTED_CHANGES]) if isinstance(value, dict) else value[:MAX_LISTED_CHANGES]
            changes[key] = shown
            if len(value) > MAX_LISTED_CHANGES:
                changes[key + '_more'] = len(value) - MAX_LISTED_CHANGES
    return changes


def reverse_requirements(packages):
    """package -> names of the locked packages that require it (`composer why`)."""
    required_by = {}
    for name, versions in packages.items():
        for package in versions:
            for required in package.requires:
                required_by.setdefault(required, set()).add(name)
    return required_by


def changed_package_names(lock):
    return sorted(name for name in set(lock.base) | set(lock.head)
                  if locked_entries(lock.base.get(name, [])) != locked_entries(lock.head.get(name, [])))


def usage_subjects(lock):
    """package -> the PHP namespaces, or the JS package name, to look for in our own files."""
    subjects = {}
    for name in changed_package_names(lock):
        if lock.ecosystem == 'php':
            prefixes = {prefix for package in lock.head.get(name, []) or lock.base.get(name, []) for prefix in package.namespaces}
            if prefixes:
                subjects[name] = sorted(prefixes)
        else:
            subjects[name] = [name]
    return subjects


def scan_files(repository_directory, ecosystem, scope='', top_directories=SCAN_DIRECTORIES):
    """The files where a package of this ecosystem can be mentioned: PHP code and config, or the JS code of one JS project. Each file once."""
    extensions = SCAN_EXTENSIONS[ecosystem]
    seen = set()

    def nested_project(path):
        """A JS project with a lockfile of its own; a workspace package without one belongs to the project above."""
        return ecosystem == 'js' and os.path.exists(os.path.join(path, 'package.json')) and any(
            os.path.exists(os.path.join(path, lock_name)) for lock_name in ('yarn.lock', 'package-lock.json', 'pnpm-lock.yaml'))

    roots = [scope] if scope else [name for name in top_directories if not nested_project(os.path.join(repository_directory, name))]
    for directory in roots:
        for root, directories, files in os.walk(os.path.join(repository_directory, directory)):
            directories[:] = [name for name in directories if name not in SKIPPED_DIRECTORIES and not nested_project(os.path.join(root, name))]
            for file_name in files:
                relative = os.path.relpath(os.path.join(root, file_name), repository_directory)
                if file_name.endswith(extensions) and relative not in seen:
                    seen.add(relative)
                    yield relative
    if not scope:
        for file_name in sorted(os.listdir(repository_directory)):
            if file_name.endswith(extensions) and file_name not in seen and os.path.isfile(os.path.join(repository_directory, file_name)):
                yield file_name


def normalised_scope(scope):
    """A project directory relative to the repository root, without `./`, trailing slashes or `..`; '' is the root."""
    normalised = os.path.normpath(scope) if scope else ''
    if normalised == '.':
        return ''
    if os.path.isabs(normalised) or normalised == '..' or normalised.startswith('..' + os.sep):
        raise ValueError('the scope %r is outside the repository' % scope)
    return normalised


def scan_usage(repository_directory, ecosystem, subjects, scope='', directories=SCAN_DIRECTORIES):
    """package -> {directory: number of files that mention the package's namespaces (PHP) or its name as a quoted import or setting (JS)}.

    A JS lockfile in a subdirectory is searched only in that project. A file counts once per package.
    A PHP scan reads the top-level `directories` (and the files of the root); a repository whose code lives elsewhere names its own.
    """
    scope = normalised_scope(scope)
    directories = [normalised_scope(directory) for directory in directories]
    owners = {}
    for package, needles in subjects.items():
        for needle in needles:
            owners.setdefault(needle, []).append(package)
    if not owners:
        return {}
    longest_first = sorted(owners, key=len, reverse=True)
    if ecosystem == 'php':
        parts = [re.escape(needle).replace('\\\\', '\\\\{1,2}') for needle in longest_first]
        matcher = re.compile(r'(?<!\w)(?<!\w\\)(?<!\w\\\\)(?:%s)' % '|'.join(parts))
    else:
        matcher = re.compile(r'''(?<=['"`])(?:%s)(?=(?:/[^'"`\s]*)?['"`])''' % '|'.join(re.escape(needle) for needle in longest_first))
    usage = {}
    for relative in scan_files(repository_directory, ecosystem, scope, directories):
        path = os.path.join(repository_directory, relative)
        try:
            if not stat.S_ISREG(os.stat(path).st_mode) or os.path.getsize(path) > MAX_SCANNED_FILE:
                continue
            with open(path, errors='ignore') as source_file:
                text = source_file.read()
        except OSError:
            continue
        parts_of_path = relative.split(os.sep)
        if scope:
            inside = os.path.relpath(relative, scope).split(os.sep)
            top = scope if len(inside) == 1 else '%s/%s' % (scope, inside[0])
        else:
            top = parts_of_path[0] if len(parts_of_path) > 1 else 'root'
        found = {re.sub(r'\\+', r'\\', match.group(0)) if ecosystem == 'php' else match.group(0) for match in matcher.finditer(text)}
        for package in {package for needle in found for package in owners.get(needle, ())}:
            counts = usage.setdefault(package, {})
            counts[top] = counts.get(top, 0) + 1
    return {package: dict(sorted(counts.items())) for package, counts in usage.items()}


def build_lock_report(lock, fixed, remaining, lookup_error):
    fixed_by_package = {}
    for advisory in fixed:
        fixed_by_package.setdefault(advisory.package, []).append(advisory)
    required_by = reverse_requirements(lock.head) if lock.ecosystem == 'php' else {}

    package_lines = []
    counts = {}
    for name in sorted(set(lock.base) | set(lock.head)):
        before, after = locked_entries(lock.base.get(name, [])), locked_entries(lock.head.get(name, []))
        if before == after:
            continue
        old_entries, new_entries = ordered_entries(before - after), ordered_entries(after - before)
        old_versions, new_versions = ordered_versions(old_entries), ordered_versions(new_entries)
        old_package = package_at(lock.base.get(name, []), *old_entries[-1]) if old_entries else None
        new_package = package_at(lock.head.get(name, []), *new_entries[-1]) if new_entries else None
        shown_old, shown_new = old_versions, new_versions
        if old_versions and new_versions:
            jump = jump_between(old_versions, new_versions)
        elif name in lock.base and name in lock.head:
            jump = 'deduplicated' if old_versions else 'duplicated'
            shown_old, shown_new = old_versions or ordered_versions(before), new_versions or ordered_versions(after)
        else:
            jump = 'added' if new_versions else 'removed'
        counts[jump] = counts.get(jump, 0) + 1
        line = {
            'package': name, 'from': one_or_list(shown_old), 'to': one_or_list(shown_new),
            'jump': jump, 'direct': name in lock.direct_names,
            'fixes': [advisory.advisory_id for advisory in fixed_by_package.get(name, [])],
        }
        known_package = new_package or old_package
        if known_package.dev is not None:
            line['scope'] = 'dev' if known_package.dev else 'prod'
        if known_package.repository:
            line['repository'] = known_package.repository
        if old_package and new_package:
            line['from_ref'], line['to_ref'] = old_package.reference, new_package.reference
            compare = compare_url(new_package.repository, old_package.reference, new_package.reference)
            if compare:
                line['compare'] = compare
            changes = requirement_changes(old_package.requires, new_package.requires)
            if changes:
                line['requires'] = changes
        if new_package and new_package.released:
            line['released'] = new_package.released
        if known_package.kind and known_package.kind != 'library':
            line['kind'] = known_package.kind
        if name in lock.requirements:
            line['constraint'] = lock.requirements[name]
            if lock.base_requirements.get(name, lock.requirements[name]) != lock.requirements[name]:
                line['constraint_was'] = lock.base_requirements[name]
        elif name in required_by:
            dependants = sorted(required_by[name])
            line['required_by'] = dependants[:5]
            if len(dependants) > 5:
                line['required_by_more'] = len(dependants) - 5
        if lock.patches.get(name):
            line['patches'] = lock.patches[name]
        if known_package.namespaces:
            line['namespaces'] = known_package.namespaces[:3]
            if len(known_package.namespaces) > 3:
                line['namespaces_more'] = len(known_package.namespaces) - 3
        elif known_package.classes_only:
            line['no_namespaces'] = True
        if lock.usage.get(name):
            line['used_in'] = lock.usage[name]
        package_lines.append(line)

    return {
        'path': lock.path, 'ecosystem': lock.ecosystem, 'packages': package_lines, 'counts': counts,
        'advisories': [advisory_entry('advisory', advisory) for advisory in fixed],
        'remaining': [advisory_entry('remaining_advisory', advisory) for advisory in remaining],
        'lookup_error': lookup_error,
    }


def advisory_entry(key, advisory):
    entry = {key: advisory.advisory_id, 'package': advisory.package, 'severity': advisory.severity,
             'title': advisory.title, 'affected': shortened_range(advisory.affected), 'url': advisory.url, 'cve': advisory.cve}
    return {name: value for name, value in entry.items() if value}


def shortened_range(affected, limit=160):
    """Composer lists a long union of ranges joined by `|`; cut between two of them, never inside one."""
    if len(affected) <= limit:
        return affected
    cut = affected.rfind('|', 0, limit)
    return affected[:cut if cut > 0 else limit] + '|…'


def advisory_line(entry, level):
    """Advisory lines shrink with the level: full, then without title and range, then id, package and severity only."""
    drop = {1: ('title', 'affected'), 2: ('title', 'affected', 'url', 'cve')}.get(level, ())
    return to_json_line({name: value for name, value in entry.items() if name not in drop})


def needs_attention(line):
    return bool(line['fixes'] or line.get('patches'))


def package_entry(line, level, known_advisories):
    entry = {'package': line['package'], 'from': line['from'], 'to': line['to'], 'jump': line['jump']}
    if line['direct']:
        entry['direct'] = True
    if line.get('scope'):
        entry['scope'] = line['scope']
    if known_advisories and line['fixes']:
        entry['fixes'] = line['fixes']
    for key in ('constraint', 'constraint_was', 'patches', 'used_in'):
        if line.get(key):
            entry[key] = line[key]
    significant = needs_attention(line) or line['jump'] in ('major', 'minor', 'downgrade', 'deduplicated', 'duplicated')
    if level == 0 or (level == 1 and significant) or (level >= 2 and needs_attention(line)):
        for key in ('repository', 'from_ref', 'to_ref', 'released', 'kind', 'requires', 'required_by', 'required_by_more', 'namespaces', 'namespaces_more', 'no_namespaces'):
            if line.get(key):
                entry[key] = line[key][:12] if key in ('from_ref', 'to_ref') else line[key]
    return entry


def host_of(url):
    try:
        return urllib.parse.urlsplit(url).hostname
    except ValueError:
        return None


def link(text, url):
    """`https://github.com@evil.example/` shows a trusted name in front of user info, so an address with user info is no link."""
    safe = url and len(url) <= MAX_LINK_LENGTH and SAFE_URL.fullmatch(url) and '@' not in urllib.parse.urlsplit(url).netloc
    return '[%s](%s)' % (text, url) if safe else ''


def linked(text, url):
    """The text as a link when the address is safe, else the text alone."""
    return link(text, url) or text


def package_url(line, ecosystem):
    if ecosystem == 'php':
        return line.get('repository') if host_of(line.get('repository') or '') in TRUSTED_REPOSITORY_HOSTS else None
    return 'https://www.npmjs.com/package/%s' % line['package'] if NPM_NAME.fullmatch(line['package']) else None


def advisory_url(advisory_id, url):
    """A GitHub advisory has one page in the GitHub Advisory Database; any other id goes to the address the audit gave."""
    return 'https://github.com/advisories/%s' % advisory_id if GITHUB_ADVISORY.fullmatch(advisory_id) else url


def table_row(line, ecosystem, known_advisories, advisory_urls):
    fixes = line['fixes'] if known_advisories else []
    shown = ', '.join(linked(table_code(advisory_id, 40), advisory_url(advisory_id, advisory_urls.get(advisory_id))) for advisory_id in fixes[:3])
    shown += ' +%d' % (len(fixes) - 3) if len(fixes) > 3 else ''
    notes = [note for note, present in (('direct', line['direct']), ('patched', line.get('patches')),
                                        ('constraint changed', line.get('constraint_was'))) if present]
    return '| %s | %s | %s | %s | %s | %s |' % (
        linked(table_code(line['package'], 100), package_url(line, ecosystem)),
        table_code('%s → %s' % (version_text(line['from']), version_text(line['to'])), 60),
        line['jump'], link('diff', line.get('compare')) or '—', shown or '—', ', '.join(notes) or '—')


def table_code(text, limit):
    """A code span that cannot split a table cell: GitHub splits cells on every `|`, also inside code."""
    return code_span(text, limit).replace('|', '\\|')


def changes_table(report, known_advisories):
    rows = [line for line in report['packages'] if needs_attention(line) or line['jump'] in ('major', 'downgrade')]
    if not rows:
        return []
    advisory_urls = {advisory['advisory']: advisory.get('url') for advisory in report['advisories']}
    lines = ['', '| Package | From → To | Jump | Diff | Fixes | Notes |', '|---|---|---|---|---|---|']
    lines += [table_row(line, report['ecosystem'], known_advisories, advisory_urls) for line in rows[:MAX_TABLE_ROWS]]
    if len(rows) > MAX_TABLE_ROWS:
        lines += ['', '_%d more packages are in the lines below._' % (len(rows) - MAX_TABLE_ROWS)]
    return lines


def big_jump_summary(packages):
    groups = {}
    for line in packages:
        if line['jump'] in ('major', 'downgrade') or (line['jump'] == 'minor' and line['fixes']):
            key = (line['package'].split('/')[0], version_text(line['from']), version_text(line['to']), line['jump'])
            groups.setdefault(key, []).append(line['package'])
    parts = []
    for (prefix, old, new, jump), names in groups.items():
        label = names[0] if len(names) == 1 else '%s/* (%d packages)' % (prefix, len(names))
        parts.append('%s (%s)' % (code_span('%s %s → %s' % (label, old, new)), jump))
    more = ' and %d more' % (len(parts) - SHOWN_BIG_JUMPS) if len(parts) > SHOWN_BIG_JUMPS else ''
    return ', '.join(parts[:SHOWN_BIG_JUMPS]) + more


def to_json_line(value):
    """One compact line; a backtick and a `<` that starts a tag or comment are escaped, so lockfile text cannot close the fence or fake a marker.
    A `<` or `>` in a version range (`<1.0.1`, `>=8.2`) stays readable."""
    text = json.dumps(value, ensure_ascii=False, separators=(',', ':'))
    return re.sub(r'<(?![0-9=\s])', r'\\u003c', text.replace('`', '\\u0060'))


def render_lock_report(report, level):
    packages = report['packages']
    known_advisories = not report['lookup_error']
    with_fixes = sum(1 for line in packages if line['fixes'])
    jumps = ', '.join('%d %s' % (count, name) for name, count in report['counts'].items())
    lines = ['### %s — %s' % ('PHP' if report['ecosystem'] == 'php' else 'JS', code_span(report['path'], 100)), '']
    if known_advisories:
        lines.append('%d packages change (%s). Packages with fixes: %d, fixing %d advisories. Companions: %d. Advisories remaining after the update: %d.' % (
            len(packages), jumps or 'none', with_fixes, len(report['advisories']), len(packages) - with_fixes, len(report['remaining'])))
    else:
        lines.append('%d packages change (%s). **Advisory lookup failed, so which packages fix advisories and which advisories remain is not known:** %s' % (
            len(packages), jumps or 'none', code_span(report['lookup_error'])))
    big_jumps = big_jump_summary(packages)
    if big_jumps:
        lines += ['', 'Bigger than a patch: ' + big_jumps]
    lines += changes_table(report, known_advisories)
    lines += ['', '```jsonl']
    collapsed, collapsed_key = [], 'companions' if known_advisories else 'unclassified'
    for line in packages:
        if level >= 2 and not needs_attention(line):
            collapsed.append([line['package'], line['from'], line['to'], line['jump']])
        else:
            lines.append(to_json_line(package_entry(line, level, known_advisories)))
    if collapsed:
        lines.append(to_json_line({collapsed_key: collapsed}))
    if level >= 2 and report['advisories']:
        lines.append(to_json_line({'advisories': [[advisory['advisory'], advisory['package'], advisory['severity']] for advisory in report['advisories']]}))
    else:
        lines += [advisory_line(advisory, level) for advisory in report['advisories']]
    if level == 0:
        lines += [advisory_line(advisory, level) for advisory in report['remaining']]
    elif report['remaining']:
        lines.append(to_json_line({'remaining_advisories': [
            [advisory['remaining_advisory'], advisory['package'], advisory['severity']] for advisory in report['remaining']]}))
    lines.append('```')
    return '\n'.join(lines)


def fit_section(section, budget):
    """Cut a section at a line end inside its jsonl block, so the closing fence is always ours."""
    if len(section) <= budget:
        return section
    fence_end = section.find(FENCE_OPEN) + len(FENCE_OPEN)
    cut = max(section.rfind('\n', 0, max(budget - 120, 0)), fence_end - 1)
    return section[:cut] + '\n```\n\n' + CUT_NOTE


def share_budget(total, sizes):
    """Give every section its need up to an equal share, and hand what small sections leave over to the big ones."""
    budgets = [0] * len(sizes)
    pending = sorted(range(len(sizes)), key=lambda index: sizes[index])
    remaining = total
    for position, index in enumerate(pending):
        budgets[index] = min(sizes[index], remaining // (len(pending) - position))
        remaining -= budgets[index]
    return budgets


def render_brief(reports, head_label, limit, instructions=REVIEW_INSTRUCTIONS):
    ordered = sorted(reports, key=lambda report: (report['ecosystem'] != 'php', report['path']))
    header = '\n'.join([
        BRIEF_START, '## Security update brief', '',
        'Head `%s`. Generated by `.github/scripts/security_update_brief.py`; regenerated on every push.' % plain_text(head_label, 40),
        '', instructions, '', '',
    ])
    footer = '\n' + BRIEF_END
    levels = [0] * len(ordered)
    sections = [render_lock_report(report, 0) for report in ordered]
    while len(header) + len(footer) + sum(len(section) for section in sections) + 2 * len(sections) > limit:
        shrinkable = [index for index, level in enumerate(levels) if level < 2]
        if not shrinkable:
            break
        js_first = [index for index in shrinkable if ordered[index]['ecosystem'] != 'php']
        biggest = max(js_first or shrinkable, key=lambda index: len(sections[index]))
        levels[biggest] += 1
        sections[biggest] = render_lock_report(ordered[biggest], levels[biggest])
    budgets = share_budget(limit - len(header) - len(footer) - 2 * len(sections), [len(section) for section in sections])
    brief = header + '\n\n'.join(fit_section(section, budget) for section, budget in zip(sections, budgets)) + footer
    if len(brief) <= limit:
        return brief
    kept = shorten_at_paragraph(brief, limit - len(footer) - len(CUT_NOTE) - 2)
    return (kept if kept.startswith(BRIEF_START) else BRIEF_START) + '\n\n' + CUT_NOTE + footer


def splice_body(body, brief, limit=BODY_LIMIT):
    """Put the brief first, replacing an earlier one; shorten the rest at a paragraph end to stay under GitHub's limit."""
    body = remove_brief(body or '')
    room = limit - len(brief) - 2
    if len(body) > room:
        kept = shorten_at_paragraph(body, room - len(SHORTENED_NOTE) - 2)
        body = '\n\n'.join(part for part in (kept, SHORTENED_NOTE if room >= len(SHORTENED_NOTE) else '') if part)
    return brief + ('\n\n' + body if body.strip() else '')


def remove_brief(body):
    """Drop every complete brief (the nearest start before each end), then the stray markers a hand edit left behind."""
    while BRIEF_END in body:
        end = body.find(BRIEF_END)
        start = body.rfind(BRIEF_START, 0, end)
        body = body[:end if start == -1 else start] + body[end + len(BRIEF_END):]
    return body.replace(BRIEF_START, '').strip()


def scan_blocks(text):
    """Yield (offset of the end of the line, open fence marker or None, open details count, line is blank) after each line."""
    fence, open_details, start = None, 0, 0
    for line in text.split('\n'):
        match = FENCE_LINE.match(line)
        if match:
            marker, rest = match.groups()
            if fence is None:
                if not (marker[0] == '`' and '`' in rest):
                    fence = marker
            elif marker[0] == fence[0] and len(marker) >= len(fence) and not rest.strip():
                fence = None
        elif fence is None:
            visible = INLINE_CODE.sub('', line)
            open_details = max(open_details + len(DETAILS_OPEN.findall(visible)) - len(DETAILS_CLOSE.findall(visible)), 0)
        yield start + len(line), fence, open_details, not line.strip()
        start += len(line) + 1


def closers_for(fence, open_details):
    return ('\n' + fence if fence else '') + '\n</details>' * max(open_details, 0)


def close_open_blocks(text):
    """A cut can leave a code fence or a details block open, which would swallow what follows."""
    state = (None, 0)
    for _, fence, open_details, _ in scan_blocks(text):
        state = (fence, open_details)
    return text + closers_for(*state)


def shorten_at_paragraph(text, budget):
    """A start of the text that fits the budget once its open blocks are closed: ending at a paragraph end, else at a line end, each only if it keeps at least half the budget, else cut anywhere."""
    states = list(scan_blocks(text))
    best_line = best_paragraph = None
    for index, (end, fence, open_details, blank) in enumerate(states):
        if end > budget:
            break
        closers = closers_for(fence, open_details)
        if end > 0 and end + len(closers) <= budget:
            best_line = (end, closers)
            if not blank and index + 1 < len(states) and states[index + 1][3]:
                best_paragraph = (end, closers)
    for candidate in (best_paragraph, best_line):
        if candidate and candidate[0] >= budget // 2:
            return text[:candidate[0]] + candidate[1]
    hard = text[:max(budget, 0)]
    while hard:
        if hard.rfind('<') > hard.rfind('>'):
            hard = hard[:hard.rfind('<')]  # a cut inside a tag would swallow the closers as attributes
        closed = close_open_blocks(hard)
        if len(closed) <= budget:
            return closed
        hard = hard[:max(len(hard) - max((len(closed) - budget + 1) // 2, 1), 0)]  # closers can outgrow the text they close, so give back half the overflow at a time
    return ''


def git(repository_directory, *arguments):
    result = subprocess.run(['git', '-C', repository_directory, *arguments], capture_output=True, text=True)
    if result.returncode != 0:
        raise RuntimeError('git %s failed: %s' % (' '.join(arguments[:2]), result.stderr.strip()[:300]))
    return result.stdout


def changed_lock_paths(repository_directory, base_sha, head_sha):
    paths = git(repository_directory, 'diff', '--name-only', base_sha, head_sha).split('\n')
    return [path for path in paths if os.path.basename(path) in ('composer.lock', 'yarn.lock') and not set(path.split('/')) & set(VENDORED_DIRECTORIES)]


def read_instructions(path):
    """The repository's own instructions for taking an update, put in the brief instead of the built-in ones."""
    with open(path) as instructions_file:
        text = instructions_file.read().strip()
    if not text or BRIEF_START in text or BRIEF_END in text:
        raise ValueError('the instructions file %s is empty or holds a brief marker' % path)
    if len(text) > MAX_INSTRUCTIONS:
        raise ValueError('the instructions file %s has %d characters, more than the %d that fit in a pull request body next to the tables'
                         % (path, len(text), MAX_INSTRUCTIONS))
    return text


def file_at(repository_directory, sha, path):
    """The file's text, or None when the commit has no such path; any other git failure raises."""
    result = subprocess.run(
        ['git', '-C', repository_directory, 'show', '%s:%s' % (sha, path)],
        capture_output=True, text=True, env={**os.environ, 'LC_ALL': 'C'},
    )
    if result.returncode == 0:
        return result.stdout
    if 'does not exist in' in result.stderr or 'exists on disk, but not in' in result.stderr:
        return None
    raise RuntimeError('git show %s:%s failed: %s' % (sha, path, result.stderr.strip()[:200]))


def load_lock(repository_directory, base_sha, head_sha, path):
    directory_path, file_name = os.path.split(path)
    ecosystem = 'php' if file_name == 'composer.lock' else 'js'
    parse = parse_composer_lock if ecosystem == 'php' else parse_yarn_lock
    base_text, head_text = file_at(repository_directory, base_sha, path), file_at(repository_directory, head_sha, path)
    manifest_name = 'composer.json' if ecosystem == 'php' else 'package.json'
    head_manifest = read_json(file_at(repository_directory, head_sha, os.path.join(directory_path, manifest_name)))
    base_manifest = read_json(file_at(repository_directory, base_sha, os.path.join(directory_path, manifest_name)))
    requirements = requirements_of(head_manifest, ecosystem)
    return Lock(
        path=path, ecosystem=ecosystem,
        base=parse(base_text) if base_text else {}, head=parse(head_text) if head_text else {},
        direct_names=set(requirements), requirements=requirements, base_requirements=requirements_of(base_manifest, ecosystem),
        patches=composer_patches(head_manifest) if ecosystem == 'php' else {},
    )


def read_json(text):
    try:
        value = json.loads(text) if text else {}
    except ValueError:
        return {}
    return value if isinstance(value, dict) else {}


def advisories_of(repository_directory, base_sha, sha, lock, state):
    if lock.ecosystem == 'php':
        lock_text = file_at(repository_directory, sha, lock.path)
        if lock_text is None:
            return []
        with tempfile.TemporaryDirectory() as workdir:
            write_audit_project(workdir, repository_directory, base_sha, os.path.dirname(lock.path), lock_text)
            return composer_advisories(workdir)
    return npm_advisories({name: sorted({package.version for package in packages}) for name, packages in state.items()})


def audit_both_states(repository_directory, base_sha, head_sha, lock):
    """Return (fixed, remaining, error): advisories of the base lock the head no longer has, and those it still has."""
    try:
        base_advisories = advisories_of(repository_directory, base_sha, base_sha, lock, lock.base)
        head_advisories = advisories_of(repository_directory, base_sha, head_sha, lock, lock.head)
    except Exception as error:  # any failure must still leave the brief, with the lookup marked as failed
        return [], [], '%s: %s' % (type(error).__name__, error)
    head_ids = {(advisory.package, advisory.advisory_id) for advisory in head_advisories}
    fixed = [advisory for advisory in base_advisories if (advisory.package, advisory.advisory_id) not in head_ids]
    return fixed, head_advisories, None


def build_brief(options):
    instructions = read_instructions(options.instructions_file) if options.instructions_file else REVIEW_INSTRUCTIONS
    directories = [normalised_scope(directory) for directory in options.scan_directories or SCAN_DIRECTORIES]
    reports = []
    for path in changed_lock_paths(options.repository, options.base_sha, options.head_sha):
        lock = load_lock(options.repository, options.base_sha, options.head_sha, path)
        try:
            lock.usage = scan_usage(options.repository, lock.ecosystem, usage_subjects(lock), os.path.dirname(lock.path) if lock.ecosystem == 'js' else '', directories)
        except Exception as error:  # the usage hints are a bonus; the brief must not depend on them
            print('usage scan failed for %s: %s' % (path, error), file=sys.stderr)
        fixed, remaining, error = audit_both_states(options.repository, options.base_sha, options.head_sha, lock)
        reports.append(build_lock_report(lock, fixed, remaining, error))
    reports = [report for report in reports if report['packages']]
    if not reports:
        print('no lockfile changes, no brief')
        return
    brief = render_brief(reports, options.head_label or options.head_sha[:12], BODY_LIMIT - ORIGINAL_TEXT_RESERVE, instructions)
    with open(options.output, 'w') as output_file:
        output_file.write(brief)
    print('%d lockfile(s) summarised, brief of %d characters' % (len(reports), len(brief)))


def splice_files(options):
    with open(options.body_file) as body_file:
        body = body_file.read().rstrip('\n')
    with open(options.brief_file) as brief_file:
        brief = brief_file.read()
    new_body = splice_body(body, brief)
    if new_body == body:
        print('body unchanged')
        return
    with open(options.output, 'w') as output_file:
        output_file.write(new_body)
    print('body %d -> %d characters' % (len(body), len(new_body)))


def main(arguments):
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    commands = parser.add_subparsers(dest='command', required=True)
    brief_parser = commands.add_parser('brief', help='summarise the lockfile changes between two commits')
    brief_parser.add_argument('--repository', required=True, help='git checkout holding both commits')
    brief_parser.add_argument('--base-sha', required=True)
    brief_parser.add_argument('--head-sha', required=True)
    brief_parser.add_argument('--head-label', help='what to print as the head, default the first 12 characters of --head-sha')
    brief_parser.add_argument('--scan-directories', nargs='+', metavar='DIRECTORY',
                              help='top-level directories searched for the PHP namespaces of a changed package; default: the built-in list')
    brief_parser.add_argument('--instructions-file', metavar='FILE',
                              help='markdown with the steps for taking an update, put in the brief instead of the built-in steps')
    brief_parser.add_argument('--output', required=True)
    splice_parser = commands.add_parser('splice', help='put a brief at the top of a pull request body')
    splice_parser.add_argument('--body-file', required=True)
    splice_parser.add_argument('--brief-file', required=True)
    splice_parser.add_argument('--output', required=True)
    options = parser.parse_args(arguments)
    (build_brief if options.command == 'brief' else splice_files)(options)


if __name__ == '__main__':
    main(sys.argv[1:])
