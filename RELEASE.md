> v6.0.0 ~ "twilio/sdk 8 for Laravel 12"

---
## Highlights

- **Requires `twilio/sdk ^8.3` and PHP 8.1 or later.** `laravel-notification-channels/twilio` 4, which Laravel 12 needs, requires SDK 8. Stay on 5.x with SDK 6.
- **No API change.** `message()` and `call()` keep their signatures, and `messages->create()` and `calls->create()` keep their shape in SDK 8.
- **Tests run in CI.** A new contract test drives `message()` and `call()` through the SDK's real resource classes with a fake HTTP client.

## Not tested

No real SMS or call was sent, and the tests run only the newest SDK in range, not the 8.3 floor.
