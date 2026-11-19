---
view: components.packages.quick-look
title: A quick look
subtitle: Choose a strategy with one marker interface; the code that uses the model never changes.
file: app/Post.php
language: php
code: |
  class Post extends Model { use Translatable; }                                    // translations table
  class Tag extends Model implements JsonTranslatable { use Translatable; }         // a JSON column per field
  class Country extends Model implements ColumnsTranslatable { use Translatable; }  // name_en, name_ar columns

  // The same code under all three:
  $post->setTranslation('title', 'ar', 'مرحبا')->save();
  $tag->name;                                         // the current locale, with fallback
  Country::whereTranslation('name', 'مصر', 'ar')->first();
---
