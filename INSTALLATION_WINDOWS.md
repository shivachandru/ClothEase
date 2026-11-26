# Windows installation and local network guide

## Development setup available in this workspace

This machine has XAMPP with PHP 8.2.12 and MySQL/MariaDB components. The application was initialized with SQLite for isolated development. A protected, local-only Windows test installer is built as `dist/ClothEase-Setup-<version>.exe`. Before any install or update, it checks for PHP 8.2+ with `mbstring`, `openssl`, and PDO SQLite. If a prerequisite is missing, setup is disabled and the window provides links to XAMPP, PHP for Windows, and the Microsoft Visual C++ runtime. It asks for the build's passkey, blocks reinstalling the same version, confirms before updating an older version, and shows an Uninstall button when it finds program files. Uninstall removes the program folder and desktop shortcut, stops the local server, and keeps the SQLite database/backups under the user data folder. This is a test installer, not a production shop installer. The application has SQLite backup/restore, but no service manager, MySQL backup path, or LAN configuration wizard.

Build a new installer with `packaging/windows/build-test-installer.ps1 -PackageVersion 0.2.4`. The build prints a randomly generated passkey; keep it private and pass it only to intended users. For an update, increment `PackageVersion` and provide the same `-InstallPasskey` value to keep the key unchanged. The executable includes the ClothEase icon; the desktop shortcut and browser favicon use the same clothing mark.

For development only, see the commands in [README.md](README.md). Do not expose `artisan serve` or the XAMPP control panel to the internet.

## Release packaging decision (provisional)

The current test ZIP installs the Laravel app under `%LOCALAPPDATA%\Programs\ClothEase`, stores its environment/database/storage under `%LOCALAPPDATA%\ClothEase`, runs migrations on install, and starts a loopback-only PHP server from a desktop shortcut. Reinstalling the same package preserves that data directory. This has not been verified on a clean machine, and the package does not bundle PHP. It is a test package, not the final customer installer. A production release still needs a pinned bundled runtime, service lifecycle, LAN/firewall setup, migration/rollback strategy, uninstall flow, backup/recovery verification, and clean-machine testing.

Before release, choose and pin PHP and MariaDB builds; keep MariaDB bound to loopback only; expose only the authenticated web server on a user-selected private LAN interface; generate secrets locally; run migrations and initial owner setup; add firewall rules limited to Private networks; and verify start/stop, logs, backup, restore and recovery after restart. A user should be able to decline LAN sharing.

## Data safety

Never remove shop data silently during uninstall. The installer must offer preserve/export versus delete, explain each choice, and require an explicit confirmation before deletion. The development build stores SQLite backups in private application storage, verifies checksums and database integrity, and creates a pre-restore snapshot. Production backups should also be copied off-device. MySQL backup, retention, off-device copy automation, and Windows Task Scheduler registration remain pending.

## Printer setup

Receipts use browser printing with compact 80 mm print CSS. Barcode labels print directly through the installed Windows driver. Install the Retsol R220 driver and configure its stock as 100 × 25 mm; each print row has two side-by-side 50 × 25 mm labels. The default Windows printer name is `Bar Code Printer R220`; set `CLOTHEASE_BARCODE_PRINTER` in `.env` if Windows uses another name. Direct label alignment still needs verification on the target machine.
