# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

OpenWP is a WordPress plugin that provides a plugin-native AI agent operating system. It executes only registered actions through strict policy gates, with approval queues, backup snapshots, and full audit logging. Version 0.1.4, requires WordPress 6.9+ and PHP 7.4+. Single-site only (no multisite).

## Build & Development Commands

```bash
npm run start          # Dev build with hot reload (wp-scripts)
npm run build          # Production build
npm run lint:js        # Lint JavaScript (wp-scripts lint-js)
npm run lint:css       # Lint CSS (wp-scripts lint-style)
npm run format         # Format JS source files

composer test          # Run PHPUnit tests
```

Two webpack entry points defined in `webpack.config.js`:
- `index` → `src/admin/index.js` → `build/index.js` (admin dashboard SPA)
- `ai-content-generator-block` → `src/editor/index.js` → `build/ai-content-generator-block.js` (Gutenberg block)

## Architecture

### PHP Backend (`inc/`)

PSR-4 autoloaded under `OpenWP\Inc\` namespace. All classes use the `Get_Instance` singleton trait (`inc/Traits/Get_Instance.php`).

**Boot sequence** (`loader.php`): `openwp.php` → `Loader::get_instance()` → on `plugins_loaded`:
1. `Capability_Manager::grant_capabilities()`
2. `Migrations::maybe_upgrade()` (DB schema versioning)
3. `Action_Bootstrap` → registers 50+ actions in `Action_Registry`
4. `Ability_Bootstrap` → action metadata for UI
5. `Api_Init` → REST API routes under `openwp/v1`
6. `Admin_Page` + `Editor_Block` → WP admin integration

**Action execution flow:**
```
User prompt → OpenWP_Controller → Agent_Engine → Provider_Factory.create()
→ LLM generates {thought, action, params, confidence}
→ Action_Registry.get() → Action_Executor → capability check → backup (if needed)
→ action callback → log result → return (or queue for approval if high-risk)
```

**Key directories:**
| Directory | Purpose |
|-----------|---------|
| `inc/Actions/` | Action handlers (Content, Taxonomy, Media, User, Plugin, Theme, Database, Memory, Option, Comment) + registry + executor |
| `inc/Agent/` | `Agent_Engine` - prompt-to-action orchestration |
| `inc/API/` | REST controller (`OpenWP_Controller`), SSE streaming (`SSE_Response`), base class |
| `inc/Providers/` | LLM clients (OpenAI, Anthropic, GLM, OpenRouter) + `Provider_Factory` + `Stream_Transport` |
| `inc/Security/` | `PolicyEngine`, `Sql_Guard`, `Rate_Limiter`, `Provider_Key_Manager` (encrypted), `Encryption_Service` |
| `inc/Backup/` | Pre-action snapshots, restore, cleanup |
| `inc/Logs/` | Audit log, approval queue, rollback |
| `inc/Database/` | `Migrations` (5 custom tables), `Tables` (schema definitions) |
| `inc/Memory/` | Agent memory CRUD with guard rails |
| `inc/Core/` | `Action_Bootstrap` (registers actions), `Settings` (defaults + persistence) |

**Action risk levels:** `low`, `medium`, `high`, `critical`. Medium+ require approval. High/critical require pre-action backup. Critical requires typed confirmation ("APPROVE").

### JavaScript Frontend (`src/`)

React SPA using Tailwind CSS 3.4 (preflight disabled for WP admin compatibility), Radix UI primitives, Lucide icons, Sonner toasts.

**`src/admin/`** - Main admin dashboard:
- `app.jsx` - Root component with all state management (hooks-based, no external state library)
- `screens/` - Tab screens: dashboard, console, approvals, settings, actions, memory, logs, backups, onboarding
- `components/ui/` - shadcn-style primitives (Button, Dialog, Select, Table, etc.)
- `hooks/use-hash-tab.js` - Hash-based tab routing
- Path alias: `src/*` maps to `./src/*` (see `jsconfig.json`)

**`src/editor/`** - Gutenberg block for AI content generation:
- `blocks/ai-content-generator/` - Block registration and edit component
- `components/` - Toolbar, preview, progress, prompt box, modify popover
- `hooks/use-streaming-generation.js` - SSE streaming hook for content gen

**`src/shared/`** - Shared between admin and editor:
- `api.js` - `request()` (via `@wordpress/api-fetch`) and `streamRequest()` (SSE via fetch + ReadableStream)
- `constants.js` - Tab keys, onboarding routes, OpenRouter model list

**Data flow:** PHP passes `window.openwpAdmin` (nonce, root URL, onboarding state, capabilities) to JS. All API calls go through `src/shared/api.js`. Streaming uses SSE with `data:` line parsing.

### REST API (`openwp/v1`)

Core endpoints in `OpenWP_Controller`:
- `POST /agent/execute` and `/agent/execute/stream` - Run agent commands
- `POST /editor/generate` - Editor block content generation
- `GET /bootstrap` - Config and capabilities for frontend
- CRUD for: `/approvals`, `/logs`, `/settings`, `/backups`, `/memory`, `/actions`
- Approval: `/approvals/{id}/approve` (typed confirmation), `/approvals/{id}/reject`
- Rollback: `/logs/{id}/rollback`

### Database (5 custom tables)

Managed via `inc/Database/Migrations.php` with version checking against `OPENWP_DB_VERSION`:
- `openwp_logs` - Execution audit trail
- `openwp_approvals` - Pending action approvals (risk_level, typed_confirmation)
- `openwp_memory` - Agent memory store (type: preference/constraint/fact/workflow)
- `openwp_backups` - Pre-action snapshots
- `openwp_usage_daily` - Rate limiting counters

### LLM Providers

Four provider clients implementing `ProviderClientInterface`:
- `OpenAI_Client` (Responses API)
- `Anthropic_Client` (Messages API)
- `GLM_Client` (Z.AI Chat Completions)
- `OpenRouter_Client` (unified gateway)

All support streaming. Keys stored encrypted via `Encryption_Service` + `Provider_Key_Manager`.

## Tailwind Configuration

Custom color palette in `tailwind.config.js`: `primary` (#0057ff), `accent` (#ff7d1f), `ink`, `muted`, `line`, `surface`, `bg`, `success`, `warning`, `danger`. Font: Archivo. Preflight disabled.

## Key Patterns

- **Singleton everywhere:** All PHP service classes use `Get_Instance` trait. Call `ClassName::get_instance()`.
- **Action registration:** New actions go in `inc/Actions/` handler classes, registered in `inc/Core/Action_Bootstrap.php` via `$this->register()` with callback, capability, risk level, schema.
- **Agent contract:** LLM must return `{thought, action, params, confidence}` JSON. The `Agent_Engine` validates and routes to `Action_Executor`.
- **SQL safety:** `Sql_Guard` blocks dangerous SQL patterns (INTO OUTFILE, LOAD_FILE, etc.) for `db_query` action.
- **No dynamic PHP execution** - all actions must be pre-registered.

## Constants

Defined in `constants.php`: `OPENWP_DB_VERSION`, `OPENWP_OPTION_SETTINGS`, `OPENWP_OPTION_PROVIDER_KEYS`, `OPENWP_OPTION_ACTION_POLICIES`, `OPENWP_OPTION_RATE_LIMITS`, `OPENWP_DISABLE_AGENT`.

Plugin constants in `openwp.php`: `OPENWP_FILE`, `OPENWP_BASE`, `OPENWP_DIR`, `OPENWP_URL`, `OPENWP_VERSION`.
