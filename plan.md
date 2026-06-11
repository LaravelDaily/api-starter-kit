# Laravel 13 API-Only Starter Kit — Implementation Plan

**Package:** `laraveldaily/api-starter-kit`
**Install command:** `laravel new my-app --using=laraveldaily/api-starter-kit`

This document is a complete, self-contained build spec. An engineer can implement
the kit end-to-end from this file alone.

---

## 1. What we are building

A Laravel 13 **API-only** application skeleton, published to Packagist, that other
developers install as a starter kit. It ships token-based authentication and account
management as plain, readable Laravel code — no front-end, no Fortify.

### Hard constraints
- **API-only.** No Blade (except mail templates), no Vite, no `package.json`, no
  `npm`, no front-end asset compilation.
- **No Laravel Fortify.** Auth controllers are hand-written. (Fortify assumes
  session/redirect flows; bending it to pure JSON is more work than owning the code.)
- **Token auth via Laravel Sanctum** personal access tokens — DB-backed, revocable,
  ability-aware. Works for mobile, SPA, and third-party clients.
- **Lean architecture.** Grouped controllers + Form Requests + API Resources. No
  Action-class layer — the auth surface is small and an extra indirection layer
  would be over-engineering for v1.
- **Fully tested.** A Pest feature test for every endpoint. `php artisan test` must
  be green immediately after install.

---

## 2. Locked decisions

| # | Topic | Decision |
|---|---|---|
| 1 | Auth mechanism | Sanctum personal access tokens. Not JWT, Passport, or SPA cookie mode. |
| 2 | Routing | `/api/v1/*` prefix in a **single** `routes/api.php`. Controllers namespaced `App\Http\Controllers\Api\V1` so a `v2` file can be split out later without moving code. |
| 3 | Email verification | **Not in v1.** No verify/resend endpoints, no `MustVerifyEmail`, no `verified` middleware. The `email_verified_at` column **stays** in the users migration so verification is a clean bolt-on later. |
| 4 | Password reset | **In v1, code-based** (forgot + reset). `forgot-password` emails a short numeric code; `reset-password` takes email + code + new password. **No reset link, no `FRONTEND_URL`** — tech-stack-neutral for web, mobile, and CLI clients. Mailer defaults to `log`, so it works with zero mail config. |
| 5 | Architecture | Grouped controllers, Form Requests, API Resources. No Action classes. |
| 6 | API docs | Ship `dedoc/scramble` — OpenAPI 3.1 docs generated from the code, viewable at `/docs/api`. |
| 7 | Deferred | 2FA/TOTP, passkeys, social login, teams/orgs, roles/permissions. Documented in §11, not built. |

### Conventions applied throughout
- **Token-issuing response** (register, login): HTTP `201`/`200` with
  `{ "token": "<plain-text>", "token_type": "Bearer", "user": <UserResource> }`.
- **`device_name`** is optional on register/login; when absent, default the token
  name to `"api"` so the token list stays readable.
- **"Log out everywhere"** is `DELETE /api/v1/tokens` (revoke all but current). There
  is no separate `logout-all` route.
- **Token expiration** relies on Sanctum's `expiration` config value; no per-token
  `expires_at` endpoint in v1.
- **Emails are normalized to lowercase** before persistence/lookup.
- **Password rules** use `Illuminate\Validation\Rules\Password::defaults()`.

---

## 3. Packaging mechanics (no Maestro needed)

A community starter kit is **a full Laravel application skeleton published to
Packagist** with `"type": "project"`. `laravel new my-app --using=laraveldaily/api-starter-kit`
makes the Laravel installer run `composer create-project` against the package, then
handles `.env`, `php artisan key:generate`, and the DB prompts.

`laravel/maestro` is **not** required — it is Laravel's internal tool for managing
the variants of their *official* kits. A single-variant community kit just needs to
be a valid Packagist `project` package.

### Repo requirements
- Built from `laravel/laravel` (the app skeleton), with the front-end stripped (§4).
- `composer.json`:
  - `"name": "laraveldaily/api-starter-kit"`
  - `"type": "project"`
  - `post-create-project-cmd` runs: `@php artisan key:generate --ansi`, create
    `database/database.sqlite` if missing, `@php artisan migrate --graceful --ansi`.
  - No front-end scripts.
