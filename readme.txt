=== AI Spend Monitor by Axtolab ===
Contributors: axtolab
Tags: ai, cost, usage, tokens, monitoring
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI usage and cost tracking for the WordPress AI Client. See which plugins make AI calls, how many tokens they use, and what it costs.

== Description ==

WordPress 7.0 introduced the AI Client and the Connectors screen: you configure an AI provider API key once, and every plugin on your site can use it. What WordPress does not show you is **which plugin is spending your money**.

AI Spend Monitor records every call made through the WordPress AI Client and gives you a clear dashboard under **Tools → AI Spend Monitor**:

* **Per-plugin AI usage** — see exactly which plugins and themes make AI calls, with calls, prompt tokens, and completion tokens for each.
* **Estimated cost per plugin** — token counts are converted to an estimated USD cost using bundled list prices for popular OpenAI, Anthropic, and Google models.
* **Daily cost chart** — a 30-day view of your estimated AI spend, so a sudden spike is visible the day it happens, not when the invoice arrives.
* **Recent call log** — the latest AI calls with source, provider, model, and token counts.
* **CSV export** — download the recorded calls for any period for accounting or review: source plugin, provider, model, tokens, and estimated cost per call.
* **Spend notification** — optionally get one email per month when estimated sitewide AI spend passes a dollar amount you choose.
* **Zero configuration** — activate it and it starts recording. No API keys, no account, no setup.

= How it works =

The plugin listens to the WordPress AI Client's lifecycle hooks. When any plugin calls the AI Client, the call's token usage and model metadata are recorded in a local database table, and the calling plugin is identified automatically. Old records are pruned after 90 days (filterable).

= Privacy =

All data stays on your site. The plugin records token counts and metadata only — it does **not** store prompt or response content, and it does **not** send anything to any external service. There are no remote calls, no tracking, and no account requirement.

= For developers =

* `aismon_usage_recorded` — action fired after each recorded call.
* `aismon_cost_rates` — filter the model price table used for estimates.
* `aismon_retention_days` — filter the data retention window (default 90 days).
* `aismon_dashboard_after_summary` — action to render additional dashboard panels.

== Frequently Asked Questions ==

= Does this work without WordPress 7.0? =

The plugin activates on older versions but can only record usage on WordPress 7.0 or newer, because it relies on the AI Client that ships with 7.0.

= Are the cost figures exact? =

No — they are estimates. Costs are calculated from recorded token counts using published list prices per model (standard tier). Caching and batch discounts are not modeled, and providers change prices. Always treat your provider's invoice as the source of truth. You can adjust the rates with the `aismon_cost_rates` filter.

= Does it record my prompts? =

No. Only token counts, provider, model, capability, and the calling plugin are stored. Prompt and response content is never recorded.

= Does it slow down AI calls? =

No. Recording happens after the AI call completes and is a single local database insert. If recording ever fails, the original AI call is unaffected.

= Can it alert me about spend? =

Yes — set a monthly dollar amount on the dashboard and the plugin emails you once per month when estimated sitewide spend passes it. This is a notification only.

= Can it block or limit AI usage? =

This plugin is a monitor: it shows you usage and cost, and can notify you. It does not block calls or enforce budgets.

== Screenshots ==

1. The AI Spend Monitor dashboard: summary cards, daily cost chart, and per-plugin usage.
2. Usage by source — every plugin and theme that makes AI calls, with tokens and estimated cost.
3. Recent AI calls log.

== Changelog ==

= 1.0.0 =
* Initial release: per-plugin AI usage recording, cost estimates, daily cost chart, recent call log, CSV export, monthly spend notification, 90-day retention with daily pruning.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
