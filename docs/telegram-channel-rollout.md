# Numan Freight — Telegram bot and channel rollout

**Status:** Code integration and automated tests are separate from provider acceptance. This document does not authorize live bot activation, posting or migration. Use the current Numan channel; a replacement channel is not necessary.

## 1. Confirm the existing bot and channel

1. Sign in to Telegram using an account permitted to administer the **existing** Numan Freight channel.
2. Open **@BotFather** and inspect the bot under its management list. Confirm the exact bot username and that it is active; a text handle typed into the app is not evidence that Telegram registered a bot with that username.
3. If `@NumanFreightbot` does not appear in **Add Administrator**, verify its actual username in BotFather first, open the bot's `https://t.me/<verified-username>` profile to confirm it is resolvable, and retry adding that exact bot to the **existing channel**. Don't create a new channel as a workaround.
4. Grant the bot **Post Messages** permission as a channel administrator. It does not need permission to add administrators or access members to publish the freight summaries used here.
5. Record the existing channel's real Telegram chat ID (typically a `-100...` numeric ID). A public channel `@handle` is not a substitute unless explicitly tested with the configured bot.
6. Confirm the channel's administrators agree that approved, visible freight summaries may be published publicly. No consignor contact details should ever be included in channel posts.

**Manual gate:** In a private owner-controlled bot session, verify the bot identity and channel posting permission. Code/CI cannot confirm either.

## 2. Server configuration

Configure these keys only through the deployment environment's secret manager or protected `.env`; **never** put actual values into GitHub, screenshots, chat messages, CI logs, shell history, or client-side JavaScript:

```dotenv
TELEGRAM_BOT_TOKEN=<server-side-secret>
TELEGRAM_BOT_USERNAME=<verified-bot-username-without-at-sign>
TELEGRAM_CHANNEL_CHAT_ID=<existing-numeric-channel-id>
TELEGRAM_WEBHOOK_SECRET=<random-telegram-supported-secret>
```

Use the existing secure HTTPS application domain. After deploying and migrating, register Telegram's `setWebhook` via an owner-controlled secure administration process:

- Webhook URL: `https://<actual-domain>/api/telegram/webhook`
- Set the API `secret_token` parameter to exactly the configured `TELEGRAM_WEBHOOK_SECRET`; Telegram transmits it in the `X-Telegram-Bot-Api-Secret-Token` header.
- Allow private `message` updates needed for `/start` and `/stop`.
- Check `getWebhookInfo` through the protected operator workflow after registering. Do not paste bot-token URLs or API responses containing credentials in shared channels.
- Ensure HTTPS, required outbound Telegram API connectivity, the database migration, and a supervised queue worker. Never treat `QUEUE_CONNECTION=sync` as a durable production background worker.

The webhook endpoint rejects an absent or invalid secret, ignores non-private chat activity and only binds a one-time user token from the private `/start` message. Tokens expire after ten minutes; replayed tokens must fail.

## 3. Test acceptance before any public publication

Perform an isolated/test-user end-to-end check, with the bot temporarily permitted only in a test channel where possible:

1. Open the authenticated **Settings → Notifications → Connect Telegram** flow, send the one-time `/start <token>` to the bot in a private chat, and confirm the correct Numan user is linked.
2. Verify that an invalid webhook secret is rejected, a group message cannot link an account, the token cannot be reused and `/stop` or **Disconnect Telegram** stops personal alerts.
3. Check that users not linked or with Telegram notifications turned off receive **no** private bot messages. Existing email and in-app notification preferences remain intact.
4. Approve one test freight post and verify exactly one channel announcement with route, cargo, truck, pickup and reference, but **no person, phone, address or private contact**.
5. Verify **draft, submitted, rejected, hidden and expired** freight posts never publish. Re-running a queued announcement for an already published post should not create a second message.
6. Test approval/rejection, transporter interest, selected connection and delivery-related notifications against actual roles; never send the counterparty's contact data into the public channel.
7. Exercise a temporary Telegram outage and queue retries. Compare the queue's failure records with the Telegram chat; do not assume a timed-out HTTP request means no message was delivered.
8. Confirm all screen sizes of the Notifications settings page: mobile connect/disconnect buttons, keyboard focus, accessible toggle labels, dark mode and disabled-but-explanatory state while not configured.

**Do not bulk-republish historical freight.** The observer reacts to visible active freight changes after installation, not an approved historical-import campaign. Publishing old listings requires separate admin approval.

## 4. Rollback and operational limits

- Disable future Telegram sends by clearing the bot token in server configuration and restarting application/queue workers. Keep existing email/in-app notifications operational.
- Disable the webhook through Telegram's protected API administration, rather than merely deleting an application route. Monitor pending retries and failed queue jobs.
- Do not drop `telegram_accounts` or freight publication columns merely to turn off Telegram; database cleanup is a separately reviewed retention decision.
- Messages already published in a channel remain there until a channel administrator deletes them. Disabling the bot does **not** retract a public message or guarantee exactly-once network delivery.
- Before enabling publicly, verify the release preflight, actual channel administrator access, private bot linking and delivery, live queue supervision and the current freight contact-privacy rules.

**References:** Telegram [Bot API](https://core.telegram.org/bots/api) (including `setWebhook`, `secret_token` and channel posting permissions) and [Channels FAQ](https://telegram.org/faq_channels/).
