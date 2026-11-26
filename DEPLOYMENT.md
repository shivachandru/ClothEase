# Deployment notes

## Local development

Use PHP 8.2+, Composer 2, and SQLite. `APP_DEBUG` must be false for shared use. Do not commit `.env` or real shop databases. The built-in Laravel server is for local development only.

## Local shop release (not ready)

The intended deployment is an offline-capable Laravel application with a local PHP web runtime and MariaDB bound only to loopback. LAN clients should reach only the web server and must authenticate. Do not expose database credentials, the MariaDB port, or the development server to the network. A Windows installer/server manager, firewall policy, updater, backup and restore, service supervision and clean install test remain pending.

## Optional cloud hosting (future)

Use HTTPS, a supported MySQL/MariaDB service, `APP_DEBUG=false`, protected environment secrets, session cookies marked secure, restricted file storage, database backups and least-privilege DB credentials. Do not enable a remote sync that can overwrite local shop transactions. A future sync protocol needs immutable transaction IDs, idempotent ingestion, retries, encrypted transport, explicit conflict reporting and no destructive remote-to-local overwrite.

No cloud deployment has been performed or tested.
