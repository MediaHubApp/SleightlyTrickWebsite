"""Build the deployment archive without local data, tests, Git or credentials."""
from hashlib import sha256
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile

root = Path(__file__).resolve().parents[1]
public_files = ['.htaccess', 'favicon.svg', 'index.php', 'instructions.php',
                'instruction-video.php', 'media.php', 'robots.txt', 'setup.php']
for directory in ['admin', 'app', 'assets', 'images']:
    public_files.extend(str(p.relative_to(root)) for p in (root / directory).rglob('*') if p.is_file())
entries = {f'httpdocs/{name}': root / name for name in sorted(public_files)}
entries['INSTALL-PLESK.txt'] = root / 'INSTALL-PLESK.txt'
checksums = ''.join(f'{sha256(path.read_bytes()).hexdigest()}  {name}\n' for name, path in sorted(entries.items()))
(root / 'SHA256SUMS.txt').write_text(checksums)
output = root / 'releases'
output.mkdir(exist_ok=True)
archive = output / 'sleightly-tricky-website.zip'
with ZipFile(archive, 'w', ZIP_DEFLATED, compresslevel=9) as z:
    for name, path in sorted(entries.items()):
        z.write(path, name)
    z.writestr('SHA256SUMS.txt', checksums)
with ZipFile(archive) as z:
    assert z.testzip() is None
    for line in z.read('SHA256SUMS.txt').decode().splitlines():
        checksum, name = line.split('  ', 1)
        assert sha256(z.read(name)).hexdigest() == checksum, name
(output / 'SHA256SUMS.txt').write_text(f'{sha256(archive.read_bytes()).hexdigest()}  {archive.name}\n')
print(f'Built and verified {archive} ({archive.stat().st_size:,} bytes, {len(entries)} deployment files plus checksums).')
