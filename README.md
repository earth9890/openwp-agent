# OpenWP

Plugin-native AI agent operating system for WordPress. Executes only registered actions through strict policy gates, with approval queues, backup snapshots, and full audit logging.

**Version:** 0.1.4 &middot; **Requires:** WordPress 6.9+ &middot; **PHP:** 7.4+ &middot; **License:** GPLv2 or later

## Features

### AI Content Generator (Gutenberg Block)

- Block type `openwp/ai-content-generator` with 15 content types (10 sections + 5 full pages) and 5 tones
- Real-time SSE streaming preview during generation
- Global floating modal (Cmd+Shift+Space) with draggable, persistent positioning
- AI Modify toolbar on every block — Humanize, Fix Grammar, Improve, Simplify, Shorter, Longer + custom instructions
- Per-block provider/model override and custom color palette in Inspector sidebar
- `parseAndRecover()` auto-fixes malformed Gutenberg markup across all LLM providers
- Prompt enhancement via AI before generation
- Cancel generation via AbortController

### Agent Engine (57 Registered Actions)

| Category | Actions |
|----------|---------|
| Content | `get_posts`, `create_post`, `update_post`, `publish_post`, `delete_post` |
| Taxonomy | `get_terms`, `create_term`, `update_term`, `delete_term` |
| Media | `get_media`, `upload_media`, `update_media`, `delete_media` |
| Comments | `get_comments`, `update_comment`, `delete_comment` |
| Users | `get_users`, `create_user`, `update_user`, `delete_user`, `set_user_role` |
| Options | `get_option`, `update_option`, `delete_option` |
| Memory | `remember`, `forget`, `list`, `clear` |
| Plugins | `list`, `install`, `activate`, `deactivate`, `update`, `delete` |
| Themes | `list`, `install`, `switch`, `update`, `delete` |
| Database | `optimize`, `repair`, `analyze`, `query` (SQL Guard protected) |
| MCP Bridge | `read_tool` (low), `write_tool` (medium), `admin_tool` (high) |

Each action has: risk level (`low`/`medium`/`high`/`critical`), capability requirement, JSON schema, mutation flag, and backup flag.

**Execution pipeline:** LLM response &rarr; validate JSON contract &rarr; PolicyEngine &rarr; Rate_Limiter &rarr; Backup_Service (if needed) &rarr; Action_Executor &rarr; Log_Repository

### MCP Server (59+ Tools, 6 Modules)

HTTP + SSE transports with bearer token authentication and per-module toggles.

| Module | Tools | Description |
|--------|-------|-------------|
| Core | 11 | Posts, users, comments, themes, media, terms |
| WooCommerce | 24 | Products, orders, stock, customers, analytics, reviews |
| Plugins | 12 | CRUD + file ops + create/copy/rename |
| Themes | 12 | CRUD + file ops + create/copy/rename |
| Database | 1 | SQL query with guard rails |
| Polylang | 11 | Languages, translations, status |

### Sitewide Chatbot

- Page context awareness and smart routing (conversational vs agent actions)
- File attachments (5 max, 10MB each) with blocked executables
- Multi-turn conversation (3 turns) with session isolation
- Multimodal support (images via data URI)
- SSE streaming responses

### Admin Dashboard (9 Screens)

Dashboard, Console (streaming agent), Actions (57 catalog), Approvals (queue), Logs (audit + rollback), Memory (CRUD), Backups (restore), Settings (5 sections), Onboarding (5-step wizard)

### Security (6 Systems)

