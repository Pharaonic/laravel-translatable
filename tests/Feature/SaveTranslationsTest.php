<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Translatable\Tests\Feature;

use Pharaonic\Laravel\Translatable\Tests\Fixtures\Post;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\PostTranslation;

function saveTranslationsCreatePostWithTwoLocales(): Post
{
    $post = Post::create(['published' => true]);

    PostTranslation::create(['locale' => 'en', 'post_id' => $post->getKey(), 'title' => 'Hello']);
    PostTranslation::create(['locale' => 'ar', 'post_id' => $post->getKey(), 'title' => 'مرحبا']);

    return $post->fresh();
}

it('keeps every stored translation on a save without a translation payload', function () {
    $post = saveTranslationsCreatePostWithTwoLocales();
    $post->translate('en');

    $post->published = false;
    $post->save();

    $this->assertSame(['ar', 'en'], PostTranslation::query()->orderBy('locale')->pluck('locale')->all());
});

it('keeps the other locales when the payload holds one locale', function () {
    $post = saveTranslationsCreatePostWithTwoLocales();
    $post->load('translations');

    $post->update(['locale' => ['en' => ['title' => 'Hi']]]);

    $this->assertSame(
        ['ar' => 'مرحبا', 'en' => 'Hi'],
        PostTranslation::query()->orderBy('locale')->pluck('title', 'locale')->all()
    );
});

it('saves a modified translation to the database', function () {
    $post = saveTranslationsCreatePostWithTwoLocales();

    $post->translate('ar')->title = 'أهلا';
    $post->save();

    $this->assertSame('أهلا', PostTranslation::query()->where('locale', 'ar')->value('title'));
    $this->assertSame(2, PostTranslation::query()->count());
});
