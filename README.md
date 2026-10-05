# CodeIgniter 4 Application Starter

## Start the server
PHP spark serve

### Automatic startup on Windows

On each computer, install PHP 8.2+ with the required extensions, run
`composer install`, and configure `.env` for that computer's PostgreSQL
hostname, port, database, username and password. Set `app.baseURL` to
`http://127.0.0.1:8080/`. PostgreSQL must already be running (locally as a
Windows service or on a reachable database server). Its installation path
is not needed by this launcher; it does not install or start PostgreSQL.

Run once from the project folder in Windows PowerShell:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\install-local-startup.ps1
```

The launcher finds the project relative to its script and locates PHP using
`BIOTIME_PHP`, then `PATH`, then common XAMPP, Laragon, Wamp64 and PHP folders
on filesystem drives. For a custom PHP installation, use:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\install-local-startup.ps1 -PhpPath "D:\tools\php\php.exe"
```

The installer starts the local development server and adds a startup shortcut
for the current Windows user. It runs quietly at sign-in. Re-run the installer
after moving the project or changing an explicitly selected PHP path. Remove
`BioTime Attendance.lnk` from `shell:startup` to disable automatic startup.
Server output is saved in `writable/logs/local-server-*.log`.
The launcher reads the host and port from each computer's `app.baseURL` in
`.env` using CodeIgniter's environment parser. For example,
`app.baseURL = 'http://localhost:8090/'` starts on port 8090. Use an address
belonging to the computer running the server. This local launcher supports
HTTP root URLs; HTTPS and subfolder hosting require Apache or IIS.
The configured port must be available; the launcher leaves an existing listener alone.
Database settings are read by CodeIgniter directly from `.env`; no database
credentials or PostgreSQL installation paths belong in these scripts.
Each installation needs its own existing database and matching connection
settings. Restart the project server after changing `.env`.
This setup supports Windows computers, not mobile devices, macOS or Linux.

## Server Requirements
PHP version 8.2 or higher is required, with the following extensions installed:
Path C:\xampp82\php
    extension=intl
    extension=pdo_pgsql
    extension=pgsql
    extension=zip

.env - Database connection



## Setup

Copy `env` to `.env` and tailor for your app, specifically the baseURL
and any database settings.

## Important Change with index.php

`index.php` is no longer in the root of the project! It has been moved inside the *public* folder,
for better security and separation of components.

This means that you should configure your web server to "point" to your project's *public* folder, and
not to the project root. A better practice would be to configure a virtual host to point there. A poor practice would be to point your web server to the project root and expect to enter *public/...*, as the rest of your logic and the
framework are exposed.

**Please** read the user guide for a better explanation of how CI4 works!

## Repository Management

We use GitHub issues, in our main repository, to track **BUGS** and to track approved **DEVELOPMENT** work packages.
We use our [forum](http://forum.codeigniter.com) to provide SUPPORT and to discuss
FEATURE REQUESTS.

This repository is a "distribution" one, built by our release preparation script.
Problems with it can be raised on our forum, or as issues in the main repository.