1. **PolicyEngine** — risk-level routing: low auto-execute, medium/high require approval, critical requires typed confirmation (`APPROVE`)
2. **Encryption_Service** — Libsodium `sodium_crypto_secretbox` (primary), OpenSSL AES-256-CBC (fallback) for API keys at rest
3. **Provider_Key_Manager** — encrypted storage in `wp_options`, masked display (first 4 + last 4 chars only)
4. **SQL Guard** — blocks `INTO OUTFILE`, `LOAD_FILE`, `SLEEP`, multi-statement detection
5. **Rate_Limiter** — per-user daily limits (50 actions, 120K tokens) + site-wide limits (500 actions, 1.2M tokens) + cooldown
6. **Backup_Service** — pre-action database snapshots with gzip compression + SHA256 checksums, restore with confirmation

### 4 Custom Capabilities

`openwp_run_agent`, `openwp_approve_actions`, `openwp_manage_settings`, `openwp_view_logs` — granted to Administrator role.

## Requirements

- WordPress 6.9+
- PHP 7.4+
- Single-site install (multisite not supported)
- cURL extension (required for SSE streaming)

## Installation

1. Upload the `openwp` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Go to **WP Admin &rarr; OpenWP**.
4. Complete the 5-step onboarding wizard.
5. Add your API key(s) in **Settings &rarr; Connections** (OpenAI, Anthropic, GLM, or OpenRouter).
6. Use the **Console** to run agent commands, or insert the AI block in the editor.

## Architecture

### PHP Backend (`inc/`)

PSR-4 autoloaded under `OpenWP\Inc\` namespace. All classes use the `Get_Instance` singleton trait.

**Boot sequence:** `openwp.php` &rarr; `Loader::get_instance()` &rarr; on `plugins_loaded`:
1. `Capability_Manager::grant_capabilities()`
2. `Migrations::maybe_upgrade()` (DB schema versioning)
3. `Action_Bootstrap` &rarr; registers 57 actions in `Action_Registry`
4. `Ability_Bootstrap` &rarr; action metadata for UI
5. `Api_Init` &rarr; REST API routes under `openwp/v1`
6. `Admin_Page` + `Editor_Block` &rarr; WP admin integration

**Key directories:**

| Directory | Purpose |
|-----------|---------|
| `inc/Actions/` | Action handlers (Content, Taxonomy, Media, User, Plugin, Theme, Database, Memory, Option, Comment) + registry + executor |
| `inc/Agent/` | `Agent_Engine` — prompt-to-action orchestration |
| `inc/API/` | REST controller (`OpenWP_Controller`), SSE streaming (`SSE_Response`), base class |
| `inc/Providers/` | LLM clients (OpenAI, Anthropic, GLM, OpenRouter) + `Provider_Factory` + `Stream_Transport` |
| `inc/Security/` | `PolicyEngine`, `Sql_Guard`, `Rate_Limiter`, `Provider_Key_Manager`, `Encryption_Service` |
| `inc/Backup/` | Pre-action snapshots, restore, cleanup |
| `inc/Logs/` | Audit log, approval queue, rollback |
| `inc/Database/` | `Migrations` (5 custom tables), `Tables` (schema definitions) |
| `inc/Memory/` | Agent memory CRUD with guard rails |
| `inc/Core/` | `Action_Bootstrap`, `Settings` (defaults + persistence) |

### JavaScript Frontend (`src/`)

React 18 SPA with Tailwind CSS 3.4 (preflight disabled), Radix UI primitives, Lucide icons, Sonner toasts.

Three webpack entry points:

| Entry | Source | Build Output |
|-------|--------|-------------|
| `index` | `src/admin/index.js` | Admin dashboard SPA |
| `ai-content-generator-block` | `src/editor/index.js` | Gutenberg block |
| `sitewide-chatbot` | `src/sitewide-chatbot/index.js` | Frontend chatbot |

**`src/admin/`** — Main admin dashboard with 9 tab screens, hooks-based state management.

**`src/editor/`** — Gutenberg block: form/generating/preview states, `useStreamingGeneration` hook, `parseAndRecover()` block recovery, global modal, AI toolbar.

**`src/shared/`** — Shared API layer: `request()` (via `@wordpress/api-fetch`), `streamRequest()` (SSE via fetch + ReadableStream), `uploadRequest()` (multipart).

**`src/sitewide-chatbot/`** — Frontend chatbot widget with page context and file attachments.

### Database (5 Custom Tables)

| Table | Purpose |
|-------|---------|
| `openwp_logs` | Execution audit trail |
| `openwp_approvals` | Pending action approvals (risk_level, typed_confirmation) |
| `openwp_memory` | Agent memory store (preference / constraint / fact / workflow) |
| `openwp_backups` | Pre-action snapshots (path, checksum, expiration) |
| `openwp_usage_daily` | Rate limiting counters (per-user + site-wide) |

Managed via `inc/Database/Migrations.php` with version checking against `OPENWP_DB_VERSION`.

### LLM Providers

Four provider clients implementing `ProviderClientInterface`:

| Provider | API | Streaming |
|----------|-----|-----------|
| OpenAI | Responses API | SSE |
| Anthropic | Messages API | SSE |
| GLM | Z.AI Chat Completions | SSE |
| OpenRouter | Unified gateway (50+ models) | SSE |

All support streaming via `Stream_Transport`. Keys stored encrypted via `Encryption_Service` + `Provider_Key_Manager`.

## REST API

Namespace: `openwp/v1`

### Editor Endpoints
| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/editor/generate` | Content generation |
| `POST` | `/editor/generate/stream` | Streaming content generation |
| `POST` | `/editor/modify/stream` | Modify existing blocks |
| `POST` | `/editor/enhance-prompt` | AI prompt improvement |

