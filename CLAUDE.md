# Tesuji — CLAUDE.md

## Working style
Execute tasks without asking for confirmation. Don't ask "should I continue?", "are you sure?" — just do it. Pause only if an action is irreversible and was not explicitly requested.

---

## What this project is

**Tesuji** — a platform for playing **Go** (ancient board game). Two modes:
- **Realtime** — both players online, moves are instant via WebSocket
- **Correspondence** — players move at their own pace, receive email notifications

The project is built for a portfolio. Priorities: tests, clean architecture, well-written commits.

Written from scratch. There are two earlier prototypes (`go-server`, `go-frontend`) — they stay as-is, no code is ported from them.

Project name in code: `tesuji` (composer name, APP_NAME, GitHub repo name). The directory on disk is historically `go-game` (renamed by mistake, not critical).

---

## Stack (final)

### Backend
- **PHP 8.4 + Laravel 13** (inside DDEV container)
- **PostgreSQL 16** — primary storage
- **Redis** — pub/sub for Reverb, presence, queue, cache, rate limiting
- **Laravel Reverb** — native WebSocket server
- **Laravel Sanctum** — authentication (API tokens, not SPA mode)
- **Google OAuth** — sign in with Google

### Frontend
- **Svelte 5 + Vite** (no SvelteKit — plain SPA, SSR not needed)
- **TypeScript**
- **Laravel Echo** — WebSocket client
- Package manager: **npm**
- Runs on host, not in container (Vite hot reload without intermediaries)

### Tests
- **PHPUnit** — game logic (unit), API (feature)
- **Vitest** — frontend logic (legality validation and hover-preview only, see architecture point 5)
- **Shared JSON fixtures** for game scenarios at `tests/fixtures/game-scenarios.json`, run by both PHPUnit and Vitest. Cover what is computed on both sides — legality, ko
- **Laravel Horizon** — queue monitoring (failed jobs, retry, latency), added at the correspondence step

### Local development
- **DDEV** — config lives in `backend/.ddev/`, runs PHP 8.4 + PostgreSQL 16 + Redis. Frontend on host via `npm run dev`.

### Deploy
- **Railway** — backend (Laravel + PostgreSQL + Redis + Reverb)
- **Vercel** — frontend (static)

### Lint / format
- **Pint** — PHP formatting (ships with Laravel)
- **Prettier + prettier-plugin-svelte** — frontend formatting
- No ESLint, no PHPStan/Larastan initially — add if/when needed

### CI
- **GitHub Actions** on every push/PR. 4 parallel jobs:
  - `backend-lint`: `./vendor/bin/pint --test`
  - `backend-test`: `php artisan test`
  - `frontend-lint`: `npm run format:check`
  - `frontend-test`: `npm test`

### Git
- Single repository for the entire monorepo, at the `go-game/` level
- Public GitHub repo `tesuji`
- **Conventional Commits**, English, minimal set of types: `feat`, `fix`, `refactor`, `test`, `docs`, `chore` (add `perf`, `ci` as needed)
- Scope by domain: `game`, `api`, `auth`, `board`, `lobby`, `deps`, etc.
- No `commitlint` initially — maintain discipline manually

---

## Architecture decisions (locked)

### 1. Rules
**Chinese rules in v1.** Area scoring (territory + own live stones). Suicide forbidden. Komi depends on board size.

A **`Ruleset` interface** is introduced from the very first commit that adds rules. The only implementation in v1 is `ChineseRuleset`. Japanese rules (`JapaneseRuleset`) can be added later as another class without refactoring the game layer.

```php
interface Ruleset {
    public function name(): string;
    public function komi(int $boardSize): float;
    public function isSuicideAllowed(): bool;
    public function score(Board $board, DeadStones $dead): Score;
}
```

### 2. Board sizes
**9×9, 13×13, 19×19.** In DB — `games.board_size SMALLINT`, validated to these three values only. Default komi depends on size (table in `ChineseRuleset`), player can override when creating a game.

### 3. Time control
**Realtime:** Absolute (sudden death) + Byo-yomi (standard for Go).
**Correspondence:** Days per move (no time bank, no vacation, no Fischer).

Architecture: `TimeControl` interface + three implementations: `AbsoluteTimeControl`, `ByoyomiTimeControl`, `CorrespondenceTimeControl`. In DB — `games.time_control_type` (enum), `games.time_control_config` (JSONB), `games.black_clock` (JSONB), `games.white_clock` (JSONB). One shared queue job for timeout checks.

Deferred: Fischer, Canadian byo-yomi, vacation/time bank.

### 4. Guests
**Registered users only in v1.** Registration via email/password through Sanctum or Google OAuth. No guest accounts, no games via link without an account.

### 5. TypeScript game logic (frontend) — reduced scope
**Client-side game logic is intentionally limited.** A full TS engine was rejected — too high a risk of desync with PHP (any difference in traversal order / position normalization → constant reloads), and every bugfix would need to be made in two places.

