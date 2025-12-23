# Override Tracker App


A Laravel 12 application for preparing and deploying Minecraft modpack releases by comparing a modpack against a remote server snapshot, applying override rules, and syncing changed files over SFTP. The app provides a simple Web UI for server admins.

## Highlights
- Compare a modpack (zip or extracted folder) with a server snapshot to detect added, removed, and modified files.
- Apply override rules per-path using plain text replace, JSON merge, or YAML merge.
- Deploy changed files via SFTP and optionally remove files deleted in the new modpack.
- Keep structured release summaries for review and audit.
- Download modpacks from CurseForge or Feed The Beast

## Stack & Versions
- PHP: 8.3+
- Laravel: 12
- Filament: v4

## Prerequisites
- Composer 2
- for dev only: (Node.js 18+ and npm/yarn)
- SFTP-accessible remote server (password or key auth)

## Quick Setup (developer)

```bash
# From project root
composer install
cp .env.example .env
php artisan key:generate

# default env will use sqlite
php artisan migrate --force
npm install
# For development with HMR
npm run dev
# To build production assets
# npm run build

# Creating a user
php artisan filament:make-user
```

## Run the app locally

```bash
php artisan serve
```

## Core Concepts
- Server: SFTP connection details and the remote root path plus optional exclude globs.
- Release: A prepared import of a modpack tied to a server and label; stores paths for `new`, `remote`, and `prepared` states and a structured summary.
- Override Rule: A rule that matches paths and applies a payload (text, JSON, or YAML) or a file operation (add, remove, skip) during prepare.
- FileChange: Records added/removed/modified files, checksums, and optional text diffs for text files.

## Using the Web UI
- Log in, create a `Server` with SFTP details and optional exclude patterns.
- Add `Override Rules` to modify files during the prepare stage.
- Create a `Release` by uploading a modpack zip or selecting a new version from the pack provider. The system snapshots the server, computes diffs, applies overrides, and stores the prepared release.
- Review the release diff and deploy (Maintainer/Admin), see a live log of the changes.
- Some issues when updating? There's a backup of the remote downloaded during the preparation available.


## Testing
Lots of tests vibe coded by Github Copilot that should cover most flows and actions.

```bash
php artisan test
```

# Developer Notes

- Storage layout: modpack files are stored under `storage/app/modpacks/<release_id>/{new,remote,prepared}`.
- Follow project Pint formatting: run `composer run format` before committing.

## Troubleshooting
- UI not reflecting changes: ensure `npm run dev` is running or run `npm run build`.
- SFTP connection issues: confirm host/port/credentials and that the key file (if used) is readable by the process.
- Permission errors: ensure `storage/` and `bootstrap/cache/` are writable by the web user.

