# Online Laundry Service

A standalone Laravel application for ordering laundry pickup and tracking service progress. Prices and totals are computed on the server. Payment method/status is separate from fulfillment status. The default flows are cash-on-delivery and manually recorded payment; no payment processor is integrated.

## Features

- Customer registration/login and customer-owned order history.
- Seeded, editable service catalog with per-kilogram and per-item prices.
- Pickup/delivery scheduling, address and optional instructions.
- Server-calculated price snapshots, stable receipt references, printable receipt page.
- Staff dashboard, administrator assignment and service management.
- Explicit allowed order-state transitions and append-only status/payment/assignment event records.
- Separate unpaid/paid/refunded payment state; offline payment only.
- Admin daily/monthly reports and CSV export, calculated from persisted orders.
- Customers can access/cancel only their own eligible orders; staff can access/update assigned orders only.

## Local setup (Windows)

```powershell
Copy-Item .env.example .env
php C:/Users/wondr/.config/herd-lite/bin/composer.phar install
npm install
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

SQLite is configured for local development. Configure PostgreSQL in `.env` for deployment. Registration is open. To grant administrator or staff access, a trusted operator must set `users.is_admin` or `users.is_staff` directly in the database; registration cannot assign these roles. `php artisan db:seed` adds only catalog services, not users, customer orders, or payments.

## Pricing and status rules

- Monetary values are integer hundredths of an IDR (`100` stored units = `Rp 1.00`); price totals use no floating-point arithmetic.
- Weight quantities are integer grams (0.100–100 kg); per-item quantities are whole counts (1–500). Per-kg line totals use integer arithmetic, rounded to the nearest stored minor unit. Order lines snapshot service name, unit, rate, quantity and total, so later catalog changes do not rewrite old receipts.
- Customers request pickup and delivery timestamps; pickup must be in the future, delivery later than pickup. Staff confirm and process orders through `pending → confirmed → picked_up → processing → ready → delivered`; cancellation is allowed only from pending/confirmed.
- Payment states are separate (`unpaid`, `paid`, `refunded`). Marking paid is an offline staff record, not proof of settlement. Refund status is admin-only. Cancelling an order never changes payment status automatically.
- Reports show persisted order counts, non-cancelled ordered value, and orders marked paid. They do not represent reconciled bank receipts.

## Validation

```powershell
php artisan migrate:fresh --seed --no-interaction
php artisan route:list --except-vendor
php artisan view:clear
php artisan view:cache
php artisan test --compact
npm run build
php C:/Users/wondr/.config/herd-lite/bin/composer.phar validate --no-check-publish
php C:/Users/wondr/.config/herd-lite/bin/composer.phar audit
npm audit --omit=dev
```

Keep `.env`, local databases, dependencies, customer data and build artifacts out of Git.
