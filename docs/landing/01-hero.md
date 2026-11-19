---
view: components.packages.package-hero
badges:
  - label: Laravel Package
    color: blue
  - label: "{package.latestVersionLabel}"
    color: green
  - label: "{package.license} License"
    color: purple
  - label: "{package.downloadsShort}+ downloads"
    color: blue
  - label: AI Ready · Laravel Boost
    color: purple
eyebrow: "{package.name}"
title: One API,
highlight: three ways to store translations
buttons:
  - label: View Full Documentation
    href: "{card.docsUrl}"
    style: primary
    icon: arrow-right
  - label: View on GitHub
    href: "{package.githubUrl}"
    style: ghost
    external: true
install: "{card.install}"
labels:
  copy: Copy
  copied: Copied!
---

Laravel Translatable lets each Eloquent model choose where its translations live: a **translations table**, a **JSON column per field**, or a **column per field and locale**. You write `$post->title`, `setTranslation()` and `whereTranslation()` the same way for all three, and move a model from one storage to another later without losing a translation. It is AI ready: `php artisan boost:install` teaches your coding agent the whole package through Laravel Boost.