- `.env.example` tuned for API use (§9).
- A `README.md` documenting every endpoint with `curl` examples.
- Published to Packagist with a tagged stable release (publishing is a **manual,
  later step** — not part of this build).

---

## 4. Front-end strip-out checklist

Delete:
- `vite.config.js`, `package.json`, `package-lock.json`
- `resources/js/`, `resources/css/`
- `tailwind.config.js`, `postcss.config.js`
- `resources/views/welcome.blade.php`

Keep:
- `resources/views/` only for mail notification templates (password reset).

Adjust:
- `routes/web.php` → reduce to a minimal health/landing response (or leave the
  framework default `/up` health check, which lives in `bootstrap/app.php`).
- Remove every `npm`/Vite mention from `composer.json` scripts and the README.

---

## 5. Endpoints (12 total)

Base path `/api/v1`. Public group has no middleware; the authenticated group uses
`auth:sanctum`.

### Public
| Method | Path | Controller@method | Throttle | Purpose |
|---|---|---|---|---|
| POST | `/register` | `AuthController@register` | `throttle:api` | Create user, issue token. |
| POST | `/login` | `AuthController@login` | `throttle:login` | Verify credentials, issue token. |
| POST | `/forgot-password` | `AuthController@forgotPassword` | `throttle:password` | Email a short reset code (no link). |
| POST | `/reset-password` | `AuthController@resetPassword` | `throttle:password` | Set new password from email + code; revoke all tokens. |

### Authenticated (`auth:sanctum`)
| Method | Path | Controller@method | Purpose |
|---|---|---|---|
| GET | `/user` | `UserController@show` | Current user as `UserResource`. |
| PUT | `/user` | `UserController@update` | Update name/email (plain update; no re-verification). |
| PUT | `/user/password` | `UserController@updatePassword` | Change password; revoke other tokens. |
| DELETE | `/user` | `UserController@destroy` | Delete account; requires password confirmation. |
| POST | `/logout` | `AuthController@logout` | Revoke the **current** token only. |
| GET | `/tokens` | `TokenController@index` | List the user's active tokens. |
| DELETE | `/tokens/{id}` | `TokenController@destroy` | Revoke one token (ownership-checked). |
| DELETE | `/tokens` | `TokenController@destroyOthers` | Revoke all tokens except the current one. |

---

## 6. Per-endpoint behaviour, validation & responses

`U:` = unique on `users` table. Password reset/credential lookups normalize email to
lowercase first.

### POST /register
- **Validation:** `name` required|string|max:255 · `email` required|email|max:255|lowercase|U
  · `password` required|confirmed|`Password::defaults()` · `device_name` nullable|string|max:255.
- **Action:** create user with `Hash::make($password)`; fire `Registered` event;
  `createToken($device_name ?? 'api')`.
- **Response 201:** `{ token, token_type: "Bearer", user: UserResource }`.

### POST /login
- **Validation:** `email` required|email · `password` required|string · `device_name` nullable|string|max:255.
- **Action:** look up user by lowercased email; if missing or `Hash::check` fails,
  throw `ValidationException::withMessages(['email' => [__('auth.failed')]])`. On
  success, `createToken(...)`.
- **Response 200:** `{ token, token_type: "Bearer", user: UserResource }`.

### POST /logout
- **Action:** `$request->user()->currentAccessToken()->delete()`.
- **Response 204.**

### POST /forgot-password  (code-based — no link)
- **Validation:** `email` required|email.
- **Action:** look up user by lowercased email. **If found:** generate a 6-digit
  numeric code (`str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT)`),
  store `Hash::make($code)` + `created_at` in the `password_reset_tokens` table
  (`updateOrInsert(['email' => $email], [...])`, one live code per email), and send a
  `PasswordResetCode` notification containing the code. **If not found:** do nothing.
- **Response 200 (always the same):** `{ message: "If the email exists, a reset code has been sent." }`
  — identical whether or not the email exists (no user enumeration).

