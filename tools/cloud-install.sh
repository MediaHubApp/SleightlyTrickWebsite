#!/bin/sh
set -eu
cd /workspace/SleightlyTrickWebsite
# Prepare a user-local PHP without changing application files or the system.
# Debian signed indexes and package checksums remain enabled.
php_bin=/workspace/.runtime/bin/php
if [ ! -x "$php_bin" ] || ! "$php_bin" -r 'exit(PHP_VERSION_ID >= 80200 && extension_loaded("pdo_sqlite") && extension_loaded("fileinfo") ? 0 : 1);'; then
  apt_root=/workspace/.runtime/apt
  mkdir -p "$apt_root/state/lists/partial" "$apt_root/cache/archives/partial" /workspace/.runtime/php /workspace/.runtime/bin
  cat > "$apt_root/sources.list" <<'SOURCES'
deb [signed-by=/usr/share/keyrings/debian-archive-keyring.gpg] https://deb.debian.org/debian trixie main
deb [signed-by=/usr/share/keyrings/debian-archive-keyring.gpg] https://security.debian.org/debian-security trixie-security main
SOURCES
  apt-get -o Dir::Etc::sourcelist="$apt_root/sources.list" -o Dir::Etc::sourceparts=- -o Dir::State="$apt_root/state" -o Dir::Cache="$apt_root/cache" -o APT::Sandbox::User=agent update
  (
    cd /workspace/.runtime/php
    apt-get -o Dir::Etc::sourcelist="$apt_root/sources.list" -o Dir::Etc::sourceparts=- -o Dir::State="$apt_root/state" -o APT::Sandbox::User=agent download php8.4-cli php8.4-sqlite3 php8.4-common php8.4-opcache php8.4-readline libargon2-1
    for package in ./*.deb; do dpkg-deb -x "$package" root; done
  )
  cat > "$php_bin" <<'PHP_WRAPPER'
#!/bin/sh
runtime=/workspace/.runtime/php/root
exec env LD_LIBRARY_PATH="$runtime/usr/lib/x86_64-linux-gnu${LD_LIBRARY_PATH:+:$LD_LIBRARY_PATH}" "$runtime/usr/bin/php8.4" -n -d extension_dir="$runtime/usr/lib/php/20240924" -d extension=pdo -d extension=pdo_sqlite -d extension=fileinfo "$@"
PHP_WRAPPER
  chmod +x "$php_bin"
fi
"$php_bin" -v
"$php_bin" -r 'if (!extension_loaded("pdo_sqlite") || !extension_loaded("fileinfo")) {fwrite(STDERR,"Required PHP extension missing\n"); exit(1);}'
find . -name '*.php' -not -path './.git/*' -exec "$php_bin" -l '{}' \;
PHP_BIN="$php_bin" python3 -m unittest discover -s tests -v