**What the client has:**
- Move legality validation (cell occupancy, obvious suicide, ko via hash from server)
- Hover-preview of legal moves
- Applying incoming broadcast events to a local Board copy (without recomputing captures — captures come in the event)
- Replay of reviewed moves from ready-made snapshots

**What the client does NOT have:**
- Local capture computation
- Its own `position_hash` calculation (single source — server)
- Full `Board::replay()` with rules

**Server is the single source of truth.** Client applies events; `position_hash` from the event is compared to local — mismatch triggers a state reload from server. 50ms latency per move in Go is imperceptible, full local simulation is not needed.

### 6. Event sourcing for games + snapshot in every move
**A game = metadata + list of moves.** Source of truth — the `moves` table. `Board::replay($moves)` remains as a pure function but **is not used on the hot path**.

Every row in `moves` stores a **`board_state` BYTEA** column — a packed snapshot of the position after the move (~362 bytes for 19×19, 2 bits per intersection). "Current position" = `board_state` of the last move. This eliminates the linear cost growth per move and enables review mode without replay. The snapshot is derived from events and does not break event sourcing purity (can always be rebuilt from history).

No `current_position` column in `games`. Benefits:
- Natural representation for Go (game = sequence of moves)
- Free history and replay
- Free SGF export
- Constant-cost current position read (one SELECT on last `moves` row)

`position_hash` is stored on every move (for ko and client/server verification). `captures` too (redundant, but convenient for broadcast and animations).

### 6a. Move concurrency
Every move-handling transaction starts with `SELECT pg_advisory_xact_lock(game_id)` — this serializes moves within a single game without blocking other games, and eliminates the race condition between reading "whose turn" and inserting. The unique constraint `(game_id, move_number)` stays as a safety net.

### 6b. Time control — denormalized `expires_at`
The `games` table has an `expires_at TIMESTAMPTZ` column with a partial index (`WHERE status = 'playing'`). Updated on every move: "the current player's time will expire at X". The queue job catches timed-out games with a simple `WHERE expires_at < now() AND status = 'playing'`. JSONB clocks (`black_clock`, `white_clock`) remain for byo-yomi details, but the hot path uses the indexed column.

### 6c. Bot foundation
`users` has an `is_bot BOOLEAN DEFAULT false` column. Not used in v1, but added immediately: a bot is simply a user whose moves are generated not by an HTTP request but from a subprocess (GnuGo/KataGo). No migration needed later.

### 7. Broadcasting — Redis pub/sub only
Broadcasts go **exclusively via Redis pub/sub** (`BROADCAST_CONNECTION=redis`). Controllers/jobs **never** make synchronous HTTP calls to Reverb — they publish an event to Redis and respond immediately. Reverb is subscribed asynchronously and fans out events to clients. This:
- Decouples the API and WebSocket layers (Reverb can restart without affecting the API)
- Prepares for moving Reverb to a separate process/instance without refactoring
- Removes Reverb from the critical path of move handling

We broadcast **events**, not positions:
- `MovePlayed { game_id, move_number, x, y, color, captures[], position_hash }`
- `MovePassed { game_id, move_number, color }`
- `PlayerResigned { game_id, color }`
- `DeadStonesMarked { game_id, by, stones[] }`
- `GameFinished { game_id, result, score }`

The client applies events to its local Board copy using the same logic as the server.

### 8. Game layer — pure PHP
`backend/app/Game/` — pure PHP with no `Illuminate\*` imports. Immutable classes (`Board`, `Move`, `Game`, `Position`, `Stone`). Tests in `tests/Unit/Game/` do not boot the Laravel application and run instantly. This is important for portability and test speed.

---

## v1 scope — what the full first release includes

### Backend

**1. Game logic (pure PHP service, no framework dependencies)**
- Immutable `Board` (2D representation, parameterized by size)
- Move validation: cell occupancy, suicide, ko rule
- Group capture — BFS over liberties
- Ko rule — hash of previous position
- Two consecutive passes → transition to final phase
- Dead stone marking (players click, opponent confirms)
- Scoring (Chinese, area scoring)
- Resignation
- `Ruleset` interface (Chinese implementation in v1)
- `TimeControl` interface (Absolute, Byo-yomi, Correspondence implementations)

**2. Database**
- `users` — standard Laravel + Google OAuth + `is_bot BOOLEAN`
- `games` — mode, rules, board size, status, current turn, time_control_config (JSONB), black_clock/white_clock (JSONB), `expires_at` (indexed column for timeout job), result
- `moves` — game_id, move_number, color, type (play/pass/resign), x, y, captures (JSONB), position_hash, **`board_state` BYTEA (position snapshot)**, played_at
- `game_invitations` — invitations between users
- Unique constraint on `(game_id, move_number)` + **advisory lock `pg_advisory_xact_lock(game_id)`** in the move handler — race condition protection

**3. REST API**
- Register / login (email + password via Sanctum, Google OAuth)
- Create game (choose opponent, mode, board size, time control)
- Accept / decline invitation
- Play move, pass, resign
- Mark dead stones / confirm / dispute
- Game history, active games