### Agent Endpoints
| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/agent/execute` | Agent execution |
| `POST` | `/agent/execute/stream` | Streaming agent execution |

### Chatbot Endpoints
| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/chatbot/upload` | File attachments |
| `POST` | `/chatbot/execute/stream` | Sitewide chatbot |
| `POST` | `/chatbot/session/clear` | Session cleanup |

### Admin CRUD Endpoints
| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/bootstrap` | Config + capabilities |
| `GET/POST` | `/settings` | Settings read/update |
| `GET` | `/actions` | 57 action catalog |
| `GET` | `/approvals` | Approval queue |
| `POST` | `/approvals/{id}/approve` | Approve action (typed confirmation for critical) |
| `POST` | `/approvals/{id}/reject` | Reject action |
| `GET` | `/logs` | Audit log |
| `POST` | `/logs/{id}/rollback` | Rollback action |
| `GET/POST/DELETE` | `/memory` | Memory CRUD |
| `GET` | `/backups` | Backup list |
| `POST` | `/backups/{id}/restore` | Restore backup |
| `GET` | `/installed-plugins` | Plugin list |
| `GET` | `/mcp/tools` | MCP tool catalog |
| `GET/POST` | `/onboarding/*` | Onboarding flow |

## Build & Development

### Prerequisites

```bash
npm install
composer install
```

### Development Commands

```bash
# JavaScript (wp-scripts)
npm run start          # Dev build with hot reload
npm run build          # Production build
npm run lint:js        # ESLint (WordPress config)
npm run lint:css       # Stylelint (WordPress config)
npm run format         # Prettier formatting

# PHP (Composer)
composer test          # PHPUnit tests
composer lint          # PHPCS (WordPress Coding Standards)
composer format        # PHPCBF auto-fix
composer phpstan       # PHPStan Level 8 static analysis
composer lint-and-format   # Auto-fix then lint
composer stan-and-format   # Auto-fix then PHPStan

# i18n
npm run i18n           # Generate .pot file
npm run i18n:po        # Update .po files
npm run i18n:mo        # Compile .mo files
npm run i18n:json      # Generate JSON translations
```

### Release Pipeline (Grunt)

```bash
grunt release          # Build distributable zip: clean → copy → compress → clean
grunt version-bump --ver=patch|minor|major   # Bump version across all files
grunt rtl              # Generate RTL CSS variants
grunt readme           # Convert readme.txt → README.md
```

The `grunt version-bump` command syncs version across:
- `package.json` (via grunt-bumpup)
- `openwp.php` header (`Version: x.x.x`)
- `openwp.php` constant (`OPENWP_VERSION`)
- `readme.txt` stable tag
- All PHP `@since x.x.x` docblocks

## Code Quality

### PHPStan (Static Analysis)

- **Level 8** (strictest) — configured in `phpstan.neon`
- Uses `szepeviktor/phpstan-wordpress` extension for WordPress function stubs
- Bootstrap file (`tests/bootstrap-phpstan.php`) defines all plugin constants
- Baseline file (`phpstan-baseline.neon`) for incremental adoption
- Scans: `openwp.php`, `loader.php`, `constants.php`, `inc/`

### PHPCS (Coding Standards)

Configured in `phpcs.xml` with the following rulesets:

- **WordPress-Core** — code style
- **WordPress-Docs** — documentation standards
- **WordPress-Extra** — best practices
- **WordPress-VIP-Go** — VIP-level quality
- **WordPress.Security.EscapeOutput** — output escaping
- **pheromone/phpcs-security-audit** — security-focused sniffs

Additional configuration:
- Text domain: `openwp` (i18n enforcement)
- PHP compatibility: 7.4+
- Parallel processing: 20 threads
- Short array syntax allowed, Yoda conditions not required

### JavaScript Linting

- **ESLint** via `wp-scripts lint-js` (WordPress ESLint configuration)
- **Stylelint** via `wp-scripts lint-style` (WordPress Stylelint configuration)
- **Prettier** via `wp-scripts format`

## Testing

### PHPUnit

Configured in `phpunit.xml.dist`. Bootstrap (`tests/bootstrap.php`) stubs core WordPress functions (`__()`, `sanitize_text_field`, `rest_sanitize_boolean`, `absint`, `WP_Error`, `is_wp_error`).

**Unit Tests** (`tests/unit/`):

- `SqlGuardTest` — verifies SQL Guard blocks injection attempts (`INTO OUTFILE`), classifies write queries correctly
- `SchemaValidatorTest` — verifies type coercion, HTML sanitization, required field enforcement, unknown field rejection

**Integration Tests** (`tests/integration/`):

- `ApiRoutesTest` — scaffold for WP REST route integration tests (requires full WP environment)

### E2E Test Plan

Manual checklist (`tests/e2e/openwp.spec.txt`) covering the full plugin lifecycle:

1. Activate plugin and open WP Admin &rarr; OpenWP
2. Add OpenAI or Anthropic API key in Settings
3. Execute prompt: "Create a draft post titled Test"
4. Verify pending approvals for high-risk actions
5. Approve a high/critical action and ensure backup is created
6. Trigger rollback from logs and verify action reversal

> **Note:** E2E is a manual checklist — the plugin operates within WordPress admin context requiring a live WP environment, authenticated sessions, and external LLM API keys. Automated E2E would require `wp-env` + mocked LLM responses.

## Project Structure

```
openwp/
├── openwp.php              # Plugin entry point
├── loader.php              # Boot sequence
├── constants.php           # Plugin constants
├── inc/                    # PHP backend (PSR-4: OpenWP\Inc\)
│   ├── Actions/            # 57 action handlers + registry + executor
│   ├── Agent/              # Agent_Engine orchestration
│   ├── API/                # REST controller + SSE streaming
│   ├── Backup/             # Pre-action snapshots
│   ├── Core/               # Action_Bootstrap, Settings
│   ├── Database/           # Migrations + table schemas
│   ├── Logs/               # Audit log + approval queue
│   ├── Memory/             # Agent memory CRUD
│   ├── MCP/                # MCP server (6 modules, 59+ tools)
│   ├── Providers/          # LLM clients + Stream_Transport
│   ├── Security/           # PolicyEngine, SQL Guard, Rate Limiter, Encryption
│   ├── Traits/             # Get_Instance singleton trait
│   └── Utils/              # Schema_Validator, helpers
├── src/                    # JavaScript frontend
│   ├── admin/              # Admin dashboard SPA (9 screens)
│   ├── editor/             # Gutenberg block + AI toolbar
│   ├── shared/             # API layer (request, streamRequest, uploadRequest)
│   └── sitewide-chatbot/   # Frontend chatbot widget
├── build/                  # Compiled assets (webpack output)
├── tests/                  # Test suites
│   ├── unit/               # PHPUnit unit tests
│   ├── integration/        # Integration test scaffolds
│   ├── e2e/                # E2E test plan
│   ├── bootstrap.php       # PHPUnit bootstrap (WP stubs)
│   └── bootstrap-phpstan.php  # PHPStan bootstrap (constants)
├── languages/              # i18n (.pot, .po, .mo, .json)
├── phpstan.neon            # PHPStan Level 8 config
├── phpstan-baseline.neon   # PHPStan baseline
├── phpcs.xml               # PHPCS WordPress standards config
├── phpunit.xml.dist        # PHPUnit config
├── webpack.config.js       # 3 entry points
├── tailwind.config.js      # Custom palette, preflight disabled
├── Gruntfile.js            # Release pipeline + version bumping
├── composer.json           # PHP dependencies + scripts
├── package.json            # JS dependencies + scripts
└── readme.txt              # WordPress.org readme
```

## Tailwind Configuration

Custom color palette in `tailwind.config.js`:

| Token | Value | Usage |
|-------|-------|-------|
| `primary` | `#0057ff` | Headings, links, primary actions |
| `accent` | `#ff7d1f` | Highlights, badges, CTAs |
| `ink` | `#10203b` | Body text |
| `muted` | `#5f6f89` | Secondary text |
| `line` | `#d9e2f2` | Borders, dividers |
| `surface` | `#ffffff` | Card backgrounds |
| `bg` | `#f3f6fb` | Page backgrounds |
| `success` | `#0f8b57` | Success states |
| `warning` | `#946200` | Warning states |
| `danger` | `#b42318` | Error states |

Fonts: Sora / Manrope (sans-serif), JetBrains Mono (monospace). Preflight disabled for WordPress admin compatibility.

## Safety Notes

- No dynamic PHP execution — all actions must be pre-registered in `Action_Registry`
- All executions must match registered actions and JSON schemas
- SQL blocklist includes `INTO OUTFILE`, `LOAD_FILE`, `SLEEP`, and multi-statement queries
- Critical operations require typed confirmation (`APPROVE`)
- API keys encrypted at rest, never exposed to frontend
- Pre-action backups for high/critical write operations with SHA256 verification
- Rate limiting: per-user + site-wide daily action and token budgets

## Changelog

### 0.1.4
- Enabled provider streaming (`stream: true`) for OpenAI, GLM, and Anthropic with SSE aggregation
- Added OpenRouter provider (50+ models, 12 free)
- AI Content Generator block with 15 content types and 5 tones
- MCP server with HTTP + SSE transports, 6 modules, 59+ tools
- Sitewide chatbot with page context, file attachments, multimodal support
- 9-screen admin dashboard SPA
- 57 registered agent actions across 11 categories
- 6-system security pipeline (PolicyEngine, Encryption, SQL Guard, Rate Limiter, Backups, Key Manager)
- 5 custom database tables with migration versioning
- PHPStan Level 8 + PHPCS WordPress standards + security audit sniffs
- Grunt release pipeline with automated version bumping
- 5-step onboarding wizard
- 4 custom capabilities for role-based access

### 0.1.3
- Added GLM (Z.AI) provider support with BYOK, model defaults, and admin UI controls

### 0.1.1
- Redesigned admin UI and improved frontend architecture

### 0.1.0
- Initial release
