# Tesuji

A platform for playing **Go** (the ancient board game) — realtime and correspondence modes.

Built with PHP 8.4 / Laravel 13 on the backend and Svelte 5 on the frontend. Portfolio project with a focus on clean architecture, thorough testing, and well-structured commits.

> **Tesuji** (手筋) — a clever, skillful move in Go that makes the most of a difficult situation.

## Features

- **Realtime games** — both players online, instant moves via WebSocket (Laravel Reverb)
- **Correspondence games** — move at your own pace, email notifications on your turn
- Chinese rules, area scoring, 9×9 / 13×13 / 19×19 boards
- Absolute and byo-yomi time controls for realtime; days-per-move for correspondence
- Google OAuth and email/password registration

## Stack

| Layer | Tech |
|---|---|
| Backend | PHP 8.4, Laravel 13, PostgreSQL 16, Redis, Laravel Reverb |
| Frontend | Svelte 5, TypeScript, Vite, Laravel Echo |
| Auth | Laravel Sanctum, Google OAuth |
| Tests | PHPUnit, Vitest, shared JSON fixtures |
| Queue | Redis + Laravel Horizon |
| Deploy | Railway (backend), Vercel (frontend) |
| Local dev | DDEV |

## Quick start

### Backend

Requires [DDEV](https://ddev.readthedocs.io/en/stable/).

```bash
cd backend
ddev start
ddev exec php artisan migrate
```

The Laravel app will be available at `https://tesuji.ddev.site`.

### Frontend

Requires Node.js 20+.

```bash
cd frontend
npm install
npm run dev
```

Vite dev server starts at `http://localhost:5173`.

### Tests

```bash
# Backend
cd backend
ddev exec php artisan test

# Frontend
cd frontend
npm test
```

## Repository structure

```
go-game/
├── backend/        # Laravel 13 — API, WebSocket, game logic, queue
│   ├── app/
│   │   ├── Game/   # Pure PHP game logic (no framework dependencies)
│   │   ├── Http/
│   │   ├── Models/
│   │   ├── Events/
│   │   └── Jobs/
│   └── tests/
│       ├── Unit/Game/   # Fast unit tests, no Laravel boot
│       ├── Feature/     # API feature tests
│       └── fixtures/    # Shared JSON scenarios (also used by Vitest)
└── frontend/       # Svelte 5 + Vite SPA
    └── src/
        └── lib/
            ├── game/    # TS game module (legality validation, event application)
            └── board/   # SVG board components
```

## Architecture notes

Key decisions are documented in [`CLAUDE.md`](CLAUDE.md):

- **Event sourcing** — game state is never stored, it is derived from the `moves` table. Each move stores a `board_state` snapshot so the current position is always a single `SELECT` away.
- **Pure PHP game layer** — `app/Game/` has zero `Illuminate\*` imports. Unit tests boot in milliseconds.
- **Redis pub/sub for broadcasting** — controllers never call Reverb directly; they publish to Redis and respond immediately. Reverb fans out asynchronously.
- **Reduced TS scope** — the client validates move legality and applies server events, but does not recompute captures or position hashes. The server is the single source of truth.

## CI

GitHub Actions runs four parallel jobs on every push and pull request:

| Job | Command |
|---|---|
| `backend-lint` | `./vendor/bin/pint --test` |
| `backend-test` | `php artisan test` |
| `frontend-lint` | `npm run format:check` |
| `frontend-test` | `npm test` |
