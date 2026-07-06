# Production Readiness Checklist

This tracks what's already fixed in code/config vs. what's a deploy-time
environment value that must be set when this app is actually deployed. None of
the env-only items below have been changed in the current dev `.env` — flipping
them now would disrupt active development, and they're inherently specific to
the real deployment target anyway.

## Already fixed (code/config)

- **CORS** (`backend/config/cors.php`) — previously no config file existed at
  all, so the app silently ran on the framework's wildcard default
  (`allowed_origins: ['*']`). Now published and env-driven via
  `CORS_ALLOWED_ORIGINS` (comma-separated), falling back to `FRONTEND_URL`.
- **Rate limiting** — previously only auth/register/contact/demo routes were
  throttled; every other authenticated route (all tenant CRUD, users, roles,
  platform admin) had none, and no `'api'` limiter was even registered. Added
  a 60/min-per-user (or per-IP if unauthenticated) `'api'` limiter in
  `AppServiceProvider::boot()`, applied via `throttle:api` to the whole
  `auth:sanctum` route group in `routes/api.php`.

## Deploy-time environment values (not yet changed — set these before going live)

- [ ] `APP_DEBUG=false` — currently `true`. Laravel hides stack traces from
      API responses automatically when this is false; no code change needed,
      just flip the value.
- [ ] `LOG_LEVEL=warning` (or `error`) — currently `debug`, too verbose for
      production log volume.
- [ ] `SESSION_SECURE_COOKIE=true` — currently unset (defaults falsy), only
      matters if/when this app ever serves over HTTPS with cookie-based auth.
- [ ] Regenerate `APP_KEY` fresh for production — never reuse the key
      committed/used in dev.
- [ ] Rotate `SUPER_ADMIN_PASSWORD` — currently a weak plaintext dev value
      (`admin123`) in `.env`.
- [ ] Set real `APP_URL`, `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS` for the
      actual production domain.
- [ ] Set `CORS_ALLOWED_ORIGINS` to the real frontend origin(s) (new env var,
      see above — falls back to `FRONTEND_URL` if unset, so this is optional
      if there's only one frontend origin).
- [ ] Real database credentials — currently an empty `DB_PASSWORD` (XAMPP dev
      default).
- [ ] HTTPS/SSL termination at the hosting layer.
- [ ] Real mail delivery — currently `MAIL_MAILER=log`, meaning contact-form
      and demo-request submissions are only written to `storage/logs/laravel.log`,
      not sent anywhere. This is a known, intentional stub (see the comments
      in `ContactController`/`DemoRequestController`), not something this pass
      fixes — needs a real mail provider (SMTP, Postmark, SES, etc.) wired up
      before those forms are actually useful in production.
