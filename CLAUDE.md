# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# First-time setup
composer setup

# Development (runs server + queue worker + Vite concurrently)
composer dev

# Run tests
composer test

# Run a single test file
php artisan test tests/Feature/ExampleTest.php

# Frontend only
npm run dev
npm run build

# Code formatting
./vendor/bin/pint

# Database
php artisan migrate
php artisan migrate:fresh --seed
```

## Architecture

**BAC Office** is a Laravel 12 government procurement management system. It manages the full lifecycle of public bidding: project creation → open bidding → bid review → contract award.

### Roles & Middleware

Three user roles enforced via middleware in `bootstrap/app.php`:

| Middleware alias | Class | Checks |
|---|---|---|
| `admin` | `AdminMiddleware` | `role === 'admin'` |
| `staff` | `StaffMiddleware` | `role === 'staff'` |
| `approved.bidder` | `ApprovedBidderMiddleware` | `role === 'bidder'` + `status === 'active'` + `approval_status === 'approved'` on the `Bidder` profile |

Routes are grouped by role in `routes/web.php`. Staff and bidder routes are separate from admin routes — staff cannot access admin routes and vice versa.

### Controllers

- `AuthController` — login, registration, password reset, logout
- `AdminController` — full CRUD for projects, bids, users, awards, assignments, reports, notifications
- `StaffController` — bid validation/recommendation, project status updates, reports
- `BidderController` — browse projects, submit bids, manage company profile/documents
- `PublicProcurementController` — unauthenticated public procurement listing and awards

### Key Models & Relationships

```
User → hasMany(Bid), hasOne(Bidder profile), hasMany(LoginLog)
Project → hasMany(Bid), hasOne(Award), hasMany(ProjectDocument), hasMany(Assignment)
Bid → belongsTo(User), belongsTo(Project), hasOne(Award)
Bidder → belongsTo(User)  [one-to-one, holds approval status + company info]
```

Project statuses: `draft` → `approved_for_bidding` → `open` → `closed` → `awarded`

### Support Classes (`app/Support/`)

- **`Uploads`** — storage abstraction over local/S3. Use `Uploads::url($path)` for file URLs; it handles both legacy `public/` paths and S3 temp URLs.
- **`DocumentPreview`** — renders PDF/image/DOCX/text files for inline preview in the browser.
- **`LoginAudit`** — records login attempts to `login_logs` table.
- **`SystemNotification`** — creates in-app notifications for individual users, by role, or in bulk; tracks unread counts and mark-read state.

### File Storage

Controlled by `UPLOADS_DISK` env var (`local` or `s3`). The `Uploads` support class abstracts over this — always use it rather than `Storage` directly when dealing with user-uploaded files. Bidder document max size is set by `BAC_BIDDER_DOCUMENT_MAX_KB` (default 20 MB).

### Auth Endpoints

`POST /login` and `POST /register` always return JSON — never redirects. The response shape is `{ ok, message, redirect?, tab?, errors? }`. `GET /login` redirects to the homepage with an `auth_tab` session key to open the correct modal tab; there is no standalone login page.

### Frontend

Vite 7 + Tailwind CSS 4. Entry points defined in `vite.config.js`:
- `resources/css/{app,home,dashboard,contact}.css`
- `resources/js/{app,dashboard}.js`

Blade templates are organized under `resources/views/` by role: `admin/`, `staff/`, `bidder/`, `pages/` (public).

### Testing

Tests use Pest 3 with `pestphp/pest-plugin-laravel`. Test files live in `tests/Feature/`. The `composer test` script clears config cache before running to avoid stale env issues.

`RefreshDatabase` is **not** applied globally — each test file opts in with `uses(RefreshDatabase::class)` at the top. New test files must include this line to get database isolation.

Pest closures don't have a `$this` pointing to `TestCase`. Use the `testCase()` helper (defined in `tests/Pest.php`) to access HTTP test methods:

```php
it('does something', function () {
    $response = testCase()->get('/');
    testCase()->assertAuthenticated();
});
```

### Database

MySQL. Default DB name: `bac_office`. Sessions, cache, and queues all use the database driver — ensure `php artisan queue:listen` is running in development (included in `composer dev`).
