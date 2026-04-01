# Muon_MultiFactorLogin

Adds two-factor authentication (MFA) to Magento 2 customer login. After a customer enters
their correct password, they are prompted to verify their identity using a one-time token
delivered via **SMS** (Twilio) or **email** before the session is established.

---

## Features

- Intercepts successful password logins and requires a second factor before access is granted
- Token delivery via **SMS** (official Twilio PHP SDK) and/or **email** (Magento transactional email)
- Customers with both a phone number and email can choose their preferred method
- Configurable token **length** (4–12 characters) and **character set** (numeric, alphanumeric, custom)
- Configurable token **lifetime** (expires automatically)
- Tokens are **one-time use** — consumed on first successful verification
- Tokens are stored **encrypted** at rest via `EncryptorInterface` (AES-256)
- **Rate limiting** on token requests — configurable max per rolling window
- **Rate limiting** on verification attempts — token invalidated after too many wrong guesses
- Guard on all MFA controllers: direct URL access (bookmark, browser history) redirects to login
- Nightly cron cleanup of expired tokens
- Fully translatable (i18n/en_US.csv)

---

## Installation

1. Place the module at `app/code/Muon/MultiFactorLogin/`
2. Install the Twilio PHP SDK via Composer (run from the project root):
   ```bash
   docker compose exec -u magento php composer require twilio/sdk:^8.0
   ```
3. Enable and deploy:
   ```bash
   docker compose exec -u magento php bin/magento module:enable Muon_MultiFactorLogin
   docker compose exec -u magento php bin/magento setup:upgrade
   docker compose exec -u magento php bin/magento setup:di:compile
   docker compose exec -u magento php bin/magento cache:clean
   ```

---

## Configuration

Navigate to **Stores → Configuration → Muon → Multi-Factor Login**.

### General

| Field | Description | Default |
|-------|-------------|---------|
| Enable Multi-Factor Login | Global on/off switch | No |

### Token Settings

| Field | Description | Default |
|-------|-------------|---------|
| Token Length | Number of characters (4–12) | 6 |
| Character Set | Characters used to generate the token | `0123456789` |
| Token Lifetime (minutes) | How long a token is valid after sending | 10 |

### Delivery Methods

| Field | Description | Default |
|-------|-------------|---------|
| Allowed Delivery Methods | `Email only`, `SMS only`, or `Both (customer chooses)` | Both |

When **Both** is selected, customers are shown a choice only if they have a phone number on
their default billing address. If no phone is found, email is used automatically.

### Rate Limiting

| Field | Description | Default |
|-------|-------------|---------|
| Max Token Requests per Window | Max times a customer can request a new token within the window | 3 |
| Rate Limit Window (minutes) | Rolling window duration | 60 |
| Max Verification Attempts per Token | Token is invalidated after this many wrong guesses | 5 |

### Twilio (SMS)

| Field | Description |
|-------|-------------|
| Account SID | Twilio Account SID (from Twilio Console) |
| Auth Token | Twilio Auth Token — stored **encrypted** in `core_config_data` |
| From Number | Sending number in E.164 format, e.g. `+15551234567` |

> Twilio credentials are never stored in plain text. The Auth Token is encrypted by
> Magento's `EncryptorInterface` before writing to the database.

### Email

| Field | Description | Default |
|-------|-------------|---------|
| Sender Email | From address (falls back to store general contact) | — |
| Sender Name | Display name for the sender | Multi-Factor Login |
| Email Template | Select a customised template from Marketing → Email Templates | Default (bundled) |

To customise the email: go to **Marketing → Email Templates**, duplicate
**MFA Verification Token**, edit it, save, then select it in this field.

---

## Authentication Flow

```
Customer submits email + password
    │ credentials valid
    ▼
Plugin intercepts LoginPost::execute
    ├─ Logs customer back out
    ├─ Stores pending customer ID in session
    └─ Redirects to /mfa/verify

/mfa/verify (GET)
    ├─ One method available  → shows "Send Code" button
    └─ Both methods available → shows method selector (SMS / Email)

Customer clicks "Send Code" → POST /mfa/verify/send
    ├─ Rate limit check (reject if exceeded)
    ├─ Generate token, encrypt, persist to muon_mfa_token
    ├─ Invalidate any previous active tokens for this customer
    ├─ Dispatch via SMS (Twilio) or Email
    └─ Redirect back to /mfa/verify (token input form shown)

Customer submits token → POST /mfa/verify/submit
    ├─ Correct → log customer in, clear MFA session, redirect to account
    ├─ Wrong   → increment attempts, show error (retry allowed)
    └─ Terminal (expired / max attempts / no token) → redirect to login

"Resend code" → POST /mfa/verify/resend
    ├─ Rate limit check
    └─ Issues a new token via the same method (invalidates the previous one)
```

**Direct URL access guard:** All four MFA controllers check for a `mfa_pending_customer_id`
in session. If absent (bookmark, history, direct navigation), the customer is immediately
redirected to the login page.

---

## Public API

### `Api/TokenServiceInterface`

| Method | Description |
|--------|-------------|
| `createAndSend(int $customerId, string $deliveryMethod): void` | Generate, encrypt, persist, and dispatch a token. Throws `LocalizedException` if rate-limited or dispatch fails. |
| `verify(int $customerId, string $inputToken): bool` | Returns `true` on match (marks token used). Returns `false` on wrong guess (increments attempts). Throws `LocalizedException` for terminal states. |

### `Api/RateLimitServiceInterface`

| Method | Description |
|--------|-------------|
| `isRequestAllowed(int $customerId): bool` | Returns `true` if the customer is within the request rate limit. |
| `recordRequest(int $customerId): void` | No-op (count derived from token table directly). |

---

## Dependencies

| Module | Reason |
|--------|--------|
| `Magento_Customer` | Customer session, repository, address repository |
| `Magento_Store` | Store scope for configuration |
| `Magento_Email` | Transactional email dispatch |
| `twilio/sdk: ^8.0` | Official Twilio PHP SDK for SMS delivery |

---

## Known Limitations

- SMS phone number is sourced from the customer's **default billing address** (`telephone` field).
  If a customer has no billing address or no phone number, SMS is silently unavailable.
- If both Twilio credentials are not configured and the admin enables SMS-only mode,
  customers without a billing phone will see an error during token dispatch.
- MFA applies only to **storefront** password-based login via `LoginPost`.
  Admin logins, API authentication, and social logins are not covered by this module.
