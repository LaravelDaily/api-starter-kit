# Laravel API Starter Kit

A Laravel **API-only** starter kit: token-based authentication and account
management as plain, readable Laravel code. No front-end, no Vite, no Fortify —
just controllers, Form Requests, API Resources, and a green test suite.

**Disclaimer**: this starter kit was built fully by Claude Fable 5 model, after
deep planning session and a few building iterations. So it wasn't a one-shot or
vibe-coding, but LLM did all the implementation work.

## Features

- **Token auth** via [Laravel Sanctum](https://laravel.com/docs/sanctum) personal
  access tokens — DB-backed, revocable, works for mobile, SPA, and CLI clients.
- **Code-based password reset** — the API emails a 6-digit code, the client posts
  it back. No reset links, no `FRONTEND_URL`, no assumption about your front end.
- **OpenAPI docs** via [Scramble](https://scramble.dedoc.co) at `/docs/api`.
- **Fully tested** — a Pest feature test for every endpoint; `php artisan test`
  is green immediately after install.

## Installation

```bash
laravel new my-app --using=laraveldaily/api-starter-kit
```

The installer asks two questions:

- **Which testing framework do you prefer?** Pick **Pest** — the kit's test
  suite is already written with it.
- **Would you like to run npm install and npm run build?** Answer **No** —
  this kit ships no front-end, so there is nothing to install or build. (The
  installer asks this for every starter kit; it cannot be skipped from the
  kit's side on current installer releases.)

Or with Composer directly (no prompts at all):

```bash
composer create-project laraveldaily/api-starter-kit my-app
```

Either way you get a generated `APP_KEY`, an SQLite database, and migrated
tables out of the box. Start the server with:

```bash
php artisan serve
```

The mailer defaults to `MAIL_MAILER=log`, so password reset codes land in
`storage/logs/laravel.log` — the whole auth flow works with zero mail setup.

## Endpoints

All routes live under `/api/v1`. Authenticated routes expect an
`Authorization: Bearer <token>` header. All responses (including errors) are JSON.

| Method | Path | Auth | Purpose |
|---|---|---|---|
| POST | `/api/v1/register` | – | Create an account, receive a token |
| POST | `/api/v1/login` | – | Issue a token for valid credentials |
| POST | `/api/v1/forgot-password` | – | Email a 6-digit reset code |
| POST | `/api/v1/reset-password` | – | Set a new password using the code |
| POST | `/api/v1/logout` | ✓ | Revoke the current token |
| GET | `/api/v1/user` | ✓ | Get the authenticated user |
| PUT | `/api/v1/user` | ✓ | Update name and/or email |
| PUT | `/api/v1/user/password` | ✓ | Change password (revokes other tokens) |
| DELETE | `/api/v1/user` | ✓ | Delete the account (password confirmed) |
| GET | `/api/v1/tokens` | ✓ | List active tokens |
| DELETE | `/api/v1/tokens/{id}` | ✓ | Revoke one token |
| DELETE | `/api/v1/tokens` | ✓ | Revoke all tokens except the current one |

### Register

`device_name` is optional everywhere a token is issued; it defaults to `api`.

```bash
curl -X POST http://localhost:8000/api/v1/register \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{
    "name": "Jane Doe",
    "email": "jane@example.com",
    "password": "secret-password",
    "password_confirmation": "secret-password",
    "device_name": "iphone-15"
  }'
```

```json
{
  "token": "1|x6tLxhBuYDQwGesgZQGMqLpdo1Wt8h9dLn1to2hX",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "name": "Jane Doe",
    "email": "jane@example.com",
    "created_at": "2026-06-11T12:00:00.000000Z",
    "updated_at": "2026-06-11T12:00:00.000000Z"
  }
}
```

### Login

```bash
curl -X POST http://localhost:8000/api/v1/login \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"email": "jane@example.com", "password": "secret-password"}'
```

Returns the same `{ token, token_type, user }` shape as register. Invalid
credentials return `422` with an `email` error. Login is throttled to 5
attempts per minute per email + IP.

### Logout

Revokes the token used to authenticate the request.

```bash
curl -X POST http://localhost:8000/api/v1/logout \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>"
```

Returns `204 No Content`.

### Forgot password

```bash
curl -X POST http://localhost:8000/api/v1/forgot-password \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"email": "jane@example.com"}'
```

```json
{ "message": "If the email exists, a reset code has been sent." }
```

The response is identical whether or not the account exists, so callers cannot
enumerate emails. When the account exists, a 6-digit code is emailed (logged,
by default) and is valid for **15 minutes** (`config/auth.php` →
`passwords.users.expire`).

### Reset password

```bash
curl -X POST http://localhost:8000/api/v1/reset-password \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{
    "email": "jane@example.com",
    "code": "123456",
    "password": "new-secret-password",
    "password_confirmation": "new-secret-password"
  }'
```

```json
{ "message": "Password has been reset." }
```

A successful reset deletes the code (single use) and **revokes every token**
the user holds. A missing, expired, or wrong code always returns the same
generic `422`:

```json
{ "message": "...", "errors": { "code": ["The reset code is invalid or has expired."] } }
```

> **Brute-force note:** the 6-digit code is protected by throttling (5 requests
> per minute per email + IP), the 15-minute expiry, and single-use deletion.
> Tighten further (longer code, attempt counter) if your threat model needs it.

### Get the current user

```bash
curl http://localhost:8000/api/v1/user \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>"
```

```json
{
  "data": {
    "id": 1,
    "name": "Jane Doe",
    "email": "jane@example.com",
    "created_at": "2026-06-11T12:00:00.000000Z",
    "updated_at": "2026-06-11T12:00:00.000000Z"
  }
}
```

### Update profile

Send only the fields you want to change. Emails are normalized to lowercase.

```bash
curl -X PUT http://localhost:8000/api/v1/user \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -H "Authorization: Bearer <token>" \
  -d '{"name": "Jane D.", "email": "jane.d@example.com"}'
```

Returns the updated user. Email changes take effect immediately — there is no
verification flow in v1 (see [Future add-ons](#future-add-ons)).

### Change password

```bash
curl -X PUT http://localhost:8000/api/v1/user/password \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -H "Authorization: Bearer <token>" \
  -d '{
    "current_password": "secret-password",
    "password": "new-secret-password",
    "password_confirmation": "new-secret-password"
  }'
```

```json
{ "message": "Password updated." }
```

Every **other** token is revoked; the token that made the request keeps working.

### Delete account

```bash
curl -X DELETE http://localhost:8000/api/v1/user \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -H "Authorization: Bearer <token>" \
  -d '{"password": "secret-password"}'
```

Returns `204 No Content`. All tokens are revoked and the user row is deleted.

### List tokens

```bash
curl http://localhost:8000/api/v1/tokens \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>"
```

```json
{
  "data": [
    {
      "id": 2,
      "name": "iphone-15",
      "abilities": ["*"],
      "last_used_at": "2026-06-11T12:34:56.000000Z",
      "created_at": "2026-06-11T12:00:00.000000Z"
    }
  ]
}
```

Token hashes are never exposed.

### Revoke one token

```bash
curl -X DELETE http://localhost:8000/api/v1/tokens/2 \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>"
```

Returns `204 No Content`, or `404` when the token does not exist or belongs to
another user.

### Revoke all other tokens ("log out everywhere")

```bash
curl -X DELETE http://localhost:8000/api/v1/tokens \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>"
```

Returns `204 No Content`. Every token except the current one is revoked.

## Error shapes

All `/api/*` errors render as JSON:

| Status | Body |
|---|---|
| 422 validation | `{ "message": "...", "errors": { "field": ["..."] } }` |
| 401 unauthenticated | `{ "message": "Unauthenticated." }` |
| 404 not found | `{ "message": "..." }` |
| 429 throttled | `{ "message": "Too Many Attempts." }` + `Retry-After` header |
| 500 | `{ "message": "Server Error." }` (trace only with `APP_DEBUG=true`) |

## Rate limiting

Defined in `app/Providers/AppServiceProvider.php`:

| Limiter | Limit | Applied to |
|---|---|---|
| `api` | 60/min per user (or IP) | everything else |
| `login` | 5/min per email + IP | `/login` |
| `password` | 5/min per email + IP | `/forgot-password`, `/reset-password` |

## API documentation

Interactive OpenAPI 3.1 docs are generated from the code by
[Scramble](https://scramble.dedoc.co):

- `GET /docs/api` — UI
- `GET /docs/api.json` — OpenAPI document

Both are restricted to the `local` environment by the `viewApiDocs` gate in
`app/Providers/AppServiceProvider.php`; relax that gate to expose them elsewhere.

## Token expiration

Tokens do not expire by default. Set `expiration` (minutes) in
`config/sanctum.php` to give every token a lifetime.

## CORS

`config/cors.php` allows all origins for `api/*` — safe for token auth because
no cookies or credentials are involved. Pin `allowed_origins` in production if
you prefer.

## Development

```bash
composer dev    # serve + queue + logs (requires npx for concurrently)
composer test   # pint --test, phpstan, pest
```

## Future add-ons

Deliberately not in v1, and designed to bolt on cleanly:

- **Email verification** — re-add `MustVerifyEmail` to `User` plus the
  verify/resend endpoints; the `email_verified_at` column already exists.
- **Two-factor authentication** (TOTP + recovery codes).
- **Social login** via Laravel Socialite.
- **OAuth2** via Laravel Passport (third-party authorization).
- **Teams / organizations**, roles & permissions.

## License

The MIT License.
