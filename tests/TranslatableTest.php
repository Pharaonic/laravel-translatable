<?php

namespace Pharaonic\Laravel\Translatable\Tests;

use Pharaonic\Laravel\Translatable\Tests\Models\Post;
use Pharaonic\Laravel\Translatable\Tests\Models\PostTranslation;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TranslatableTest extends TestCase
{
    public $post;

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('app.locale', 'en');
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }
    
    public function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__ . '/migrations');

        // POST MODEL
        $this->post = Post::find(1);

        if (!$this->post) {
            $this->post = Post::create(['published' => true]);
            PostTranslation::create([
                'locale' => 'en',
                'post_id' => 1,
                'title' => 'post_test_title',
                'content' => 'post_test_content',
                'description' => 'post_test_description',
                'keywords' => 'post_test_keyword',
            ]);


            Post::create(['published' => true]);
            PostTranslation::create([
                'locale' => 'en',
                'post_id' => 2,
                'title' => 'post_2_test_title',
                'content' => 'post_2_test_content',
                'description' => 'post_2_test_description',
                'keywords' => 'post_2_test_keyword',
            ]);
        }
    }

    public function testTranslate()
    {
        $this->post->translate('en')->title = 'post_test_title';
        $actual = $this->post->translate('en')->title;

        $this->assertSame('post_test_title', $actual);
    }

    public function testTranslateOnModifyTitle()
    {
        $this->post->translate('en')->title = 'modified_title';
        $this->post->save();

        $this->assertSame('modified_title', $this->post->translate('en')->title);
    }

    public function testTranslateOrNew()
    {
        $this->post->translateOrNew('zh_TW')->title = 'modified_title';
        $this->post->save();

        $this->assertSame('modified_title', $this->post->translate('zh_TW')->title);
        $this->assertSame('modified_title', $this->post->translate('en')->title);
    }

    public function testTranslateOrFail()
    {
        $this->expectException(NotFoundHttpException::class);
        $this->post->translateOrFail('fr')->title;
    }

    public function testTranslateOrFailOnSettingTitle()
    {
        $this->post->translateOrFail('en')->title = 'modified_title_2';
        $this->post->save();

        $this->assertSame('modified_title_2', $this->post->translateOrFail('en')->title);
    }

    public function testTranslateOrDefaultOnDefaultTitle()
    {
        $this->assertSame('modified_title_2', $this->post->translateOrDefault('de')->title);
    }

    public function testTranslateOrDefaultOnModifyingTitle()
    {
        $this->post->translateOrDefault('en')->title = 'modified_title';
        $this->post->save();

        $this->assertSame('modified_title', $this->post->translateOrDefault('en')->title);
    }

    public function testHasTranslation()
    {
        $this->assertTrue($this->post->hasTranslation('en'));
        $this->assertFalse($this->post->hasTranslation('fr'));
    }

    public function testPostAttributes()
    {
        $this->assertSame('en', $this->post->locales[0]);
    }

    public function testTranslated()
    {
        $expected = 'modified_title';
        $actual = Post::translated('en')->get()->toArray()[0]['translations'][0]['title'];

        $this->assertSame($expected, $actual);
    }

    public function testTranslatedOnExistedLocaleFromPostScope()
    {
        $expected = 'en';
        $actual = Post::translated('en')->get()->toArray()[0]['translations'][0]['locale'];

        $this->assertSame($expected, $actual);
    }

    public function testTranslatedOnNonExistedLocaleFromPostScope()
    {
        $actual = Post::translated('fr')->get()->toArray();

        $this->assertCount(0, $actual);
    }

    public function testNotTranslated()
    {
        $this->assertCount(2, Post::notTranslated('fr')->get()->toArray());
    }

    public function testTranslatedSortingOnAsc()
    {
        $expectedPostTranslationId = 3;
        $expectedPostId = 1;
        $actual = Post::translatedSorting('zh_TW', 'title', 'asc')->get()->toArray();

        $this->assertSame($expectedPostId, $actual[0]['id']);
        $this->assertSame($expectedPostTranslationId, $actual[0]['translations'][1]['id']);
    }

    public function testTranslatedSortingOnDesc()
    {
        $expectedPostTranslationId = 2;
        $expectedPostId = 2;
        $actual = Post::translatedSorting('en', 'title', 'desc')->get()->toArray();

        $this->assertSame($expectedPostId, $actual[0]['id']);
        $this->assertSame($expectedPostTranslationId, $actual[0]['translations'][0]['id']);
    }

    public function testTranslatedWhereTranslationOnSpecificContent()
    {
        $actual = Post::translated('en')->whereTranslation('content', 'post_2_test_content')->get()->toArray();

        $this->assertCount(1, $actual);
        $this->assertSame('post_2_test_title', $actual[0]['translations'][0]['title']);
        $this->assertSame('post_2_test_content', $actual[0]['translations'][0]['content']);
    }

    public function testTranslatedOrWhereTranslation()
    {
        $actual = Post::translated('en')->whereTranslation('content', 'something')->orWhereTranslation('content', 'post_test_content')->get()->toArray();
        
        $this->assertCount(1, $actual);
        $this->assertSame('modified_title', $actual[0]['translations'][0]['title']);
        $this->assertSame('post_test_content', $actual[0]['translations'][0]['content']);
    }

    public function testTranslatedWhereTranslationLike()
    {
        $actual = Post::translated('en')->whereTranslationLike('content', '%post_2%')->get()->toArray();

        $this->assertCount(1, $actual);
        $this->assertSame('post_2_test_title', $actual[0]['translations'][0]['title']);
        $this->assertSame('post_2_test_content', $actual[0]['translations'][0]['content']);
    }

    public function testTranslatedOrWhereTranslationLike()
    {
        $actual = Post::translated('en')->whereTranslationLike('content', '%something%')->orWhereTranslationLike('content', '%_test_%')->get()->toArray();

        $this->assertCount(2, $actual);
        $this->assertSame('modified_title', $actual[0]['translations'][0]['title']);
        $this->assertSame('post_test_content', $actual[0]['translations'][0]['content']);
    }
}