### POST /reset-password  (code-based)
- **Validation:** `email` required|email · `code` required|string ·
  `password` required|confirmed|`Password::defaults()`.
- **Action:**
  1. Fetch the `password_reset_tokens` row for the lowercased email. Missing →
     `422` generic error (see below).
  2. Expiry check: reject if older than `config('auth.passwords.users.expire')`
     minutes (set to **15** in config). Expired rows are deleted.
  3. `Hash::check($code, $row->token)` — mismatch → `422` generic error.
  4. On success: set the new hashed password, **delete the reset row** (single use),
     **revoke all of the user's tokens** (`$user->tokens()->delete()`), and fire
     `Illuminate\Auth\Events\PasswordReset`.
- **Response 200:** `{ message: "Password has been reset." }`.
- **Invalid/expired code → 422:** `{ "message": "...", "errors": { "code": ["The reset code is invalid or has expired."] } }`
  — one generic message for missing/expired/mismatched, so a caller can't probe which
  emails have pending codes.
- **Brute-force note:** a 6-digit code is protected by `throttle:password` (5/min per
  email+IP), the 15-minute expiry, and single-use deletion. That combination is
  adequate for v1; document it so consumers can tighten (e.g. longer code or an
  attempt counter) if needed.

### GET /user
- **Response 200:** `UserResource`.

### PUT /user
- **Validation:** `name` sometimes|string|max:255 ·
  `email` sometimes|email|max:255|lowercase|`Rule::unique('users')->ignore($user->id)`.
- **Action:** update provided fields. (No verification flow — email changes take
  effect immediately.)
- **Response 200:** `UserResource`.

### PUT /user/password
- **Validation:** `current_password` required|`current_password` ·
  `password` required|confirmed|`Password::defaults()`.
- **Action:** set new hashed password; revoke **other** tokens
  (`$user->tokens()->where('id','!=',$current->id)->delete()`), keeping the current one.
- **Response 200:** `{ message: "Password updated." }`.

### DELETE /user
- **Validation:** `password` required|`current_password`.
- **Action:** revoke all tokens, then `$user->delete()`.
- **Response 204.**

### GET /tokens
- **Response 200:** collection of `TokenResource`
  (`id, name, abilities, last_used_at, created_at`). Never expose token hashes.

### DELETE /tokens/{id}
- **Action:** find the token among `$user->tokens()`; `404` if not found/owned by
  someone else; delete it.
- **Response 204.**

### DELETE /tokens
- **Action:** `$user->tokens()->where('id','!=',$current->id)->delete()`.
- **Response 204.**

---

## 7. JSON envelope & error handling

All `/api/*` responses are JSON, including errors and unauthenticated requests. Set
this once in `bootstrap/app.php`:

```php
->withExceptions(function (Illuminate\Foundation\Configuration\Exceptions $exceptions): void {
    $exceptions->shouldRenderJsonWhen(
        fn (Illuminate\Http\Request $request, Throwable $e) =>
            $request->is('api/*') || $request->expectsJson()
    );
})
```

Resulting shapes (Laravel defaults, just rendered as JSON):

| Status | Body |
|---|---|
| 422 validation | `{ "message": "...", "errors": { "field": ["..."] } }` |
| 401 unauthenticated | `{ "message": "Unauthenticated." }` |
| 403 forbidden | `{ "message": "..." }` |
| 404 not found | `{ "message": "..." }` |
| 429 throttled | `{ "message": "Too Many Attempts." }` (with `Retry-After` header) |
| 500 | `{ "message": "Server Error." }` (full trace only when `APP_DEBUG=true`) |

No custom `ForceJson` middleware is needed — `shouldRenderJsonWhen()` covers it.

---

## 8. Architecture & wiring

