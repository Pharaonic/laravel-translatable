<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Translatable\Tests\Feature;

use Pharaonic\Laravel\Translatable\Tests\Fixtures\Post;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\PostTranslation;
use Pharaonic\Laravel\Translatable\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TranslatableTest extends TestCase
{
    private function createPostWithTranslation(string $title = 'post_test_title', string $content = 'post_test_content'): Post
    {
        $post = Post::create(['published' => true]);

        PostTranslation::create([
            'locale' => 'en',
            'post_id' => $post->getKey(),
            'title' => $title,
            'content' => $content,
            'description' => 'post_test_description',
            'keywords' => 'post_test_keyword',
        ]);

        return $post;
    }

    public function test_it_reads_a_translation_by_locale(): void
    {
        $post = $this->createPostWithTranslation();

        $this->assertSame('post_test_title', $post->translate('en')->title);
    }

    public function test_it_saves_a_modified_translation(): void
    {
        $post = $this->createPostWithTranslation();

        $post->translate('en')->title = 'modified_title';
        $post->save();

        $this->assertSame('modified_title', $post->translate('en')->title);
    }

    public function test_it_creates_a_new_translation_with_translate_or_new(): void
    {
        $post = $this->createPostWithTranslation();

        $post->translateOrNew('zh_TW')->title = 'modified_title';
        $post->save();

        $this->assertSame('modified_title', $post->translate('zh_TW')->title);
        $this->assertSame('post_test_title', $post->translate('en')->title);
    }

    public function test_it_aborts_with_404_from_translate_or_fail_when_the_locale_is_missing(): void
    {
        $post = $this->createPostWithTranslation();

        $this->expectException(NotFoundHttpException::class);

        $post->translateOrFail('zh_TW');
    }

    public function test_it_saves_a_translation_reached_through_translate_or_fail(): void
    {
        $post = $this->createPostWithTranslation();

        $post->translateOrFail('en')->title = 'modified_title';
        $post->save();

        $this->assertSame('modified_title', $post->translateOrFail('en')->title);
    }

    public function test_it_reads_a_translation_through_translate_or_default(): void
    {
        $post = $this->createPostWithTranslation();

        $this->assertSame('post_test_title', $post->translateOrDefault('en')->title);
    }

    public function test_it_saves_a_translation_reached_through_translate_or_default(): void
    {
        $post = $this->createPostWithTranslation();

        $post->translateOrDefault('en')->title = 'modified_title';
        $post->save();

        $this->assertSame('modified_title', $post->translateOrDefault('en')->title);
    }

    public function test_it_checks_whether_a_locale_is_translated(): void
    {
        $post = $this->createPostWithTranslation();

        $this->assertTrue($post->hasTranslation('en'));
        $this->assertFalse($post->hasTranslation('zh_TW'));
    }

    public function test_it_lists_the_translated_locales(): void
    {
        $post = $this->createPostWithTranslation();

        $this->assertSame(['en'], $post->locales);
    }

    public function test_it_filters_translated_records_and_eager_loads_their_translations(): void
    {
        $this->createPostWithTranslation();

        $actual = Post::translated('en')->get()->toArray();

        $this->assertSame('post_test_title', $actual[0]['translations'][0]['title']);
        $this->assertSame('en', $actual[0]['translations'][0]['locale']);
    }

    public function test_it_returns_nothing_for_a_locale_without_translations(): void
    {
        $this->assertCount(0, Post::translated('fr')->get());
    }

    public function test_it_returns_nothing_from_not_translated_when_there_are_no_posts(): void
    {
        $this->assertCount(0, Post::notTranslated('fr')->get());
    }

    public function test_it_sorts_by_a_translated_field_ascending(): void
    {
        $this->createPostWithTranslation('post_test_title2');
        $this->createPostWithTranslation('post_test_title1');

        $titles = Post::translatedSorting('en', 'title', 'asc')->get()
            ->map(function (Post $post) {
                return $post->translate('en')->title;
            })->all();

        $this->assertSame(['post_test_title1', 'post_test_title2'], $titles);
    }

    public function test_it_sorts_by_a_translated_field_descending(): void
    {
        $this->createPostWithTranslation('post_test_title2');
        $this->createPostWithTranslation('post_test_title1');

        $titles = Post::translatedSorting('en', 'title', 'desc')->get()
            ->map(function (Post $post) {
                return $post->translate('en')->title;
            })->all();

        $this->assertSame(['post_test_title2', 'post_test_title1'], $titles);
    }

    public function test_it_filters_by_a_translated_value(): void
    {
        $this->createPostWithTranslation('post_test_title2', 'post_test_content2');
        $this->createPostWithTranslation('post_test_title1', 'post_test_content1');

        $actual = Post::translated('en')->whereTranslation('content', 'post_test_content1')->get()->toArray();

        $this->assertCount(1, $actual);
        $this->assertSame('post_test_title1', $actual[0]['translations'][0]['title']);
        $this->assertSame('post_test_content1', $actual[0]['translations'][0]['content']);
    }

    public function test_it_adds_an_or_condition_on_a_translated_value(): void
    {
        $this->createPostWithTranslation('post_test_title1', 'post_test_content1');
        $this->createPostWithTranslation('post_test_title2', 'post_test_content2');

        $actual = Post::translated('en')->orWhereTranslation('content', 'post_test_content1')->get()->toArray();

        $this->assertCount(2, $actual);
        $this->assertSame('post_test_title1', $actual[0]['translations'][0]['title']);
        $this->assertSame('post_test_content1', $actual[0]['translations'][0]['content']);
    }

    public function test_it_filters_by_a_translated_value_with_like(): void
    {
        $this->createPostWithTranslation('post_test_title2', 'post_test_content2');
        $this->createPostWithTranslation('post_test_title1', 'post_test_content1');

        $actual = Post::translated('en')->whereTranslationLike('content', '%content1%')->get()->toArray();

        $this->assertCount(1, $actual);
        $this->assertSame('post_test_title1', $actual[0]['translations'][0]['title']);
        $this->assertSame('post_test_content1', $actual[0]['translations'][0]['content']);
    }

    public function test_it_adds_an_or_condition_on_a_translated_value_with_like(): void
    {
        $this->createPostWithTranslation('post_test_title1', 'post_test_content1');
        $this->createPostWithTranslation('post_test_title2', 'post_test_content2');

        $actual = Post::translated('en')->orWhereTranslationLike('content', '%content1%')->get()->toArray();

        $this->assertCount(2, $actual);
        $this->assertSame('post_test_title1', $actual[0]['translations'][0]['title']);
        $this->assertSame('post_test_content1', $actual[0]['translations'][0]['content']);
    }

    public function test_it_creates_translations_from_the_locale_payload(): void
    {
        $post = Post::create(['locale' => ['en' => ['title' => 'Hello'], 'ar' => ['title' => 'مرحبا']]]);

        $this->assertSame('مرحبا', $post->translate('ar')->title);
        $this->assertSame('Hello', $post->fresh()->translate('en')->title);
        $this->assertSame(2, PostTranslation::query()->count());
    }
}
