# Class Booking Platform — Laravel + Inertia + Vue

[![CI](https://github.com/haefrain/class-booking-platform/actions/workflows/ci.yml/badge.svg)](https://github.com/haefrain/class-booking-platform/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
![PHP](https://img.shields.io/badge/PHP-8.4-777BB4)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20)
![Vue](https://img.shields.io/badge/Vue-3-42b883)

🇬🇧 [English version](README.md)

Una plataforma full-stack de reservas de clases para un estudio/academia (clases grupales recurrentes con cupos limitados), construida para mostrar cómo un código senior de Laravel + Inertia resuelve las partes difíciles del dominio de reservas: concurrencia de cupos sin overbooking, waitlist FIFO con promoción atómica, pagos Stripe en modo test con reconciliación por webhooks, horarios recurrentes a prueba de DST y recordatorios en cola — bajo Pest, Larastan (nivel 7), Pint, vue-tsc y CI contra MySQL real.

> 🚧 **En construcción** — desarrollado hito a hito con tests, análisis estático y CI en verde.

## Stack

- **Laravel 13**, PHP 8.4 + **Inertia 3 / Vue 3 / TypeScript / Tailwind 4** (starter kit oficial)
- **Base de datos:** MySQL 8 · **Cola/Caché:** Redis · **Mail (dev):** Mailpit
- **Pagos:** Stripe Checkout (modo test) detrás de un puerto de gateway
- **Entorno de desarrollo:** Laravel Sail · **Calidad:** Pest, Larastan (nivel 7), Pint, ESLint/Prettier, GitHub Actions

## Arranque rápido

```bash
cp .env.example .env
./vendor/bin/sail up -d        # MySQL 8 + Redis + Mailpit + PHP 8.5
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install && ./vendor/bin/sail npm run build
./vendor/bin/sail bin pest     # suite de tests
```

La app se sirve en `http://localhost:8082` (UI de Mailpit en `http://localhost:8026`).

Cuentas demo (contraseña `password`): `admin@classbooking.test` · `instructor@classbooking.test` · `student@classbooking.test`.

## Roadmap

- [x] **B1** — Scaffold, Sail, roles, tooling (Pint, Larastan, Pest, vue-tsc), CI
- [x] **B2** — Dominio: tipos de clase, horarios recurrentes, generación de sesiones a prueba de DST
- [x] **B3** — Motor de reservas + waitlist FIFO (clases gratis), suite de concurrencia
- [x] **B4** — Pagos Stripe modo test: checkout, webhooks, reembolsos, ofertas pagas de waitlist
- [ ] **B5** — Recordatorios en cola + scheduler
- [ ] **B6** — UI completa para los tres roles
- [ ] **B7** — Imagen Docker de producción, docs de arquitectura y ADRs

## Licencia

[MIT](LICENSE) © Efraín Hernández