### Directory layout
```
app/
  Http/
    Controllers/Api/V1/
      AuthController.php        # register, login, logout, forgotPassword, resetPassword
      UserController.php        # show, update, updatePassword, destroy
      TokenController.php       # index, destroy, destroyOthers
    Requests/Api/V1/
      RegisterRequest.php
      LoginRequest.php
      ForgotPasswordRequest.php
      ResetPasswordRequest.php
      UpdateProfileRequest.php
      UpdatePasswordRequest.php
      DeleteAccountRequest.php
    Resources/Api/V1/
      UserResource.php          # id, name, email, created_at, updated_at
      TokenResource.php         # id, name, abilities, last_used_at, created_at
  Models/User.php               # use HasApiTokens, Notifiable
routes/api.php                  # v1 prefix; public + auth:sanctum groups
```

### Sanctum setup
- Run `php artisan install:api` (installs Sanctum, publishes the
  `personal_access_tokens` migration, creates `routes/api.php`, and registers API
  routing in `bootstrap/app.php`).
- Add `Laravel\Sanctum\HasApiTokens` to the `User` model.
- `User` does **not** implement `MustVerifyEmail` in v1.
- Optionally publish Sanctum config; leave `expiration => null` (no expiry) by default.

### Password reset — code-based (no web link)
We deliberately **do not** use Laravel's `Password::sendResetLink` / `Password::reset`
broker, because its default notification builds a URL to the `password.reset` *web*
route — a web-frontend assumption an API-only kit should not make. Instead we hand-roll
a short flow over the existing `password_reset_tokens` table:

- **Notification:** `App\Notifications\PasswordResetCode` (a Notification class) renders
  a markdown mail template containing the plain code and its validity window. This is
  the **only** mail view in the project. With `MAIL_MAILER=log` the code lands in
  `storage/logs/laravel.log`, so the flow is testable with zero mail setup.
- **Storage:** reuse the framework's `password_reset_tokens` table
  (`email` PK, `token`, `created_at`) — store `Hash::make($code)` in `token`. One live
  code per email via `updateOrInsert`.
- **Expiry:** driven by `config('auth.passwords.users.expire')`; set it to `15`.
- **Single use:** delete the row on a successful reset.
- See §6 for the exact validation, the generic error message, and brute-force notes.

There is **no `FRONTEND_URL`** anywhere in the auth flow — the API emails a value, and
each client (web, iOS, Android, CLI) collects it however it wants.

### Named rate limiters
Define in `AppServiceProvider::boot()`:

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::for('api', fn (Request $r) =>
    Limit::perMinute(60)->by($r->user()?->id ?: $r->ip()));

RateLimiter::for('login', fn (Request $r) =>
    Limit::perMinute(5)->by(Str::lower((string) $r->input('email')) . '|' . $r->ip()));

RateLimiter::for('password', fn (Request $r) =>
    Limit::perMinute(5)->by(Str::lower((string) $r->input('email')) . '|' . $r->ip()));
