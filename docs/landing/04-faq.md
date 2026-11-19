---
view: components.home.faq
badge: FAQ
title: "{package.name}"
highlight: Questions
subtitle: "Quick answers about installing and using {package.name}."
---

## What is {package.name}?

{card.description} It's a free, open-source {technology.name} package by Pharaonic.

## How do I install {package.name}?

Run `composer require {package.composer}` in your project's root directory.

## What does {package.name} require?

The latest release requires {package.requiresText}.

## Which storage strategies does {package.name} support?

Three: a translations table (one row per model and locale, the default), a JSON column per field, and a column per field and locale (`title_en`, `title_ar`). Each model chooses one with a marker interface, or follows `config('translatable.strategy')`.

## Which storage strategy should I use?

The translations table suits many locales and long text. JSON keeps everything on the model's row with any number of locales. Columns suit a small fixed set of locales that you filter or sort with plain indexes. Every strategy has the same API, so you can change your mind later.

## Can I move a model to another strategy later?

Yes. `translatable:make-schema` writes the new storage's migration, `translatable:migrate --apply` copies the translations, and `translatable:verify` compares them. The source is never changed, and `translatable:rollback` undoes a copy.

## I use version 2.x. Do I need to migrate my database?

No. The table strategy keeps the 2.x storage. Rename `$translatableAttributes` to `$translatables`, check the upgrade guide for behavior changes, and run your tests.

## Does it work with Laravel Octane?

Yes. Pending changes live on each model, and the locale and configuration are read on every call. Reload the workers after adding a locale column.

## Is {package.name} free to use?

Yes. {package.name} is open source under the {package.license} license, so you can use it in personal and commercial projects.

## Where can I find the {package.name} documentation?

Read the [{package.name} documentation]({package.docsUrl}) for setup, configuration, and usage examples.

## How do I report a bug or contribute to {package.name}?

Open an issue or a pull request on [GitHub]({package.githubUrl}), ask in [GitHub Discussions](https://github.com/orgs/Pharaonic/discussions), or chat with us on [Discord](https://discord.gg/XQG9RhvEvf).
