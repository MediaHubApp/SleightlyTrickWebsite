"""Exercise the actual extracted deployment artifact against the HTTP suite."""
import os
from pathlib import Path
import subprocess
import sys
import tempfile
from zipfile import ZipFile

root = Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='sleightly-package-') as temporary:
    with ZipFile(root / 'releases/sleightly-tricky-website.zip') as archive:
        archive.extractall(temporary)
    subprocess.run([sys.executable, '-m', 'unittest', 'discover', '-s', 'tests', '-v'], cwd=root, env={**os.environ, 'ST_TEST_SITE_ROOT': str(Path(temporary) / 'httpdocs')}, check=True)
print('Extracted Plesk deployment package passed all HTTP integration tests.')
