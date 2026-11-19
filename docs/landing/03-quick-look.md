---
view: components.packages.quick-look
title: A quick look
subtitle: Declare the translated fields, write each locale, and read or query them.
file: app/Http/Controllers/PostController.php
language: php
code: |
  $post = new Post(['published' => true]);
  $post->setTranslation('title', 'en', 'Hello')
      ->setTranslation('title', 'ar', 'مرحبا')
      ->save();

  App::setLocale('ar');
  $post->title;                          // "مرحبا"
  $post->getTranslation('title', 'fr');  // null, nothing stored in French

  Post::whereTranslation('title', 'Hello', 'en')
      ->orderByTranslation('title')
      ->get();
---
