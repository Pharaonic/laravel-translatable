---
name: Laravel Translatable

action:
  label: View on Packagist
  href: "{package.packagistUrl}"

views: components.packages

breadcrumbs:
  - label: Home
    href: route:home
  - label: Packages
    href: route:packages.index
  - label: "{technology.name} Packages"
    href: "url:/packages/{technology.slug}"
  - label: "{package.name}"

card:
  topic: localization
  icon: translate
  tags: translation translatable multilingual i18n localization eloquent json columns locale fallback
  description: Translated Eloquent fields with one API and three storage strategies, a translations table, JSON columns or a column per locale, with fallbacks, typed fields, scopes, transactional saves and commands to move data between strategies.

seo:
  title: "{package.fullName} - Multilingual Eloquent Models for Laravel"
  description: "{package.name} is a Laravel package for translated Eloquent fields stored in a translations table, JSON columns or a column per locale, with one API, fallbacks and scopes. {package.downloadsShort}+ downloads, {package.license} licensed."
  keywords: laravel translatable, laravel translations, multilingual eloquent, translatable models, laravel json translations, laravel i18n, eloquent locale, translation fallback
  author: Pharaonic
  images:
    - "{package.cover}"
  openGraph:
    type: website
    siteName: Pharaonic
  twitter:
    card: summary_large_image

schema:
  "@type": SoftwareSourceCode
  name: "{package.name}"
  description: "{package.name} is a Laravel package for translated Eloquent fields, stored in a translations table, JSON columns or a column per locale, behind one API."
  image: "{package.cover}"
  codeRepository: "{package.githubUrl}"
  programmingLanguage: PHP
  runtimePlatform: "{technology.name}"
  version: "{package.version}"
  datePublished: "{package.publishedAt}"
  dateModified: "{package.updatedAt}"
  license: "https://opensource.org/licenses/{package.license}"
  isAccessibleForFree: true
  sameAs:
    - "{package.githubUrl}"
    - "{package.packagistUrl}"
  author:
    "@id": url:/#organization
  publisher:
    "@id": url:/#organization
  interactionStatistic:
    "@type": InteractionCounter
    interactionType: https://schema.org/DownloadAction
    userInteractionCount: "{package.downloads}"
---
