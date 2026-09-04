# HerdWise

HerdWise is a multi-farm livestock monitoring system for medium-scale Philippine farms. This first vertical slice includes owner registration, farm creation, role-ready farm membership, a dashboard, individual animal records, livestock batches, search, and profile views.

## Run locally

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

For a quick demo without PostgreSQL, set `DB_CONNECTION=sqlite`, remove the other `DB_*` lines, create `database/database.sqlite`, then migrate and seed.

Demo sign-in: `juan@herdwise.test` / `password`

## Architecture decisions

- A `farm_users` membership table holds owner, manager, staff, and veterinarian roles so permissions can grow without changing users.
- Every livestock record belongs to a farm. Controllers scope reads and writes through the signed-in user's farm.
- Individual animals and batches are separate because poultry and production groups should not require one QR tag per animal.
- Species and breeds are reference data, not free text, keeping future reports consistent.
- Health, vaccination, deworming, breeding, reminder, QR, audit, report, and AI modules should be added as records linked to `farm_id` and either an animal or batch. AI must receive curated record context from the backend and must never query the database directly or diagnose disease.
