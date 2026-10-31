"""Build the deployment archive without local data, tests, Git or credentials."""
from hashlib import sha256
from pathlib import Path
from time import localtime
from zipfile import ZIP_DEFLATED, ZipFile, ZipInfo

root = Path(__file__).resolve().parents[1]
public_files = ['.htaccess', 'favicon.svg', 'index.php', 'contact.php', 'instructions.php',
                'instruction-video.php', 'media.php', 'robots.txt', 'setup.php']
for directory in ['admin', 'app', 'assets', 'images']:
    public_files.extend(str(p.relative_to(root)) for p in (root / directory).rglob('*') if p.is_file())
entries = {f'httpdocs/{name}': root / name for name in sorted(public_files)}
entries['INSTALL-PLESK.txt'] = root / 'INSTALL-PLESK.txt'
entries['CONTACT-PLESK.txt'] = root / 'CONTACT-PLESK.txt'
entries['CONTACT-MAIL.example.php'] = root / 'CONTACT-MAIL.example.php'
checksums = ''.join(f'{sha256(path.read_bytes()).hexdigest()}  {name}\n' for name, path in sorted(entries.items()))
(root / 'SHA256SUMS.txt').write_text(checksums)
output = root / 'releases'
output.mkdir(exist_ok=True)
archive = output / 'sleightly-tricky-website.zip'
timestamp = localtime()[:6]

def write_entry(z, name, data=b'', directory=False):
    # Do not inherit this workspace's restrictive 600/700 permissions.
    # Apache/nginx must be able to read public files and traverse directories.
    info = ZipInfo(name, timestamp)
    info.create_system = 3
    info.external_attr = ((0o40755 if directory else 0o100644) << 16) | (0x10 if directory else 0)
    info.compress_type = ZIP_DEFLATED
    z.writestr(info, data, compresslevel=9)

with ZipFile(archive, 'w', ZIP_DEFLATED, compresslevel=9) as z:
    directories = {parent.as_posix() + '/' for name in entries for parent in Path(name).parents if parent != Path('.')}
    for name in sorted(directories):
        write_entry(z, name, directory=True)
    for name, path in sorted(entries.items()):
        write_entry(z, name, path.read_bytes())
    write_entry(z, 'SHA256SUMS.txt', checksums)
with ZipFile(archive) as z:
    assert z.testzip() is None
    for entry in z.infolist():
        assert entry.external_attr >> 16 == (0o40755 if entry.is_dir() else 0o100644), entry.filename
    for line in z.read('SHA256SUMS.txt').decode().splitlines():
        checksum, name = line.split('  ', 1)
        assert sha256(z.read(name)).hexdigest() == checksum, name
(output / 'SHA256SUMS.txt').write_text(f'{sha256(archive.read_bytes()).hexdigest()}  {archive.name}\n')
print(f'Built and verified {archive} ({archive.stat().st_size:,} bytes, {len(entries)} deployment files plus checksums).')
