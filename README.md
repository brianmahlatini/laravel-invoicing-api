# laravel-invoicing-api

An invoicing REST API built with **Laravel 13**: customers, invoices with line items, a strict invoice lifecycle, gap-free invoice numbering, queued email delivery, overdue reminders on the scheduler, and Sanctum token auth. The focus is the parts that go wrong in real billing systems, like money rounding, race conditions, and editing documents a customer has already seen, and each one has a test.

```
php artisan test
  Tests: 24 passed        # unit + feature tests, run in CI against SQLite and MySQL 8.4
```

## Endpoints

| Method | Path | Notes |
|---|---|---|
| POST | `/api/auth/register`, `/api/auth/login` | Rate limited 10/min per IP; tokens expire after 7 days |
| POST | `/api/auth/logout` | Revokes the current token only |
| CRUD | `/api/customers` | Scoped to the signed-in user; `?q=` search; can't delete a customer with invoices |
| CRUD | `/api/invoices` | `?status=`, `?overdue=1`, paginated, customer eager-loaded |
| POST | `/api/invoices/{id}/send` · `/pay` · `/void` | State transitions (below) |

## Engineering decisions

**Money is integer cents.** Floats can't represent 0.10 exactly. `InvoiceCalculator` works in cents, computes tax per line in basis points (1500 = 15%) with half-up rounding in integer arithmetic, then sums, so the printed lines always add up to the total.

**An explicit lifecycle.** `InvoiceStatus` is a backed enum that owns its transitions:

```
draft ──send──► sent ──pay──► paid
  └──void──┐      └──void──┐
           ▼               ▼
          void            void
```

Only drafts can be edited or deleted (enforced in `InvoicePolicy`). Once a customer has seen an invoice it's immutable. An illegal transition returns 409.

**No races on money paths.** Transitions lock the invoice row (`SELECT … FOR UPDATE`) before checking its status, so a double-clicked "pay" or two concurrent "send" requests can't both succeed. Invoice numbers come from a per-user, per-year counter row, also locked, which gives gap-free `INV-2026-0001` sequences.

**Email after commit, exactly once.** `send` dispatches `SendInvoiceEmail` with `afterCommit()`, so no email goes out for a transaction that rolled back. The job is `ShouldBeUnique` per invoice, carries only the invoice id (not a stale serialized model), and retries with backoff.

**Request input can't touch what the server owns.** Totals, number, status and owner are never fillable. `customer_id` is validated with an `exists` rule scoped to the caller, so you can't invoice another user's customer. `Model::shouldBeStrict()` makes N+1 queries and silently discarded attributes throw outside production.

**Operational.** `invoices:remind-overdue` uses `chunkById` for flat memory on large tables and is scheduled daily with `withoutOverlapping()->onOneServer()`, so it's safe with multiple app servers.

## Run

```sh
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan serve
php artisan queue:work          # delivers invoice emails
php artisan schedule:work       # overdue reminders
```

## CI

PHP 8.3 and 8.4 on SQLite, plus PHP 8.4 on **MySQL 8.4**, so row locking and migrations are checked on a real server. Also runs Laravel Pint and `composer audit`. Test failures are turned into GitHub annotations from the JUnit report.

## License

MIT
