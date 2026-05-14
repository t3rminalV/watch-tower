# Open Graph

Social-share metadata for **watch-tower** — a self-hosted observability dashboard for Laravel apps (issues, exceptions, performance).

## Card preview

```
┌────────────────────────────────────────────────────────────┐
│  ▲ watch-tower                                              │
│                                                             │
│  Self-hosted error tracking & performance                   │
│  monitoring for Laravel.                                    │
│                                                             │
│  Issues · Exceptions · Releases · Activity                  │
│                                                             │
│                                              [PHP] [Laravel]│
└────────────────────────────────────────────────────────────┘
        1200 × 630   ·   light + dark variants
```

## Canonical values

| Property        | Value                                                                       |
| --------------- | --------------------------------------------------------------------------- |
| `title`         | watch-tower — Self-hosted observability for Laravel                         |
| `description`   | Track exceptions, triage issues, and watch your Laravel apps in real time.  |
| `url`           | https://watch-tower.test                                                    |
| `image`         | https://watch-tower.test/og/cover.png                                       |
| `image:width`   | 1200                                                                        |
| `image:height`  | 630                                                                         |
| `site_name`     | watch-tower                                                                 |
| `type`          | website                                                                     |
| `locale`        | en_US                                                                       |
| `twitter:card`  | summary_large_image                                                         |
| `twitter:site`  | @watchtower                                                                 |

Keep `description` under 200 characters; Twitter truncates around 200, Facebook around 300.

## HTML meta tags

Drop into the document `<head>` (or a Blade/Inertia layout partial):

```html
<meta property="og:type" content="website">
<meta property="og:site_name" content="watch-tower">
<meta property="og:title" content="watch-tower — Self-hosted observability for Laravel">
<meta property="og:description" content="Track exceptions, triage issues, and watch your Laravel apps in real time.">
<meta property="og:url" content="https://watch-tower.test">
<meta property="og:image" content="https://watch-tower.test/og/cover.png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="watch-tower dashboard showing issues and exceptions for a Laravel application">
<meta property="og:locale" content="en_US">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:site" content="@watchtower">
<meta name="twitter:title" content="watch-tower — Self-hosted observability for Laravel">
<meta name="twitter:description" content="Track exceptions, triage issues, and watch your Laravel apps in real time.">
<meta name="twitter:image" content="https://watch-tower.test/og/cover.png">
```

## Per-page overrides

Issues and exception detail pages should override the title and description so shared links carry context:

| Page                  | `og:title`                                          | `og:description`                                            |
| --------------------- | --------------------------------------------------- | ----------------------------------------------------------- |
| Dashboard             | `{project} · watch-tower`                           | Recent activity and open issues for {project}.              |
| Issues index          | `Issues · {project}`                                | {open} open · {unassigned} unassigned · {mine} assigned to you. |
| Issue detail          | `#{number} {short_class} · {project}`               | {message} — first seen {first_seen}, {total_count} events.  |
| Exceptions index      | `Exceptions · {project}`                            | {total} events across {groups} exception groups.            |
| Exception detail      | `{short_class} · {project}`                         | {message} — {users_count} users impacted.                   |

## Image guidelines

- **Size**: 1200 × 630 px (Facebook/LinkedIn/X all crop cleanly).
- **Safe area**: keep text 80 px from each edge — Slack and iMessage crop slightly.
- **Contrast**: ship a dark-mode variant served via `prefers-color-scheme`; fall back to dark for messaging apps.
- **Filename**: `public/og/cover.png` (and `cover-dark.png`).
- **File size**: under 1 MB — Facebook downsamples larger images.

## Brand tokens

| Token              | Value                                  |
| ------------------ | -------------------------------------- |
| Accent (primary)   | emerald-500 (`#10b981`)                |
| Background (dark)  | zinc-950 (`#09090b`)                   |
| Background (light) | zinc-50 (`#fafafa`)                    |
| Text (heading)     | zinc-50 / zinc-900                     |
| Text (muted)       | zinc-400 / zinc-500                    |
| Font (display)     | Inter, sans-serif                      |
| Font (mono)        | JetBrains Mono, ui-monospace           |

## Verification

After deploying, validate the card with:

- Facebook: <https://developers.facebook.com/tools/debug/>
- X (Twitter): <https://cards-dev.twitter.com/validator>
- LinkedIn: <https://www.linkedin.com/post-inspector/>
- Slack: paste the URL into any Slack DM — re-share strips the cache.

If a refresh isn't reflecting changes, append `?v=2` to the URL to bust each platform's cache.
