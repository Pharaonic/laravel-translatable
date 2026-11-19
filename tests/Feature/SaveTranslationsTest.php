<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Translatable\Tests\Feature;

use Pharaonic\Laravel\Translatable\Tests\Fixtures\Post;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\PostTranslation;
use Pharaonic\Laravel\Translatable\Tests\TestCase;

final class SaveTranslationsTest extends TestCase
{
    private function createPostWithTwoLocales(): Post
    {
        $post = Post::create(['published' => true]);

        PostTranslation::create(['locale' => 'en', 'post_id' => $post->getKey(), 'title' => 'Hello']);
        PostTranslation::create(['locale' => 'ar', 'post_id' => $post->getKey(), 'title' => 'مرحبا']);

        return $post->fresh();
    }

    public function test_it_keeps_every_stored_translation_on_a_save_without_a_translation_payload(): void
    {
        $post = $this->createPostWithTwoLocales();
        $post->translate('en');

        $post->published = false;
        $post->save();

        $this->assertSame(['ar', 'en'], PostTranslation::query()->orderBy('locale')->pluck('locale')->all());
    }

    public function test_it_keeps_the_other_locales_when_the_payload_holds_one_locale(): void
    {
        $post = $this->createPostWithTwoLocales();
        $post->load('translations');

        $post->update(['locale' => ['en' => ['title' => 'Hi']]]);

        $this->assertSame(
            ['ar' => 'مرحبا', 'en' => 'Hi'],
            PostTranslation::query()->orderBy('locale')->pluck('title', 'locale')->all()
        );
    }

    public function test_it_saves_a_modified_translation_to_the_database(): void
    {
        $post = $this->createPostWithTwoLocales();

        $post->translate('ar')->title = 'أهلا';
        $post->save();

        $this->assertSame('أهلا', PostTranslation::query()->where('locale', 'ar')->value('title'));
        $this->assertSame(2, PostTranslation::query()->count());
    }
}