```

Apply `throttle:login` / `throttle:password` per §5; `throttle:api` on the rest.

### Scramble (API docs)
- `composer require dedoc/scramble`.
- Default `api_path` is `api`, so it auto-discovers `/api/v1/*` — no config needed
  for discovery.
- Routes added: `/docs/api` (UI) and `/docs/api.json` (OpenAPI doc).
- **Access:** both routes are restricted to the `local` environment by default
  (`RestrictedDocsAccess` middleware + a `viewApiDocs` gate). To expose docs in other
  environments, define the gate in `AppServiceProvider::boot()`:
  ```php
  Gate::define('viewApiDocs', fn ($user = null) => app()->isLocal());
  ```
  Document this in the README so consumers know where to relax it.

---

## 9. Configuration & migrations

### Migrations
- Default `users` migration **kept as-is**, including `email_verified_at`.
- Default `password_reset_tokens` migration kept — reused to store the hashed reset
  **code** + `created_at` (see §8).
- `personal_access_tokens` migration from Sanctum.

### config/auth.php
- Set `passwords.users.expire` to `15` (minutes) — the reset-code validity window.

### .env.example (key values)
```
APP_NAME="API Starter Kit"
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
# (database/database.sqlite created by post-create-project-cmd)

MAIL_MAILER=log

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```
No `FRONTEND_URL` — the kit makes no assumption about the consumer's front end.

### CORS
- `config/cors.php`: cover the `api/*` paths. Default `allowed_origins` to `['*']`
  (safe here because token auth uses no cookies/credentials) and document that
  consumers should pin specific origins in production.

---

## 10. composer.json

- `require`: `php` (^8.3 or per L13 minimum), `laravel/framework:^13.0`,
  `laravel/sanctum`, `laravel/tinker`, `dedoc/scramble`.
- `require-dev`: `laravel/pint`, `larastan/larastan`, `laravel/pail`, `laravel/sail`,
  `pestphp/pest`, `pestphp/pest-plugin-laravel`, `fakerphp/faker`,
  `nunomaduro/collision`, `mockery/mockery`.
- Scripts:
  - `test`: `pint --test` → `larastan analyse` → `pest`.
  - `dev`: `php artisan serve` + `php artisan queue:listen` + `php artisan pail`
    (run concurrently; **no** Vite process).
  - `post-create-project-cmd`: `key:generate`, ensure `database/database.sqlite`
    exists, `migrate --graceful`.

---

## 11. Tests (Pest)

A feature test per endpoint covering happy path + validation failure + auth failure.
Use `Laravel\Sanctum\Sanctum::actingAs($user)` for authenticated requests and an
in-memory SQLite test DB (`phpunit.xml`).

Coverage checklist:
- **register** — success returns token+user; duplicate email → 422; weak password → 422.
- **login** — success; wrong credentials → 422; exceeds `throttle:login` → 429.
- **logout** — current token revoked; the token no longer authenticates.
- **forgot-password** — sends the `PasswordResetCode` notification (assert
  `Notification::fake()`) and writes a hashed row to `password_reset_tokens`; unknown
  email returns the same 200 message and sends nothing (no enumeration).
- **reset-password** — valid email+code sets the password, deletes the reset row, and
  revokes all tokens; wrong code → 422; expired code (older than 15 min) → 422.
- **GET /user** — returns the authenticated user; 401 without a token.
- **PUT /user** — updates name/email; email uniqueness honoured (ignoring self).
- **PUT /user/password** — wrong `current_password` → 422; success keeps current
  token, revokes others.
- **DELETE /user** — wrong password → 422; success deletes user and revokes tokens.
- **tokens index/destroy/destroyOthers** — listing, single revoke, ownership check
  (revoking another user's token → 404), revoke-others keeps current.

Target: `php artisan test` green immediately after `laravel new ... --using=...`.

---

## 12. Build order

1. Scaffold from `laravel/laravel`; strip front-end (§4).
2. `php artisan install:api`; add `HasApiTokens` to `User`; keep `email_verified_at`.
3. JSON envelope in `bootstrap/app.php` (§7).
4. `routes/api.php` with `v1` prefix and the public/auth groups; named limiters (§8);
   set `config/auth.php` `passwords.users.expire` to 15.
5. `AuthController` (register, login, logout) with Form Requests, `UserResource`,
   `TokenResource`.
6. Password reset (code-based): `forgotPassword`, `resetPassword`, and the
   `PasswordResetCode` notification + markdown mail template (§8).
7. `UserController` (show, update, updatePassword, destroy).
8. `TokenController` (index, destroy, destroyOthers).
9. `composer require dedoc/scramble`; verify `/docs/api`; add the `viewApiDocs` gate.
10. Pest tests for all 12 endpoints (§11).
11. `README.md` (endpoint docs + curl examples), `.env.example`, finalize
    `composer.json` (`type: project`, no front-end scripts).
12. *(Manual, later — not part of this build:)* tag a stable release, publish to
    Packagist, smoke-test `laravel new test-app --using=laraveldaily/api-starter-kit`.

---

## 13. Future add-ons (documented, not built)

- **Email verification** — re-add `MustVerifyEmail` to `User`, restore the
  verify/resend endpoints and the `verified` middleware. The `email_verified_at`
  column already exists.
- **Two-factor authentication** (TOTP + recovery codes).
- **Social login** via Laravel Socialite.
- **OAuth2** via Laravel Passport (for third-party authorization).
- **Teams / organizations**, roles & permissions.
