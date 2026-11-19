<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Translatable\Tests\Feature;

use Pharaonic\Laravel\Translatable\Tests\Fixtures\Post;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\PostTranslation;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

function translatableCreatePostWithTranslation(string $title = 'post_test_title', string $content = 'post_test_content'): Post
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

it('reads a translation by locale', function () {
    $post = translatableCreatePostWithTranslation();

    $this->assertSame('post_test_title', $post->translate('en')->title);
});

it('saves a modified translation', function () {
    $post = translatableCreatePostWithTranslation();

    $post->translate('en')->title = 'modified_title';
    $post->save();

    $this->assertSame('modified_title', $post->translate('en')->title);
});

it('creates a new translation with translate or new', function () {
    $post = translatableCreatePostWithTranslation();

    $post->translateOrNew('zh_TW')->title = 'modified_title';
    $post->save();

    $this->assertSame('modified_title', $post->translate('zh_TW')->title);
    $this->assertSame('post_test_title', $post->translate('en')->title);
});

it('aborts with 404 from translate or fail when the locale is missing', function () {
    $post = translatableCreatePostWithTranslation();

    $this->expectException(NotFoundHttpException::class);

    $post->translateOrFail('zh_TW');
});

it('saves a translation reached through translate or fail', function () {
    $post = translatableCreatePostWithTranslation();

    $post->translateOrFail('en')->title = 'modified_title';
    $post->save();

    $this->assertSame('modified_title', $post->translateOrFail('en')->title);
});

it('reads a translation through translate or default', function () {
    $post = translatableCreatePostWithTranslation();

    $this->assertSame('post_test_title', $post->translateOrDefault('en')->title);
});

it('saves a translation reached through translate or default', function () {
    $post = translatableCreatePostWithTranslation();

    $post->translateOrDefault('en')->title = 'modified_title';
    $post->save();

    $this->assertSame('modified_title', $post->translateOrDefault('en')->title);
});

it('checks whether a locale is translated', function () {
    $post = translatableCreatePostWithTranslation();

    $this->assertTrue($post->hasTranslation('en'));
    $this->assertFalse($post->hasTranslation('zh_TW'));
});

it('lists the translated locales', function () {
    $post = translatableCreatePostWithTranslation();

    $this->assertSame(['en'], $post->locales);
});

it('filters translated records and eager loads their translations', function () {
    translatableCreatePostWithTranslation();

    $actual = Post::translated('en')->get()->toArray();

    $this->assertSame('post_test_title', $actual[0]['translations'][0]['title']);
    $this->assertSame('en', $actual[0]['translations'][0]['locale']);
});

it('returns nothing for a locale without translations', function () {
    $this->assertCount(0, Post::translated('fr')->get());
});

it('returns nothing from not translated when there are no posts', function () {
    $this->assertCount(0, Post::notTranslated('fr')->get());
});

it('sorts by a translated field ascending', function () {
    translatableCreatePostWithTranslation('post_test_title2');
    translatableCreatePostWithTranslation('post_test_title1');

    $titles = Post::translatedSorting('en', 'title', 'asc')->get()
        ->map(function (Post $post) {
            return $post->translate('en')->title;
        })->all();

    $this->assertSame(['post_test_title1', 'post_test_title2'], $titles);
});

it('sorts by a translated field descending', function () {
    translatableCreatePostWithTranslation('post_test_title2');
    translatableCreatePostWithTranslation('post_test_title1');

    $titles = Post::translatedSorting('en', 'title', 'desc')->get()
        ->map(function (Post $post) {
            return $post->translate('en')->title;
        })->all();

    $this->assertSame(['post_test_title2', 'post_test_title1'], $titles);
});

it('filters by a translated value', function () {
    translatableCreatePostWithTranslation('post_test_title2', 'post_test_content2');
    translatableCreatePostWithTranslation('post_test_title1', 'post_test_content1');

    $actual = Post::translated('en')->whereTranslation('content', 'post_test_content1')->get()->toArray();

    $this->assertCount(1, $actual);
    $this->assertSame('post_test_title1', $actual[0]['translations'][0]['title']);
    $this->assertSame('post_test_content1', $actual[0]['translations'][0]['content']);
});

it('adds an or condition on a translated value', function () {
    translatableCreatePostWithTranslation('post_test_title1', 'post_test_content1');
    translatableCreatePostWithTranslation('post_test_title2', 'post_test_content2');

    $actual = Post::translated('en')->orWhereTranslation('content', 'post_test_content1')->get()->toArray();

    $this->assertCount(2, $actual);
    $this->assertSame('post_test_title1', $actual[0]['translations'][0]['title']);
    $this->assertSame('post_test_content1', $actual[0]['translations'][0]['content']);
});

it('filters by a translated value with like', function () {
    translatableCreatePostWithTranslation('post_test_title2', 'post_test_content2');
    translatableCreatePostWithTranslation('post_test_title1', 'post_test_content1');

    $actual = Post::translated('en')->whereTranslationLike('content', '%content1%')->get()->toArray();

    $this->assertCount(1, $actual);
    $this->assertSame('post_test_title1', $actual[0]['translations'][0]['title']);
    $this->assertSame('post_test_content1', $actual[0]['translations'][0]['content']);
});

it('adds an or condition on a translated value with like', function () {
    translatableCreatePostWithTranslation('post_test_title1', 'post_test_content1');
    translatableCreatePostWithTranslation('post_test_title2', 'post_test_content2');

    $actual = Post::translated('en')->orWhereTranslationLike('content', '%content1%')->get()->toArray();

    $this->assertCount(2, $actual);
    $this->assertSame('post_test_title1', $actual[0]['translations'][0]['title']);
    $this->assertSame('post_test_content1', $actual[0]['translations'][0]['content']);
});

it('creates translations from the locale payload', function () {
    $post = Post::create(['locale' => ['en' => ['title' => 'Hello'], 'ar' => ['title' => 'مرحبا']]]);

    $this->assertSame('مرحبا', $post->translate('ar')->title);
    $this->assertSame('Hello', $post->fresh()->translate('en')->title);
    $this->assertSame(2, PostTranslation::query()->count());
});
