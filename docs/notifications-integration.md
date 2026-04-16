# Notifications Integration Contract

Nozule Suite handles **email** natively (via `wp_mail`). **SMS and WhatsApp** are delegated to an external handler — typically [SimpleNotify](https://github.com/hdqah/SimpleNotify). Push notifications are not used in the pilot.

## Architecture

```
Nozule                              External handler (e.g., SimpleNotify)
──────                              ─────────────────────────────────────
NotificationService::queue()   ──►  (renders nothing — content is pre-rendered)
  ├─ resolves locale
  ├─ renders body in Nozule templates
  └─ persists row in nzl_notifications

NotificationService::send()
  ├─ sendSMS() / sendWhatsApp()
  │     └─ apply_filters('nozule/notifications/{channel}_handler', null, $n)
  │           └─ handler callable ──► queue to provider (async)
  ├─ if handler accepted (true):
  │     └─ status = 'sending'   (not 'sent' — awaiting webhook)
  └─ if handler returned false:
        └─ incrementAttemptAndRequeue (Nozule owns retry, MAX_ATTEMPTS = 3)

                        ...later, provider webhook fires...

                                    do_action(
                                      'nozule/notifications/mark_delivered',
                                      $nozule_id, $provider_msg_id, $status
                                    )
NotificationService::markDelivered()
  ├─ status = 'delivered'  (+external_id, delivered_at)
  └─ or requeue on 'failed'
```

## Handler contract (inbound)

Register a WordPress filter that returns a `callable(Notification): bool`:

```php
add_filter( 'nozule/notifications/sms_handler',      [ MyBridge::class, 'handler' ], 10, 2 );
add_filter( 'nozule/notifications/whatsapp_handler', [ MyBridge::class, 'handler' ], 10, 2 );

public static function handler( ?callable $prev, Notification $n ): callable {
    return function ( Notification $n ): bool {
        // Queue/send via provider. Return true if ACCEPTED (not necessarily delivered).
        // Return false on queue failure — Nozule will retry per MAX_ATTEMPTS.
        return MyProvider::queue(
            to:              $n->recipient,
            body:            $n->content,
            channel:         $n->channel,
            locale:          $n->locale,
            template_name:   $n->template_name,
            template_lang:   $n->template_lang,
            template_params: $n->template_params ? json_decode( $n->template_params, true ) : null,
            meta:            [ 'nozule_notification_id' => $n->id ],
        );
    };
}
```

### Notification payload

| Field | Type | Notes |
|---|---|---|
| `id` | int | **Persist this** in provider metadata — needed for webhook callback |
| `channel` | string | `sms` \| `whatsapp` |
| `recipient` | string | E.164 phone number |
| `locale` | string | `en` / `ar` / etc. — 2–5 chars |
| `content` | string | Pre-rendered message body. **Do not re-render.** |
| `subject` | string\|null | Optional title (e.g., WA interactive header) |
| `template_name` | string\|null | WhatsApp Business approved template id |
| `template_lang` | string\|null | Template language code |
| `template_params` | JSON string\|null | Positional params for template placeholders |
| `type` | string | One of `Notification::TYPES` — useful for logs/grouping |
| `booking_id`, `guest_id` | int\|null | Context |

## Status writeback (outbound)

After the provider returns a terminal result (success or failure), fire:

```php
do_action(
    'nozule/notifications/mark_delivered',
    $nozule_notification_id,   // int
    $provider_message_id,      // string|null
    $status                    // 'delivered' | 'failed'
);
```

Nozule will update the row accordingly. On `failed`, it will requeue if `attempts < MAX_ATTEMPTS`, else mark terminal failure. **The handler should not retry internally** for Nozule-originated messages.

## Settings

| Key | Default | Purpose |
|---|---|---|
| `notifications.email_enabled` | `1` | Native `wp_mail` delivery |
| `notifications.sms_enabled` | `0` | Gate SMS queuing |
| `notifications.whatsapp_enabled` | `0` | Gate WA queuing |
| `notifications.default_locale` | `en` | Fallback when guest/booking locale unknown |
| `notifications.require_external_handler` | `0` | If `1`, SMS/WA fail terminally (no retry) when no handler is registered |

When a channel is enabled but no handler is registered, Nozule renders an admin warning notice linking to SimpleNotify.

## Fields added in migration 018

```sql
ALTER TABLE nzl_notifications
  ADD COLUMN locale          VARCHAR(5)   NOT NULL DEFAULT 'en' AFTER recipient,
  ADD COLUMN template_name   VARCHAR(100) DEFAULT NULL         AFTER template_vars,
  ADD COLUMN template_lang   VARCHAR(10)  DEFAULT NULL         AFTER template_name,
  ADD COLUMN template_params LONGTEXT     DEFAULT NULL         AFTER template_lang;
```