**4. WebSocket (Laravel Reverb)**
- Private channel per game: `game.{id}`
- Broadcast events (see Broadcasting section above) — **via Redis pub/sub only**, no synchronous Reverb calls from controllers
- Presence channel: who is online in a game

**5. Correspondence specifics**
- Queue job: check expired moves against indexed `games.expires_at`
- Email notifications: your turn, opponent resigned, game expired, game finished
- **Laravel Horizon** for queue monitoring (failed jobs, retry, latency)

### Frontend

**1. Board**
- SVG rendering, supports 9×9 / 13×13 / 19×19
- Lightweight TS module: Board as a data structure, legality validation (occupancy, obvious suicide, ko via server hash), applying ready-made events
- **No local capture computation, no own position_hash** — captures and hash come from server
- Hover preview of legal moves
- Click → optimistic stone render + send to server; server response applied as an event (with captures)
- Capture animation from event data
- Final phase: clicks for dead stone marking

**2. Realtime**
- Laravel Echo connects to Reverb
- Board update on opponent event
- `position_hash` verification after each event
- Move timer

**3. Lobby**
- Create game (form with settings)
- List of open games
- Incoming invitations
- Active games (realtime and correspondence)

**4. Profile**
- Game history
- Basic stats (wins/losses)

---

## Implementation order

```
Step 0  — Monorepo skeleton: git init, .gitignore, README,
          backend (Laravel + DDEV), frontend (Vite + Svelte + TS),
          Pint, Prettier, GitHub Actions CI, publish to GitHub

Step 1  — GameLogic foundation: Board, Stone, Position
          (immutable, no rules yet)

Step 2  — GameLogic: stone placement, group capture
          (BFS over liberties)

Step 3  — GameLogic: suicide, ko (position hash),
          Ruleset interface + ChineseRuleset

Step 4  — GameLogic: pass, resignation, game phases

Step 5  — GameLogic: dead stone marking, scoring (area scoring)

Step 6  — DB migrations + Eloquent models + factories
          Includes: users.is_bot, games.expires_at + index,
          moves.board_state BYTEA

Step 7  — Auth (email/password Sanctum), no Google yet

Step 8  — REST API: create game, move, pass, resign
          Move handler must run in a transaction with
          pg_advisory_xact_lock(game_id), writes board_state and
          updates games.expires_at

Step 9  — Reverb + Broadcasting events
          BROADCAST_CONNECTION=redis, events published to
          Redis pub/sub, no synchronous Reverb calls

Step 10 — Google OAuth

Step 11 — Frontend: lightweight TS game module (legality
          validation + hover-preview only, no captures or hash) +
          Vitest tests on shared fixtures with PHPUnit

Step 12 — Frontend: SVG board, local game against self

Step 13 — Frontend: API integration, realtime via Echo

Step 14 — Final phase in UI (dead stone marking)

Step 15 — Correspondence: jobs, email, timers via games.expires_at,
          Laravel Horizon setup

Step 16 — Lobby, invitations

Step 17 — Profile, history, statistics
```

Each step is 3–10 commits in Conventional Commits format. Each commit: compiles, tests green, does one meaningful thing.

---

## Repository structure

```
go-game/                  # directory name (historical)
├── CLAUDE.md
├── README.md
├── .gitignore            # monorepo-wide
├── .github/
│   └── workflows/
│       └── ci.yml        # GitHub Actions
├── wiki/                 # dev notes (not for end users, not in git)
│   ├── README.md         # table of contents
│   └── *.md
├── backend/              # Laravel 12, DDEV project root
│   ├── .ddev/
│   │   └── config.yaml
│   ├── app/
│   │   ├── Game/         # Pure PHP, no Illuminate\*
│   │   │   ├── Board.php
│   │   │   ├── Stone.php
│   │   │   ├── Position.php
│   │   │   ├── Move.php
│   │   │   ├── Game.php
│   │   │   ├── Rules/
│   │   │   │   ├── Ruleset.php
│   │   │   │   └── ChineseRuleset.php
│   │   │   ├── TimeControl/
│   │   │   │   ├── TimeControl.php
│   │   │   │   ├── AbsoluteTimeControl.php
│   │   │   │   ├── ByoyomiTimeControl.php
│   │   │   │   └── CorrespondenceTimeControl.php
│   │   │   ├── Scoring/
│   │   │   │   └── AreaScorer.php
│   │   │   └── Exceptions/
│   │   ├── Http/
│   │   ├── Models/
│   │   ├── Events/       # Broadcasting events
│   │   └── Jobs/         # Queue jobs
│   ├── tests/
│   │   ├── Unit/Game/    # GameLogic unit tests, no Laravel boot
│   │   ├── Feature/      # API feature tests
│   │   └── fixtures/
│   │       └── game-scenarios.json  # shared with frontend
│   └── ...
└── frontend/             # Svelte 5 + Vite, runs on host
    ├── src/
    │   ├── lib/
    │   │   ├── game/     # TS game module (legality only)
    │   │   ├── board/    # SVG board components
    │   │   ├── stores/
    │   │   └── api.ts
    │   └── ...
    └── ...
```
