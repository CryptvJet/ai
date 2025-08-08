# PulseCore AI App

Standalone PHP/JS app served from `/ai` that provides chat, analytics, and a bridge for controlled writes into PulseCore.

## Setup
1. Copy `api/db_config.php` and fill database credentials for the AI app and PulseCore read/write users.
2. Create the AI database and run SQL in `sql/ai_schema.sql`.
3. In PulseCore, create users `pcore_reader` and `pcore_writer` and run `sql/pcore_bridge.sql` (adjust table names to match real schema).
4. Place the `ai` directory on the server so Apache serves `/ai`.
5. Ensure `/ai/.htaccess` rewrite rules are enabled.

## Cron / Worker
Schedule the queue consumer:
```
*/5 * * * * php /path/to/ai/worker-consume-queue.php
```
This script calls whitelisted stored procedures in `pcore_bridge` for any queued actions.

## Security Notes
- No direct writes to PulseCore; all writes go through `pcore_write_queue` and stored procedures.
- Admin routes require a valid session token; `admin@local / ChangeMe!` seed user is provided.
- CORS is disabled; API only serves JSON.
- Update `SESSION_SECRET` and database passwords before deployment.

## Testing Checklist
1. Fill `db_config.php` with real credentials.
2. Run `sql/ai_schema.sql` on the AI database.
3. Run `sql/pcore_bridge.sql` on PulseCore and grant permissions.
4. Access `/ai/` in browser; the chat and analytics tabs should load.
5. Visit `/ai/admin/login.html`, login, and create a canned response.
6. Send a chat message; verify messages stored.
7. Open Analytics tab; fetch stats for different ranges.
8. POST to `/api/pcore/enqueue` and run `worker-consume-queue.php`; queue row should update to `done`.
