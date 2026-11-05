#!/usr/bin/env python3
"""Refuses a folded branch that changes more than composer.lock and plain files under vendor/.

Reads `git diff --raw -z --no-renames <base> <folded>` on stdin. A rename is not looked for (the old path is then listed as removed),
and a path is judged by the whole of it, not by how it begins. A new mode that is not a plain file (a symlink, a submodule) is refused,
and so is a new `.gitmodules` or `.gitattributes`: vendor/ has `.gitattributes` files of packages, which may change or go.
"""
import os
import re
import sys

fields = sys.stdin.buffer.read().decode('utf-8', 'surrogateescape').split('\0')
# every entry is a meta field and a path, each ended by a NUL, so the split ends in one empty field; anything else is a cut stream
if len(fields) % 2 != 1 or fields[-1] != '':
    sys.exit('The list of changes is cut off; refusing the folded branch.')
refused = []
for meta, path in zip(fields[0::2], fields[1::2]):
    old_mode, new_mode = meta.split()[0], meta.split()[1]
    in_scope = re.fullmatch(r'composer\.lock|vendor/.*', path) is not None
    plain = new_mode in ('100644', '100755', '000000')
    added_git_file = old_mode == ':000000' and os.path.basename(path) in ('.gitmodules', '.gitattributes')
    if not (in_scope and plain and not added_git_file):
        refused.append(path)

if refused:
    print('The folded branch changes more than composer.lock and vendor/, or adds something that is not a plain file:', file=sys.stderr)
    print('\n'.join(refused), file=sys.stderr)
    sys.exit(1)
