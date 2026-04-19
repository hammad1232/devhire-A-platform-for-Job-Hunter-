# DevHire

DevHire is a PHP and MySQL developer hiring marketplace where clients post jobs and developers apply with profiles, proposals, gamification, and AI-assisted tools.

## Features

- Role-based registration and login for clients and developers
- Client dashboard for posting jobs and reviewing proposals
- Developer dashboard for profile management and applications
- AI career advisor, job description enhancer, and proposal generator powered by Gemini Gemma
- Skill matching, reputation scores, points, levels, and badges
- Responsive Bootstrap 5 UI with light/dark mode toggle

## Requirements

- PHP 8.1+
- MySQL 8+
- cURL enabled in PHP
- Bootstrap loaded from CDN

## Setup

1. Create a MySQL database named `devhire`.
2. Import `database/devhire.sql`.
3. Edit `config.php` with your database connection and API credentials.
4. Keep `config.php` outside public sharing and fill in your live values before deployment.
5. Ensure the web root points to the project folder or set `APP_BASE_PATH` correctly if deployed in a subfolder.
6. Optional: run `database/admin_privileges.sql` to create a dedicated DB login with CRUD access.

## Deployment Notes

- GitHub works for source control and collaboration.
- Netlify can only preview static frontend output. It cannot run PHP.
- Use PHP hosting such as 000webhost, InfinityFree, or cPanel for the full application.
- Keep `/uploads` and `config.php` private if it contains live secrets.

## AI Configuration

The AI pages use Gemini via PHP cURL. The app defaults to `gemma-3-1b-it` and can fall back to other free Gemma models if needed. If no API key is configured in `config.php`, the pages fall back to safe template-based output so the app still works.
