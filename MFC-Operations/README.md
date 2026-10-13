# Withdrawal & QuickBooks Invoicing System — Phase 2 (Separated Frontend/Backend)

## Folder structure

```text
withdrawal-qb/
├── index.php
├── frontend/
│   ├── assets/              # CSS and JavaScript
│   ├── includes/            # Shared visual layout only
│   ├── login.php
│   ├── dashboard.php
│   ├── withdrawal_form.php
│   ├── withdrawal_print.php
│   ├── withdrawals.php
│   ├── invoice_preparation.php
│   ├── products_sites.php
│   ├── settings.php
│   └── quickbooks_status.php
└── backend/
    ├── config/app.php       # Application bootstrap/configuration
    ├── core/                # Database, auth and shared business helpers
    ├── controllers/         # POST actions (save withdrawal/invoice, logout)
    ├── api/                 # JSON endpoints
    ├── exports/              # CSV export endpoint
    └── database/schema.sql  # MySQL schema
```

## Install in XAMPP

1. Extract this folder as `C:\xampp\htdocs\withdrawal-qb`.
2. Start Apache and MySQL.
3. Import `backend/database/schema.sql` in phpMyAdmin. It creates `withdrawal_qb` and example master records. Replace example clients/products/sites with the real company data before production.
4. Confirm database credentials in `backend/core/database.php` or set `WQ_DB_HOST`, `WQ_DB_NAME`, `WQ_DB_USER`, and `WQ_DB_PASS`.
5. If you install under a different folder, set `WQ_BASE_URL` to that URL prefix (for example `/my-folder`). Default is `/withdrawal-qb`.
6. Open `http://localhost/withdrawal-qb/frontend/setup.php` and create the first administrator account. Delete `frontend/setup.php` after initial setup.
7. Sign in at `http://localhost/withdrawal-qb/frontend/login.php`.

## Phase 2 scope

- Local MySQL records for withdrawals and invoice preparation.
- Employee login, role checks, CSRF protection and activity log.
- Withdrawal create/edit/search/register and print view.
- Invoice draft separated from the source withdrawal; supports invoice-only additional lines.
- Product/site/client setup and CSV export.
- QuickBooks status view and queue schema.

## Important scope boundary

QuickBooks Web Connector connectivity is a **required Phase 2 acceptance item but is not yet implemented or verified in this package**. The remaining integration work must verify Web Connector authentication, authorization to the correct QuickBooks company file, qbXML request/response communication, a test invoice, successful and rejected responses, duplicate/uncertain-result handling, and documented customer/item mappings in the real QuickBooks Enterprise 24.0 environment. Do not enable invoice posting or claim success until these tests pass.

## Print

Open a saved withdrawal and use **Print form**. The print page hides its own Print/Close controls and prints only the form content. The print template is an initial browser-print layout based on the known Excel fields; compare it with the Excel print preview and adjust page size/margins/column widths before production rollout.
