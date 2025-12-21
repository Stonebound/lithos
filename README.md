# Override Tracker App

A Laravel 12 web app to ingest Minecraft modpack sources (zip or folder), snapshot a remote server via SFTP, compute diffs, apply override rules (text/JSON/YAML), and deploy changes with optional deletion of removed files. Built with Breeze + Livewire.

## Features
- Diff: Compare server snapshot vs new modpack, detect added/removed/modified files.
- Overrides: Apply path-pattern rules with text replace, JSON merge, YAML merge.
- Deploy: Sync changed files via SFTP, optionally remove files that were deleted.
- Summary: Store structured release summaries for review.
- UI + CLI: Auth-gated UI for non-CLI users; Artisan commands for automation.

## Prerequisites
- PHP 8.3+
- Composer 2+
- Node.js 18+ and npm
- SFTP-accessible Minecraft server (user/password or key)

## Setup

```bash
# From project root
composer install
cp .env.example .env
php artisan key:generate

# Configure your DB in .env (SQLite/MySQL/Postgres)
# For quick SQLite dev:
# echo "DB_CONNECTION=sqlite" >> .env
# touch database/database.sqlite

php artisan migrate
npm install
npm run dev
```

Start the app:

```bash
php artisan serve
```

Then open http://localhost:8000 and log in.

## Authentication
- Breeze Livewire provides `/login`, `/register`, email verification, password reset.
- Roles: `users.role` supports `viewer`, `maintainer`, `admin`.
- Maintainers/Admins can deploy releases.

Create a user quickly:

```bash
php artisan user:create --name="Maintainer" --email=you@example.com --password=secret --role=maintainer --no-interaction
```

## Core Concepts
- Servers: SFTP connection and base remote path; optional exclude glob patterns.
- Releases: A modpack import tied to a server and version label; stores source/extracted/prepared paths and summary.
- Override Rules: Path pattern + payload (text/JSON/YAML) applied during prepare.
- File Changes: Added/removed/modified entries with checksums and optional text diff summary.

## Using the UI
1. Log in.
2. Create a Server (host, port, username, auth type, remote root path, excludes).
3. Add Override Rules if desired.
4. Create a Release:
	- Upload a modpack zip (or specify a source folder if supported).
	- The app snapshots the server via SFTP, computes diffs, applies overrides.
5. Review the diff summary on the releases page.
6. Deploy (Maintainer/Admin only) — optional deletion of removed files.

## CLI Commands
These exist for automation; check help for flags:

```bash
# Prepare and deploy
php artisan modpack:prepare --server=SERVER_ID --zip=/path/to/modpack.zip --label=1.0.0 --no-interaction
php artisan modpack:deploy --release=RELEASE_ID --delete-removed --no-interaction

# Server configuration
php artisan server:add --name=Prod --host=example.org --port=22 --username=mc --auth=password --password=secret --root=/srv/mc --no-interaction
php artisan server:exclude --server=SERVER_ID --pattern="storage/**" --pattern="logs/**" --no-interaction

# Overrides
php artisan override:add --name="Enable feature" --pattern="config/*.json" --type=json --payload='{"feature":{"enabled":true}}' --no-interaction
```

List all commands:

```bash
php artisan list
```

## Testing
Run the feature and unit tests:

```bash
php artisan test
```

Filter by test name:

```bash
php artisan test --filter=ReleaseFlowTest
```

## Troubleshooting
- UI not updating: ensure `npm run dev` is running; or build with `npm run build`.
- Vite asset error: run `npm run build` or start `npm run dev`.
- SFTP issues: verify host/port/auth in the server record; key file readable; remote root path exists.
- Permissions: ensure `storage/` and `bootstrap/cache/` are writable.

## Notes
- Storage layout: modpacks live under `storage/app/modpacks/<release_id>/{new,current,prepared}`.
- Large diffs: binary detection skips text summaries for JARs and other binaries.
- JSON/YAML merges are shallow by default; adjust payloads accordingly.
