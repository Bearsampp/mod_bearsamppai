# Bearsampp AI (mod_bearsamppai)

A Joomla 5.4/6 site module that aggregates your recent articles, Kunena forum topics and FAQ articles into a compact knowledge-base panel and optionally adds a floating **Gemini-powered AI chat widget** that answers visitor questions using that same content.

## Features

- **Knowledge base chat (Gemini)** — a floating chat widget backed by the Google Gemini API (OpenAI-compatible endpoint, free tier supported). Answers are grounded strictly in your site content.
- **Prompt-grounded answers** — the AI receives articles, FAQ entries and forum topics as context and is instructed to answer only from that content, falling back to a configurable message when no knowledge matches.
- **Recent articles block** — newest published articles, optionally restricted by a single category and/or tags.
- **Forum topics block** — latest published Kunena topics with category and last-post info.
- **FAQ accordion block** — articles from a chosen category rendered as Bootstrap collapse accordions, first item open by default.
- **Customizable chat UI**:
  - Position: bottom-right, bottom-left, middle-right or middle-left (configurable offset from the viewport edge)
  - Panel width and height in px, full viewport clamping on small screens
  - Theme: auto (follows the visitor's system preference), light, dark, plus a runtime toggle persisted in `localStorage`
  - Copy conversation button (timestamped transcript to clipboard)
  - Welcome message shown as the first assistant message (optional)
  - Connection status dot in the header that periodically verifies the chat is reachable (optional)
  - Custom button label, heading and input placeholder
- **Accessibility** — ARIA live regions, `aria-busy` state, `aria-expanded`/`aria-controls` on the toggle, Escape-to-close, `Ctrl+/` shortcut to open the first widget, `:focus-visible` outlines, `prefers-reduced-motion` and `forced-colors` support.
- **Markdown rendering** — assistant replies render fenced code blocks, inline code, links, bold and italic client-side.
- **Modern Joomla structure** — namespaced `src/`, DI service provider, MVC-backed content queries, web assets, `com_ajax` endpoint.

## Requirements

- Joomla 5.4 or newer (Joomla 6 ready)
- PHP 8.1 or newer
- Kunena 6.x (optional, only for the forum topics block)
- A Google AI Studio API key (optional, only for the chat widget — free tier works, get one at https://aistudio.google.com/app/apikey)

## Installation

1. Download the latest install package from the [Releases](https://github.com/Bearsampp/mod_bearsamppai/releases) page.
2. In Joomla administrator go to **System → Install → Extensions**, upload or drop the `mod_bearsamppai_vX.Y.Z.zip` package and install.
3. Go to **Content → Site Modules**, click **New**, search for *Bearsampp AI*, then configure the module.

## Configuration

### Basic tab

| Parameter | Description |
| --- | --- |
| Show recent articles | Toggle the recent articles block. |
| Articles category | Restrict the articles block to a single category (unselected = all published). |
| Article tags | Only show articles that have any of these tags (unselected = ignore tags). |
| Number of articles to show | 1–50, default 5. |
| Show forum topics | Toggle the Kunena topics block (Kunena must be installed). |
| Number of forum topics to show | 1–50, default 5. |
| Show FAQ | Toggle the FAQ accordion block. |
| FAQ category | Category whose articles are rendered as FAQ accordion items. |
| Number of FAQ items to show | 1–100, default 10. |

### AI Chat (Gemini) tab

| Parameter | Description |
| --- | --- |
| Show AI chat widget | Enable the floating chat widget. |
| Gemini API key | Your Google AI Studio (Generative Language) API key. |
| Gemini model | Model to use, e.g. `gemini-2.5-flash`, `gemini-2.5-flash-lite`. |
| Gemini endpoint | OpenAI-compatible chat endpoint (defaults to the Google Gemini API). |
| Max response tokens | 64–4096, default 512. |
| Temperature | 0–1, default 0.2 (low = factual answers). |
| Request timeout (seconds) | 5–120, default 30. |
| Knowledge context limit (characters) | Character budget for the content sent to the AI, 1000–50000, default 20000. |
| Chat button label | Text shown on the floating button and panel header. |
| Input placeholder | Placeholder text of the message input. |
| Fallback answer | Reply returned when the knowledge base does not contain relevant information. |
| Widget position | bottom-right (default), bottom-left, middle-right, middle-left. |
| Panel width (px) | 260–900, default 400. |
| Panel height (px) | 300–1200, default 500. |
| Horizontal offset (px) | 0–200 distance from the left/right viewport edge, default 20. |
| Vertical offset (px) | 0–200 distance from the bottom edge (or used as fallback for middle anchor), default 20. |
| Default theme | auto (system preference), light, dark. Visitors can still toggle at runtime. |
| Show copy conversation button | Adds the transcript-to-clipboard button to the panel header. |
| Welcome message | Optional first assistant message shown when the chat opens (empty = no greeting). |
| Show connection status | Adds a small dot to the panel header that checks the chat endpoint every interval. |
| Status check interval (seconds) | 10–600, default 30. |

### Advanced tab

Standard Joomla module options — alternate layout, module CSS suffix, caching.

## How the chat works

The module renders as a knowledge base panel. When the chat is enabled, each message from a visitor is POSTed over `com_ajax`:

```
index.php?option=com_ajax&module=bearsamppai&method=ask&format=json
```

with `module_id` and `message`. The server-side helper (`src/Helper/BearsamppaiHelper.php`) then:

1. Loads the same content the module renders — articles (matching category/tags), FAQ articles and recent Kunena topics — and strips it to plain text.
2. Assembles a compact context string, walking each source in order until the configured character budget is reached.
3. Sends a `system` prompt plus the visitor question to the configured Gemini endpoint over the OpenAI-compatible API using a Bearer token.
4. Returns the answer (or the fallback message when no knowledge is available) as JSON consumed by the widget.

The system prompt instructs the model to answer **only** from the supplied knowledge base context and not to rely on prior knowledge, web browsing or guesswork.

### Com_ajax fallback

`com_ajax` first resolves the namespaced helper through the module `HelperFactory`. If that path is unavailable it falls back to the legacy `helper.php` bridge (`ModBearsamppaiHelper::askAjax()`), which delegates to the same class — the logic stays in a single place either way.

## Customizing the look

The chat widget is styled entirely with CSS custom properties defined on `.mod-bearsamppai__chat`, so you can restyle it from your template without touching the module files:

```css
.mod-bearsamppai__chat {
	--mbai-primary: #4078c0;      /* Bearsampp brand accent / button / header */
	--mbai-bg: #ffffff;            /* panel background */
	--mbai-fg: #222222;            /* panel text */
	--mbai-assistant-bg: #f1f3f5;  /* assistant message bubble */
	--mbai-link: #4078c0;          /* links in assistant replies */
}
```

All variables have a matching set of dark values under `[data-theme-scheme="dark"]`. Use a custom layout (module layout override) or `moduleclass_sfx` for per-instance styling.

## Accessibility

- Toggle button exposes `aria-expanded`/`aria-controls`; header `aria-label`s cover copy, theme and close actions.
- Messages container uses `role="log"` and `aria-live="polite"`; a separate visually hidden `role="status"` region announces copy and theme feedback.
- `aria-busy` is set on the message feed while a request is in flight.
- `Escape` closes the panel and returns focus to the toggle; `Ctrl+Shift+/` (or `Ctrl+/`) opens the first chat widget.
- Keyboard-visible `:focus-visible` outlines everywhere, reduced-motion and forced-colors (Windows High Contrast) friendly.

## Development

The repository is the extension source; the install ZIP is produced by a GitHub Actions workflow using [Joomla Packager](https://github.com/N6REJ/joomla-packager) on every merged PR to `main` and via manual workflow dispatch. The action is pinned to a commit SHA (`d69f73b`, release `2026.9.27.2`) rather than a branch, so packaging cannot change under you without a deliberate bump.

### Releases

Versions are **date-based** (`2026.09.27`, `2026.09.28`, …). The packager generates the version itself and ignores the manifest `<version>`, so each run produces a new tag, a new `mod_bearsamppai_<version>.zip` release asset, and a version bump committed back to `main` (`commit-changes: 'true'`). The manifest carries a date-based version so a freshly installed copy and the feed agree.

Re-running the workflow does **not** create a second release. The packager fingerprints the files that ship, ignoring the version bump, changelog, update feed and CI config, and reuses the existing version when nothing a user would install has changed. Re-run the job or dispatch it again as often as you like — installed sites only see a prompt when there is genuinely something new.

### Update server

`updates.xml` is the Joomla Update System feed and is served straight from this repository:

```
https://raw.githubusercontent.com/Bearsampp/mod_bearsamppai/main/updates.xml
```

The manifest registers it as an extension update server, so installed sites see new releases in **System → Update → Extensions** and **Joomla Update**.

The feed is published **after** the release: the packager confirms the release asset exists and only then rewrites the `<version>` and `downloadurl` of `updates.xml`, failing the run rather than advertising a version that cannot be downloaded.

```bash
# Lint PHP sources (require PHP 8.1+)
php -l mod_bearsamppai.xml   # XML manifest
php -l tmpl/chat.php
php -l src/Helper/BearsamppaiHelper.php
# ...and so on for each .php file
```

File layout:

```
mod_bearsamppai/
├── mod_bearsamppai.xml          # Extension manifest + module parameters
├── helper.php                   # Legacy com_ajax helper bridge
├── updates.xml                  # Joomla Update System feed
├── services/provider.php        # DI service provider
├── src/
│   ├── Dispatcher/Dispatcher.php
│   └── Helper/
│       ├── ModuleHelper.php         # Content queries (articles, FAQ, forum)
│       └── BearsamppaiHelper.php    # Gemini chat / knowledge base logic
├── tmpl/
│   ├── default.php                  # Main layout
│   ├── articles.php                 # Recent articles block
│   ├── faq.php                      # FAQ accordion block
│   ├── forum.php                    # Kunena topics block
│   └── chat.php                     # Chat widget markup
├── media/
│   ├── css/chat.css                 # Chat widget styles + themes
│   └── js/chat.js                   # Chat widget behaviour
├── language/en-GB/                 # en-GB translations
└── .github/workflows/package-packager.yml  # Release packaging
```

## License

GNU General Public License version 3. See [LICENSE](LICENSE).

Bearsampp AI was created by [Bearsampp](https://bearsampp.com). For support: support@bearsampp.com