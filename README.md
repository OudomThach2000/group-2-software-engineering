# Community Issue Reporting System

**CSCI 841 — Advanced Software Engineering · Group 2**

A web application where residents report local problems (streetlights, roads, water, waste) with a
category, location and optional photo; office staff triage and assign them; field staff resolve them;
and a supervisor monitors everything on a dashboard.

- **Imaginary client (course requirement):** the public-works office of a city / commune — *the Office*.
- **Project home page:** https://oudomthach2000.github.io/group-2-software-engineering/
- **Proposal (living Google Doc):** https://docs.google.com/document/d/1rLSzS4BTaCEJ3jATr-zUR9lfRH-AW2YI12V8GX5O3TU/edit

## Tech stack

- **PHP** — server-side (plain PHP, no framework, for clarity)
- **MySQL** — relational database
- **HTML / CSS / vanilla JavaScript** — front end
- Runs locally with **XAMPP** (Apache + PHP + MySQL), or any PHP + MySQL setup.

> GitHub stores the source code and serves the static home page (GitHub Pages). The working app is
> **dynamic** (PHP + database), so it runs on a local server — it is not "hosted" on GitHub itself.

## Repository structure

```
group-2-software-engineering/
├── index.html                  # Project home page (served by GitHub Pages)
├── README.md
├── .gitignore
├── db/
│   ├── schema.sql              # MySQL schema — 6 tables            (Pichponleur)
│   └── seed.sql                # Sample categories + issues
├── app/
│   ├── config.example.php      # Copy to app/config.php (gitignored) and set DB credentials
│   ├── includes/
│   │   ├── db.php              # PDO database connection            (Pichponleur)
│   │   ├── auth.php            # Sessions & role checks             (Sethouday)
│   │   ├── header.php          # Shared page header / nav           (Sethouday)
│   │   └── footer.php          # Shared page footer                 (Sethouday)
│   ├── src/
│   │   └── notify.php          # UC7 notification service           (Rolando)
│   └── public/                 # <-- web root: point Apache/PHP here
│       ├── index.php           # App home                           (Pichponleur)
│       ├── submit.php          # UC1 Submit issue                   (Oudom)
│       ├── track.php           # UC2 Track issue                    (Sethouday)
│       ├── login.php           # UC6 Log in                         (Sethouday)
│       ├── register.php        # UC6 Register                       (Sethouday)
│       ├── logout.php          # UC6 Log out                        (Sethouday)
│       ├── staff/
│       │   └── issues.php      # UC4 Update / resolve               (Oudom)
│       ├── supervisor/
│       │   ├── triage.php      # UC3 Triage & assign                (Oudom)
│       │   └── dashboard.php   # UC5 Dashboard                      (Pichponleur)
│       ├── uploads/            # runtime photo uploads (gitignored)
│       └── assets/
│           ├── css/style.css   # Base styles                        (Sethouday)
│           └── js/app.js       # Front-end behaviour                (Sethouday)
└── docs/
    ├── use-case-diagram.png
    └── system-sequence-uc1.png
```

## Use cases → where the code lives

| Use case | File | Owner |
|---|---|---|
| UC1 Submit issue        | `app/public/submit.php`               | Oudom |
| UC2 Track issue         | `app/public/track.php`                | Sethouday |
| UC3 Triage & assign     | `app/public/supervisor/triage.php`    | Oudom |
| UC4 Update / resolve    | `app/public/staff/issues.php`         | Oudom |
| UC5 Dashboard           | `app/public/supervisor/dashboard.php` | Pichponleur |
| UC6 Register / log in   | `app/public/login.php`, `register.php`| Sethouday |
| UC7 Notify resident     | `app/src/notify.php`                  | Rolando |

## Local setup (XAMPP)

1. Install **XAMPP** (bundles Apache + PHP + MySQL) and start **Apache** and **MySQL**.
2. Clone this repo, then point the site at the **`app/public`** folder (either copy the project into
   `htdocs`, or set an Apache virtual host with document root `.../app/public`).
3. Create the database and load the schema (phpMyAdmin, or the MySQL client):
   ```sql
   CREATE DATABASE community_issues CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   USE community_issues;
   SOURCE db/schema.sql;
   SOURCE db/seed.sql;   -- optional sample data
   ```
4. Copy `app/config.example.php` to `app/config.php` and set your DB user/password.
5. Open the site (e.g. `http://localhost/`). Report and track pages work without an account; staff and
   supervisor pages require logging in. To make a supervisor: register an account, then
   `UPDATE users SET role='supervisor' WHERE email='you@example.com';`.

## How we work together (contributions)

The professor tracks each member's contribution from **GitHub history**, so:

- **Each member implements their own files** (see the ownership column) and **commits under their own
  GitHub identity** — please don't have one person commit everyone's work.
- Set your identity once: `git config user.name "Your Name"` and `git config user.email "the-email-on-your-github"`.
- Make **small, frequent commits** with clear messages, e.g. `UC1: add submit form validation and insert`.
- Pull before you start, push when a piece works.

## Course versions

Proposal v1 (SWEBOK Ch.1 Requirements) and v2 (Ch.7 Software Engineering Management) are in the living
Google Doc. First demo with **Version 1 code** is around **Oct 11** — target a working slice:
submit → triage → assign → resolve.

## Status

Initial project skeleton. Every use case has a stub file with a `TODO` describing exactly what to build.
