=== OpenWP ===
Contributors: openwp
Requires at least: 6.7
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.1.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

OpenWP is a plugin-native AI agent that safely executes WordPress operations through a strict action registry.

== Description ==

OpenWP provides:

- AI command console in wp-admin.
- Strict JSON action contract and schema validation.
- Approval queue for medium/high/critical actions.
- Backup gate for high/critical write actions.
- SQL policy enforcement with blocklisted primitives.
- Full audit logs and rollback snapshots.

== Installation ==

1. Upload `openwp` folder to `/wp-content/plugins/`.
2. Activate plugin.
3. Go to `WP Admin -> OpenWP`.
4. Add your OpenAI, Anthropic, or GLM (Z.AI) API key.

== Changelog ==

= 0.1.4 =
* Enabled provider streaming (`stream: true`) for OpenAI, GLM, and Anthropic with SSE aggregation support.

= 0.1.3 =
* Added GLM (Z.AI) provider support with BYOK, model defaults, and admin UI controls.

= 0.1.1 =
* Redesigned admin UI and improved frontend architecture.

= 0.1.0 =
* Initial release.
