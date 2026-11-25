# Swaply

Peer-to-peer trading marketplace for Tunisia: exchange products, negotiate counteroffers, add cash to balance a deal, or buy and sell directly. Prices in TND.

Stack: Symfony 8.1 · PHP 8.4 · MySQL 8.4 · Doctrine ORM/Migrations · Twig · Bootstrap 5 (CDN) · AssetMapper · Mailpit (dev email).

## Status

**Core marketplace complete**

Accounts & security
- User entity (roles, email verification, suspension flag) + Doctrine migrations
- Registration with email verification (48h link), login/logout, throttling (5 attempts / 15 min)
- Password reset by email (1h link), branded Bootstrap 5 layout, CSRF protection throughout
- Profile page to edit name, username, phone and location; change password with current-password check

Listings & marketplace
- 13 categories, listing upload (up to 5 photos, JPEG/PNG/WebP ≤5MB), edit, withdraw, delete
- Listing lifecycle: pending → active (moderation) → reserved → traded/sold; rejected with reason, withdrawn
- Marketplace browse with full-text search, category/governorate/condition/type/price filters, 4 sort orders, pagination
- Listing detail page with photo carousel, seller card with rating, similar links

Trading & purchases
- Swap offers with counteroffer chains (parent/child, 7-day expiry, auto-expire command `app:offers:expire`)
- Cash balancing both directions (requester pays / owner pays), amount in millimes
- Accept → both items reserved, competing offers auto-rejected → two-party handover confirmation → trade completed (items marked traded)
- Direct buy-now purchases: request → seller accept (auto-rejects competing requests) → two-party confirmation → item sold
- My offers / My purchases inboxes with status badges

Communication & engagement
- Private conversations tied to a listing, inbox with unread badges, read marking, chat thread UI
- Favorites (heart on listing page and cards) with a favorites page
- Two-way 1–5 star reviews after completed deals; seller rating shown on listing pages and dashboard; one review per person per deal

Admin
- Moderation dashboard at `/admin`: approval queue (approve / reject with reason) and platform stats
- Member directory at `/admin/users`: search, suspend / reactivate accounts (admins and self are protected; suspended users cannot log in)
- Rejected listings show the reason to the seller with an edit-and-resubmit link

## Run

PowerShell (from the project root):

```powershell
powershell -ExecutionPolicy Bypass -File scripts\start-dev.ps1
```

This starts MySQL (if down), Mailpit (if down) and `symfony serve` on http://127.0.0.1:8000.
Mail UI (all outgoing emails are captured there): http://127.0.0.1:8025

## Dev accounts

| Account | Email | Password |
| --- | --- | --- |
| Admin (seeded fixture) | admin@swaply.tn | Admin@12345 |
| Test user (created in browser test) | tester1@example.com | TestPass123! |
| Demo sellers (11 verified accounts, 22 active listings) | yassine.benali@example.com, salma.trabelsi@example.com, ahmed.gharbi@example.com, rim.bouaziz@example.com, mehdi.jlassi@example.com, nadia.khelifi@example.com, karim.belhaj@example.com, ines.mansour@example.com, sami.oueslati@example.com, leila.haddad@example.com, nizar.chaabane@example.com | Demo@12345 |

Database credentials live in `.env.local` (gitignored). Never commit them.

## Useful commands

```bash
php bin/console doctrine:migrations:migrate -n   # apply migrations
php bin/console doctrine:fixtures:load -n        # reset DB + seed admin
php bin/console cache:clear
php bin/console debug:router
symfony server:stop
```

## Notes

- MySQL currently runs as a console process. To install it as an auto-start Windows service, run once in an **elevated** shell:

  ```powershell
  & 'C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqld.exe' --install MySQL84 --defaults-file='C:\ProgramData\MySQL\my.ini'
  net start MySQL84
  ```

- In dev, emails are routed to the `sync` transport (see `config/packages/messenger.yaml`) so verification/reset work without a Messenger worker. In prod, they stay async via Doctrine transport.
- Money is stored as integer millimes (TND has 3 decimals) and rendered with the `tnd` Twig filter.
- Uploaded listing images are stored in `public/uploads/listings/` (not committed).
