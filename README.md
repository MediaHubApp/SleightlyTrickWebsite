# Sleightly Tricky website

PHP website for Sleightly Tricky, including its stallholder editor and
password-protected customer instruction videos. This project is independent
of MediaHub; the GitHub organisation name is just where it is stored.

## The 1.4 immersive redesign

An immersive, modern interface in burgundy, cream and charcoal: layered hero
artwork, a floating navigation frame, visual collection routes, dimensional
product scenes and a swipeable deck gallery. Product details open in native
keyboard-accessible dialogs with complete descriptions and prices. The
instruction library, password gate and video player use the same visual system.

Motion includes staggered scroll reveals, paper/card hover transitions,
subtle pointer depth on the hero, a reading-progress line and dialog entrance
animations. Reduced-motion preferences are respected. Nothing scrolls or
advances automatically, and the public catalogue remains readable without
JavaScript. There are no decorative section labels or invented purchase links.

[![Homepage](docs/redesign/homepage.png)](docs/redesign/homepage-full.png)

[Full homepage](docs/redesign/homepage-full.png) ·
[Mobile](docs/redesign/mobile.png) ·
[Product details](docs/redesign/product-details.png) ·
[Instruction library](docs/redesign/instruction-library.png).

![Product detail view](docs/redesign/product-details.png)

The cup artwork retains the three correctly inverted cups. Faces are drawn
as exact SVGs with black spades/clubs, red hearts/diamonds, matching opposing
indices and correct pip counts. Generic card backs and prop illustrations
are unbranded; actual uploaded product photos take priority. The new tabletop
still life shows only face-down cards. These images are illustrative, not
photographs of the products for sale. Screenshots use sample instruction pages;
no demo passwords, customer pages or private videos are included in the ZIP.

Existing accounts, products, prices and private videos remain compatible.
Unchanged stock copy is refreshed for the new design; custom copy, prices,
photos and product settings are preserved. Changes are reflected in the
editor and become stored content when you next publish. The old decorative
location-line and product-label settings are no longer displayed or edited.

## Download and install

Download [the Plesk deployment ZIP](releases/sleightly-tricky-website.zip).
Follow [INSTALL-PLESK.txt](INSTALL-PLESK.txt). Extract the deployment ZIP one
level above `httpdocs`, or upload only its `httpdocs` contents to your public
root. Preserve existing private data when upgrading.

The ZIP has no preconfigured accounts, customer passwords or videos. Create
your owner account using the unique code generated outside the public root,
then add your tricks through **Admin → Instruction videos**.

Each page supports a shared trick password, public summary, protected notes,
one YouTube/Vimeo/direct MP4 video or private MP4 upload, a customer link,
publish/hide, and delete. Instruction changes use their own Save button.
External providers still control access to their video URLs; private uploads
are checked by the website on every request. Shared passwords do not provide
DRM or prevent buyers sharing access.

## Local development

Requires PHP 8.2+ with PDO SQLite and fileinfo. There are no Composer or npm
dependencies. Use the existing checkout: cloud tasks are already isolated;
do not create a Git worktree unless explicitly requested.

On a PHP-equipped machine, from the repository root:

```sh
ST_LOCAL_HTTP=1 ST_STORAGE_DIR=/tmp/sleightly-dev-data \
  php -d upload_max_filesize=200M -d post_max_size=210M \
  -S 127.0.0.1:8081 -t . tools/dev-router.php
```

The HTTP override is accepted only by PHP's built-in development server.
Production always requires HTTPS with the supplied configuration. Never run
PHP's development server as your production web server. Local private data
must be outside the checkout/public root. The development router also blocks
source/configuration files normally protected by Apache's `.htaccess`.

For this Debian cloud environment, `sh tools/cloud-install.sh` prepares a
user-local PHP runtime at `/workspace/.runtime/bin/php` without root access.
Use that path in place of `php` in the commands above.

## Validation

```sh
PHP_BIN=/workspace/.runtime/bin/php python3 -m unittest discover -s tests -v
PHP_BIN=/workspace/.runtime/bin/php python3 tests/browser_smoke.py
PHP_BIN=/workspace/.runtime/bin/php python3 tests/design_smoke.py
```

The automated suite has 13 passing tests covering original-site setup/migration,
editing, customer gates, CSRF, rate limiting, expiry, per-page access,
password replacement, hiding/deleting, private uploads and byte ranges,
malformed inputs, editor permissions and revocation, exact card pip counts/colours,
card indices, and compatible copy upgrades. It uses temporary data.
The browser smoke test requires Python Playwright and `/usr/bin/chromium`.
It exercises admin page creation, real video upload, saved-page reload,
mobile password entry and MP4 playback, password replacement, hiding,
deleting, responsive layout and JavaScript error checks.

The design smoke test checks five viewport widths (320–1440px), search,
password visibility, FAQs, keyboard/menu interactions, JavaScript-disabled
navigation and native form submission, reduced motion and horizontal overflow.
It also checks product dialogs, focus wrapping/return, gallery button and keyboard
controls, mobile product details, pointer-depth animation and cancellation when
the motion preference changes.

Real YouTube/Vimeo playback and production Plesk settings require validation
with your hosting account and actual video links. The test video is a tiny
blank clip, not a customer's instructions, and is excluded from deployment.

Build the deployment ZIP and checksums with `python3 tools/package.py`.
Run `PHP_BIN=/workspace/.runtime/bin/php python3 tools/verify-package.py` to
check explicit ZIP permissions, real unzip extraction, deployment layout,
and the HTTP suite against the extracted website. Public files are packaged
as `644` and directories as `755`, independently of local workspace modes.
The package includes only deployable site files and the Plesk guide; it
excludes Git metadata, development tools, tests, private data and credentials.

## Font licences

Libre Caslon Display and Manrope are self-hosted from the official Google Fonts
repository under the SIL Open Font License. Their licence notices are included
in [assets/fonts](assets/fonts). Site fonts and interface scripts are served locally. External video providers
load only after a customer unlocks a lesson.
