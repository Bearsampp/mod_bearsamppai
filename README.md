# Bearsampp AI (mod_bearsamppai)

A Joomla 5.4/6 site module that adds a floating **Gemini-powered AI chat widget** to your site. Visitor questions are answered by the Google Gemini API, grounded strictly in your own content — the article categories you select, your FAQ articles (title as the question, text as the answer) and, when you use Kunena, your latest forum topics.

## Features

- **Grounded AI chat (Gemini)** — a floating chat widget backed by the Google Gemini API (OpenAI-compatible endpoint, free tier supported). Answers are grounded strictly in your site content.
- **Prompt-grounded answers** — the AI receives the articles, FAQ entries and forum topics you select as context and is instructed to answer only from that content, falling back to a configurable message when no knowledge matches. Content is used as grounding context only; the module does not render content blocks of its own.
- **You choose the knowledge base** — multi-select the article categories to draw from, point the FAQ field at your FAQ category, and opt in to Kunena forum topics with a single switch. Installing Kunena never changes what the AI can see.
- **Customizable chat UI**:
  - Position: bottom-right, bottom-left, middle-right or middle-left (configurable offset from the viewport edge)
  - Panel width and height in px, full viewport clamping on small screens
  - Collapsed launcher reduces to a round icon on phones so it never covers a large part of the screen; set *Show button label on mobile* to Yes to keep the text there
  - Theme: auto (follows the visitor's system preference), light, dark, plus a runtime toggle persisted in `localStorage`
  - Copy conversation button (timestamped transcript to clipboard)
  - Welcome message shown as the first assistant message (optional)
  - Connection status dot in the header that periodically verifies the chat is reachable (optional)
  - Custom button label, heading and input placeholder
- **Accessibility** — ARIA live regions, `aria-busy` state, `aria-expanded`/`aria-controls` on the toggle, Escape-to-close, `Ctrl+/` shortcut to open the first widget, `:focus-visible` outlines, 48px minimum touch target for the collapsed launcher, `prefers-reduced-motion` and `forced-colors` support.
- **Markdown rendering** — assistant replies render fenced code blocks, inline code, links, bold and italic client-side.
- **Modern Joomla structure** — namespaced `src/`, DI service provider, MVC-backed content queries, web assets, `com_ajax` endpoint.

## Requirements

- Joomla 5.4 or newer (Joomla 6 ready)
- PHP 8.1 or newer
- Kunena 6.x (optional, only when you switch on *Include Kunena forum topics*)
- A Google AI Studio API key (optional, only for the chat widget — free tier works, get one at https://aistudio.google.com/app/apikey)

## Installation

1. Download the latest install package from the [Releases](https://github.com/Bearsampp/mod_bearsamppai/releases) page.
2. In Joomla administrator go to **System → Install → Extensions**, upload or drop the `mod_bearsamppai_vX.Y.Z.zip` package and install.
3. Go to **Content → Site Modules**, click **New**, search for *Bearsampp AI*, then configure the module.

## Configuration

### Knowledge Base Content tab

| Parameter | Description |
| --- | --- |
| Article categories | Multi-select. Categories whose published articles feed the AI. Any selected category counts; empty means all published articles site-wide. |
| FAQ category | The category holding your FAQ articles. Empty = no FAQ content. |
| Include Kunena forum topics | Default **No**. Set to Yes to add published topics to the context. |

There is no per-source item count: everything published in a selected scope is a candidate and the knowledge context limit is the only cap.

See [Choosing the knowledge base content](#choosing-the-knowledge-base-content) for how these combine.

### AI Chat (Gemini) tab

| Parameter | Description |
| --- | --- |
| Show AI chat widget | Enable the floating chat widget. |
| Gemini API key | Your Google AI Studio (Generative Language) API key. |
| Gemini model | Model to use, e.g. `gemini-3.8-flash` (default). |
| Gemini endpoint | OpenAI-compatible chat endpoint (defaults to the Google Gemini API). |
| Max response tokens | 64–4096, default 512. |
| Temperature | 0–1, default 0.2 (low = factual answers). |
| Request timeout (seconds) | 5–120, default 30. |
| Knowledge context limit (characters) | Character budget for the content sent to the AI, 1000–50000, default 20000. |
| Chat button label | Text shown on the floating button and panel header. |
| Input placeholder | Placeholder text of the message input. |
| Fallback answer | Reply returned when the knowledge base does not contain relevant information. |
| Widget position | bottom-right (default), bottom-left, middle-right, middle-left. The middle options anchor at half the screen height, which is rarely what you want on a phone. |
| Panel width (px) | 260–900, default 400. |
| Panel height (px) | 300–1200, default 500. |
| Horizontal offset (px) | 0–200 distance from the left/right viewport edge, default 20. |
| Vertical offset (px) | 0–200 distance from the bottom edge (or used as fallback for middle anchor), default 20. |
| Show button label on mobile | Default **No**: below 480px the collapsed launcher is a 48px round icon button. Yes keeps the text label on phones. |
| Default theme | auto (system preference), light, dark. Visitors can still toggle at runtime. |
| Show copy conversation button | Adds the transcript-to-clipboard button to the panel header. |
| Welcome message | Optional first assistant message shown when the chat opens (empty = no greeting). |
| Show connection status | Adds a small dot to the panel header that checks the chat endpoint every interval. |
| Status check interval (seconds) | 10–600, default 30. |

### Advanced tab

Standard Joomla module options — alternate layout, module CSS suffix, caching.

## How the chat works

The module renders only the chat widget. When the chat is enabled, each message from a visitor is POSTed over `com_ajax`:

```
index.php?option=com_ajax&module=bearsamppai&method=ask&format=json
```

with `module_id` and `message`. The server-side helper (`src/Helper/BearsamppaiHelper.php`) then:

1. Loads the content you configured: published articles from the selected categories, FAQ articles from the FAQ category (title read as the question, text as the answer) and recent published Kunena topics when Kunena is installed and forum content is enabled, stripping them to plain text for use as context.
2. Assembles a compact context string, walking each source in order until the configured character budget is reached.
3. Sends a `system` prompt plus the visitor question to the configured Gemini endpoint over the OpenAI-compatible API using a Bearer token.
4. Returns the answer (or the fallback message when no knowledge is available) as JSON consumed by the widget.

The system prompt instructs the model to answer **only** from the supplied knowledge base context and not to rely on prior knowledge, web browsing or guesswork.

### Choosing the knowledge base content

The **Knowledge Base Content** tab controls what the AI may answer from. It is applied on every question, so the context always reflects your published content.

| Setting | Default | Effect |
| --- | --- | --- |
| `articles_category_id` | *(empty)* | The categories whose published articles become context. Multi-select — an article is used when it belongs to **any** of the chosen categories. Leave it empty to use every published article site-wide. |
| `faq_category_id` | *(empty)* | The category holding your FAQ articles. Included only when set. |
| `show_forum` | `0` (No) | Published Kunena topics are added to the context **only when this is switched on**. It defaults to No so that installing Kunena does not silently widen what the AI can answer. |

There is no separate item count per source. Everything published in a selected scope is a candidate, and `chat_context_limit` is the only cap — so the newest articles fill the budget first and the remainder is simply left out. Articles are added first, then FAQ, then forum. Content that does not fit is skipped whole rather than cut off mid-sentence, and a source is always given its turn even if an earlier one was too large, so a short FAQ entry can still reach the context. If FAQ answers are being squeezed out, narrow the article categories or raise `chat_context_limit`.

**FAQ articles are read as question and answer pairs:** the article title is the question and the article text is the answer. The model is told to match them on meaning rather than wording, so a visitor can ask a question in their own words and still reach the right FAQ entry.

Only published content is read, at the access level Joomla's content configuration allows. Forum content is opt-in: with `show_forum` set to No the Kunena tables are never queried, so the module has no forum dependency at all. If you switch it on and Kunena is not installed, that source is skipped and the rest of the context is unaffected.

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

**On small screens** (below `30rem`, i.e. 480px) the collapsed launcher becomes a 3rem round icon button, so a long chat button label cannot turn it into a wide bar across the screen. The label text is visually clipped rather than removed, so the button keeps its accessible name and the heading still appears in the panel header. Set *Show button label on mobile* to Yes to keep the pill on phones, or force either behaviour from CSS:
```css
/* keep the text label on phones, whatever the module setting */
@media (max-width: 30rem) {
	.mod-bearsamppai__chat[data-mobile-label="0"] .mod-bearsamppai__chat-toggle {
		width: auto;
		height: auto;
		padding: 0.65rem 1.1rem;
		gap: 0.5rem;
		border-radius: 999px;
	}
}
```

## Accessibility

- Toggle button exposes `aria-expanded`/`aria-controls`; header `aria-label`s cover copy, theme and close actions.
- Messages container uses `role="log"` and `aria-live="polite"`; a separate visually hidden `role="status"` region announces copy and theme feedback.
- `aria-busy` is set on the message feed while a request is in flight.
- `Escape` closes the panel and returns focus to the toggle; `Ctrl+Shift+/` (or `Ctrl+/`) opens the first chat widget.
- Keyboard-visible `:focus-visible` outlines everywhere, reduced-motion and forced-colors (Windows High Contrast) friendly.

## Development

The repository is the extension source; the install ZIP is produced by a GitHub Actions workflow using [Joomla Packager](https://github.com/N6REJ/joomla-packager) on every merged PR to `main` and via manual workflow dispatch. The action is pinned to a commit SHA (`316d835`, released as `2026.9.28`) rather than a branch, so packaging cannot change under you without a deliberate bump.

### Releases

Versions are **date-based** (`2026.09.28.1`, `2026.09.29`, …). The packager generates the version itself and ignores the manifest `<version>`, so each run produces a new tag, a new `mod_bearsamppai_<version>.zip` release asset, and a version bump committed back to `main` (`commit-changes: 'true'`). The manifest carries a date-based version so a freshly installed copy and the feed agree.

A second release on the same day appends a sub-version rather than colliding, so the tags ascend `2026.09.28` → `2026.09.28.1` → `2026.09.28.2`. Joomla orders these with `version_compare`, so `2026.09.28.2` correctly supersedes `2026.09.28.1` — but only while the base tag is present. **Deleting the base `2026.09.28` tag while suffixed tags remain makes the packager mint a fresh `2026.09.28` that sorts *below* the existing `.1`, and the feed would then advertise a version installed sites refuse.** If you prune tags, remove the whole `2026.09.28.*` family or none of it.

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
│   ├── default.php                  # Wrapper layout, delegates to chat.php
│   └── chat.php                     # Chat widget markup + asset registration
├── media/
│   ├── css/chat.css                 # Chat widget styles + themes
│   └── js/chat.js                   # Chat widget behaviour
├── language/en-GB/                 # en-GB translations
└── .github/workflows/package-packager.yml  # Release packaging
```

## License

GNU General Public License version 3. See [LICENSE](LICENSE).

Bearsampp AI was created by [Bearsampp](https://bearsampp.com). For support: support@bearsampp.com