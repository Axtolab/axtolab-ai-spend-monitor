# Axtolab AI Spend Monitor

> Per-plugin AI usage and cost tracking for the WordPress 7.0 AI Client. See which plugins make AI calls, how many tokens they use, and what it costs.

[![Latest release](https://img.shields.io/github/v/release/Axtolab/axtolab-ai-spend-monitor?label=latest&color=2ea44f)](https://github.com/Axtolab/axtolab-ai-spend-monitor/releases/latest)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL_v2%2B-blue.svg)](LICENSE)
[![WordPress 7.0+](https://img.shields.io/badge/WordPress-7.0%2B-21759b)](https://wordpress.org)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4)](https://www.php.net/)

---

## Why this exists

WordPress 7.0 introduced the AI Client and the Connectors screen: you configure one provider API key, and any plugin on your site can use it. **What WordPress doesn't show you is which plugin is spending your money.**

AI Spend Monitor adds that visibility. It records every AI call made through the WordPress AI Client to a local database table — source plugin, provider, model, prompt/completion tokens, timestamp — and renders a dashboard under **Axtolab → AI Spend Monitor** in wp-admin.

## What it does

- 📊 **Per-plugin AI usage** — exactly which plugins and themes are making AI calls, with calls, prompt tokens, and completion tokens for each.
- 💵 **Estimated cost per plugin** — token counts converted to USD using a bundled price table for popular OpenAI, Anthropic, and Google models. Filterable, no remote lookup.
- 📈 **30-day cost chart** — sudden spike visibility the day it happens, not when the invoice arrives.
- 📋 **Recent call log** — latest AI calls with source, provider, model, and token counts.
- 📦 **CSV export** — download recorded calls for any period for accounting or review.
- 📧 **Optional spend email** — one email per month when estimated sitewide spend passes a dollar amount you set (off by default; set to 0 to disable).
- 🚀 **Zero configuration** — activate and it starts recording. No API keys, no account, no setup.

## What it deliberately doesn't do

- ❌ **No external services.** No remote calls, no telemetry, no analytics. All data stays in your WordPress database.
- ❌ **No prompt or response content stored.** Token counts and metadata only.
- ❌ **No blocking or enforcement.** AI Spend Monitor is a *monitor*. If you want budget caps + a sitewide kill switch + hard-stop margin, the paid [Axtolab AI Spend Governance](https://axtolab.com/products/ai-spend-governance) is a separate product that pairs with it (not required).

## Requirements

- **WordPress 7.0 or newer** — the AI Client was introduced in 7.0. The plugin activates on older versions but will not record anything until you upgrade.
- **PHP 7.4 or newer.**

## 📥 Install

**Easiest** *(once WordPress.org approval lands)*: search **"Axtolab AI Spend Monitor"** in **WordPress Admin → Plugins → Add New** and click Install.

**Today, while review is in progress** — grab the latest release zip from this repo:

| File | What it's for | Direct download |
|---|---|---|
| 🔌 **`axtolab-ai-spend-monitor.zip`** | WordPress plugin — upload via Plugins → Add New → Upload Plugin | [Download latest →](https://github.com/Axtolab/axtolab-ai-spend-monitor/releases/latest) |

Then open **Axtolab → AI Spend Monitor** in your WordPress admin. No further setup. The plugin starts recording on the next AI Client call.

## Architecture quick facts (for the curious)

- Records into a single custom database table (`{$wpdb->prefix}aismon_usage`) created via `dbDelta()`.
- Attribution: identifies the source plugin/theme via debug-backtrace inspection at the moment the AI Client fires its lifecycle hooks.
- Retention: daily WP-Cron job prunes records older than 90 days (filterable via `aismon_retention_days`).
- Uninstall: `uninstall.php` removes the table, options, and transients on plugin delete.
- Hooks for developers:
  - `aismon_usage_recorded` — action fired after each recorded call.
  - `aismon_cost_rates` — filter the model price table used for estimates.
  - `aismon_retention_days` — filter the retention window.
  - `aismon_dashboard_after_summary` — action to render additional dashboard panels.

## The paid companion: Spend Governance

AI Spend Monitor shows you what's being spent. **[Axtolab AI Spend Governance](https://axtolab.com/products/ai-spend-governance)** is a separate paid plugin that adds enforcement:

- Multi-threshold budget alerts (50%, 75%, 100% of monthly budget).
- Configurable hard-stop margin (refuses AI calls once a percentage above budget is reached).
- Sitewide kill switch (suspends all AI activity with one click).
- Observability for when policies fire.

Governance is distributed from [axtolab.com](https://axtolab.com), not WordPress.org. Spend Monitor is a hard dependency for Governance — the Governance plugin reads Monitor's recorded usage to decide when to enforce.

AI Spend Monitor never prompts for, requires, or upsells Governance. It works standalone.

## Contributing

Bug reports + feature requests welcome via [GitHub issues](https://github.com/Axtolab/axtolab-ai-spend-monitor/issues). Particularly interested in:

- New model pricing entries when providers update their public price lists.
- Edge cases in plugin/theme attribution (especially for sites running unusual loaders or multisite networks).
- Reproducible reports of recorded-but-implausible token counts.

If you're submitting a PR: please run PHPCS against the bundled `phpcs.xml` ruleset and confirm activation on a clean WordPress 7.0 testbed with `WP_DEBUG=true` (no PHP notices) before opening.

## License

GPL-2.0-or-later. Same as WordPress core. See [LICENSE](LICENSE) — or the License header inside `axtolab-ai-spend-monitor.php` if no LICENSE file is shipped in your copy.

## Related

- 🏠 [Axtolab](https://axtolab.com) — publisher.
- 🔌 [Axtolab AI Connector for WordPress](https://github.com/Axtolab/axtolab-ai-connector-for-wordpress-free) — sibling plugin on WordPress.org. Connects Claude / ChatGPT / MCP clients to WordPress. Independent product; no dependency between the two.
- 🛑 [Axtolab AI Spend Governance](https://axtolab.com/products/ai-spend-governance) — paid companion described above.
