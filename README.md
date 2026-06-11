# Class Booking Platform — Laravel + Inertia + Vue

[![CI](https://github.com/haefrain/class-booking-platform/actions/workflows/ci.yml/badge.svg)](https://github.com/haefrain/class-booking-platform/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
![PHP](https://img.shields.io/badge/PHP-8.4-777BB4)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20)
![Vue](https://img.shields.io/badge/Vue-3-42b883)

🇪🇸 [Versión en español](README.es.md)

A full-stack class booking platform for a studio/academy (recurring group classes with limited seats), built to show how a senior Laravel + Inertia codebase handles the hard parts of the booking domain: seat concurrency without overbooking, a FIFO waitlist with atomic promotion, Stripe test-mode payments with webhook reconciliation, DST-safe recurring schedules, and queued reminders — under Pest, Larastan (level 7), Pint, vue-tsc and CI against real MySQL.

> 🚧 **Work in progress** — built milestone by milestone with green tests, static analysis and CI.

## Stack

- **Laravel 13**, PHP 8.4 + **Inertia 3 / Vue 3 / TypeScript / Tailwind 4** (official starter kit)
- **Database:** MySQL 8 · **Queue/Cache:** Redis · **Mail (dev):** Mailpit
- **Payments:** Stripe Checkout (test mode) behind a gateway port
- **Dev environment:** Laravel Sail · **Quality:** Pest, Larastan (level 7), Pint, ESLint/Prettier, GitHub Actions

## Quick start

```bash
cp .env.example .env
./vendor/bin/sail up -d        # MySQL 8 + Redis + Mailpit + PHP 8.5
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install && ./vendor/bin/sail npm run build
./vendor/bin/sail bin pest     # test suite
```

The app is served at `http://localhost:8082` (Mailpit UI at `http://localhost:8026`).

Demo accounts (password `password`): `admin@classbooking.test` · `instructor@classbooking.test` · `student@classbooking.test`.

## Roadmap

- [x] **B1** — Scaffold, Sail, roles, tooling (Pint, Larastan, Pest, vue-tsc), CI
- [x] **B2** — Domain: class types, recurring schedules, DST-safe session generation
- [x] **B3** — Booking engine + FIFO waitlist (free classes), concurrency suite
- [x] **B4** — Stripe test-mode payments: checkout, webhooks, refunds, paid waitlist offers
- [ ] **B5** — Queued reminders + scheduler
- [ ] **B6** — Complete UI for the three roles
- [ ] **B7** — Production Docker image, architecture docs & ADRs

## License

[MIT](LICENSE) © Efraín Hernández
