# Deploying the contact form

One endpoint: `send-email.php`. It reads its credentials from a `.env` file
beside the code, filters submissions, and sends through your own SMTP server.

Needs **PHP ≥ 8.1** with `openssl`, `mbstring`, `curl` and `phar`, an SMTP account, and a free MaxMind GeoIP2 Lite key.

---

## Quick start

**1. Upload.** Put on your web server, in one directory (say `/backend/`):

```
backend/
├── send-email.php    ← the endpoint your form posts to
├── user-agent.php    ← fills the form's fingerprint fields
├── update-geoip.php  ← refreshes the GeoIP databases
├── .env              ← you create this, from .env.example
├── .htaccess         ← must deny .env (see below)
├── src/              ← config.php, utils.php, env.php
├── templates/        ← the e-mail bodies
├── js/user-agent.min.js
└── libs/
    ├── PHPMailer/src/
    └── geoip/        ← geoip2.phar + the two .mmdb databases
```

**2. Configure.** Copy `.env.example` to `.env`, fill in your SMTP credentials,
`URL` (your contact page) and `HOSTS` (where the handler may redirect back to —
a comma-separated allow-list; a leading dot matches sub-domains). Anything not
listed is refused, which is what stops the form being used as an open redirect.

**3. Protect `.env`.** It holds a password. In `.htaccess`:

```apache
<Files ".env">
  Require all denied
</Files>
```

Then check it: `curl -I https://your-domain.com/backend/.env` must return 403,
not 200. Do not skip this.

**4. Add the form.** Copy `demo/contact.html` into your page and adjust three
URLs — the `<form action>`, the `<script src>`, and the argument to
`validate_contact()` — to point at your `/backend/`. Keep the `template` and
`address` (honeypot) hidden inputs: the back-end refuses submissions without
them. Nothing else is site-specific.

Hugo users: `demo/hugo_contact.html` is the same form as a shortcode taking the
backend URL as its argument — `{{< hugo_contact "https://your-domain.com/backend" >}}`
— so the domain stays in your site config instead of in the template.

**5. Test.** Submit the form from a browser. On success you get a confirmation
page and a copy of the message at the sender's address. On failure you get the
SMTP error in plain text — not a blank 500.

---

## How the spam filtering works

The form carries hidden fields (OS, browser, language, public IP, ISP, country,
local IP) filled by `js/user-agent.min.js` from `user-agent.php`. A bot posting
straight to the backend cannot fill them consistently; `send-email.php` compares
each one against what the server itself sees and rejects mismatches. There is
also a honeypot field (`address`), hidden by CSS: humans leave it empty, bots
fill it.

**This is also the main failure mode.** If that JavaScript throws, the fields
stay empty and *nobody* can submit — you get silence and assume nobody writes to
you. Two rules follow:

- Never make a fingerprint field `required` in HTML: let the backend decide.
  the back-end compares them, but never demands them from the browser.
- Check the browser console on your live contact page after deploying.

(A real instance of this: `var DNSResolver = browser.dns || chrome.dns` threw a
`ReferenceError` in every browser outside a WebExtension, killing the script and
the form with it. Fixed in `js/user-agent.js`; the local-IP promise now also
resolves to `0.0.0.0` after 3 s so a browser that hides it cannot block the form.)

## Troubleshooting

| Symptom | Cause |
|---|---|
| "Server configuration is incomplete: KEY missing from .env" | `.env` missing, unreadable, or that key empty |
| "Mailer Error: SMTP Error: Could not authenticate" | wrong `SMTP_USER`/`SMTP_PASS`, or the mailbox password changed |
| Blank HTTP 500 | PHP fatal before output — check the server error log; usually `PHPMailer/` missing |
| Submit button does nothing | the fingerprint JavaScript threw; check the browser console |
| Sends, but never arrives | SMTP accepted it and the recipient's provider dropped it — check `MAIL_FROM` is a real mailbox on `SMTP_HOST`, with SPF/DKIM set |
| "Unauthorized" from user-agent.php | the posting domain is not in `ORIGINS` |
| HTTP 403 on POST | a server firewall (WAF) blocking a non-browser request — normal for `curl`, not for browsers |
