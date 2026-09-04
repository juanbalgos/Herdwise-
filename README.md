# HerdWise

HerdWise is a multi-farm livestock monitoring system for medium-scale Philippine farms. The working system includes owner registration, farm creation, role-ready farm membership, a dashboard, individual animals, livestock batches, search, health observations, veterinarian-aware treatments, vaccination, deworming, automatic reminders, and livestock timelines.

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
- Observations, treatments, vaccination, and deworming are separate records because they represent different responsibilities and reporting needs. Only veterinarian accounts may enter a formal diagnosis.
- A next-due or follow-up date creates a reminder linked to its source record, making schedules traceable instead of duplicating manual tasks.
- Breeding, QR, audit, report, and AI modules should follow the same farm and livestock boundaries. AI must receive curated record context from the backend and must never query the database directly or diagnose disease.
