"""Exercise the actual extracted deployment artifact against the HTTP suite."""
import os
import stat
from pathlib import Path
import subprocess
import sys
import tempfile
from zipfile import ZipFile

root = Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='sleightly-package-') as temporary:
    with ZipFile(root / 'releases/sleightly-tricky-website.zip') as archive:
        for entry in archive.infolist():
            expected = 0o40755 if entry.is_dir() else 0o100644
            assert entry.external_attr >> 16 == expected, f'Unsafe deployment permissions: {entry.filename}'
        expected_paths = {entry.filename: entry.is_dir() for entry in archive.infolist()}
    # Python extractall ignores Unix mode metadata. Use the real extractor so
    # packaging mistakes cannot be hidden by the test runner's file ownership.
    subprocess.run(['unzip', '-q', str(root / 'releases/sleightly-tricky-website.zip'), '-d', temporary], check=True, umask=0o022)
    for name, directory in expected_paths.items():
        mode = stat.S_IMODE((Path(temporary) / name).stat().st_mode)
        assert mode == (0o755 if directory else 0o644), f'Wrong extracted permissions: {name}: {oct(mode)}'
    assert (Path(temporary) / 'httpdocs/index.php').is_file(), 'Missing root index.php'
    subprocess.run([sys.executable, '-m', 'unittest', 'discover', '-s', 'tests', '-v'], cwd=root, env={**os.environ, 'ST_TEST_SITE_ROOT': str(Path(temporary) / 'httpdocs')}, check=True)
print('Extracted Plesk deployment package passed permission/layout checks and all HTTP integration tests.')
