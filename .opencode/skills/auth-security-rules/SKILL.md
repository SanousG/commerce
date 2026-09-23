---
name: auth-security-rules
description: Use when implementing or reviewing login, logout, registration, password reset, email verification, sessions, CSRF, rate limits, or sensitive account security.
---

# Authentication And Security Rules

- Use Laravel session and cookie authentication for the web application.
- Do not use JWT.
- Do not use refresh tokens for the web frontend.
- Do not use Supabase Auth; Laravel's `public.users` identity is authoritative.
- Hash passwords with Laravel's hashing facilities. Never store or log plaintext
  passwords.
- Regenerate the session after successful login.
- On logout, invalidate the session and regenerate the CSRF token.
- Preserve Laravel CSRF protection for state-changing web requests.
- Apply rate limiting to sensitive endpoints such as login, registration,
  password reset, and verification when their phase implements them.
- Implement email verification only when its phase requires it.
- Use Laravel's native password reset mechanisms when password reset is added.
- Treat backend authentication and authorization as the source of truth.
- Never expose secrets in responses, logs, source control, or agent output.
- Avoid account enumeration in authentication and recovery flows where
  appropriate.
- Design sensitive actions so future phases can add MFA or approval controls
  without bypassing backend authorization.

Do not implement authentication or security features before their explicit
phase.
