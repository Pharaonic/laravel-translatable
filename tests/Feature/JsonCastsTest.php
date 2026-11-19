<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Translatable\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\CastJsonPage;
use Pharaonic\Laravel\Translatable\Tests\TestCase;

final class JsonCastsTest extends TestCase
{
    public function test_model_casts_on_translated_json_columns_never_encode_twice(): void
    {
        $page = new CastJsonPage(['slug' => 'c']);
        $page->setTranslation('title', 'en', 'Hello')->setTranslation('title', 'ar', 'مرحبا')->setTranslation('rank', 'en', 3)->save();

        $stored = json_decode((string) DB::table('json_pages')->where('id', $page->getKey())->value('title'), true);
        $this->assertIsArray($stored);
        ksort($stored);
        $this->assertSame(['ar' => 'مرحبا', 'en' => 'Hello'], $stored, 'The column holds one level of JSON.');

        $fresh = CastJsonPage::query()->findOrFail($page->getKey());
        $this->assertSame('Hello', $fresh->getTranslation('title', 'en'));
        $this->assertSame(3, $fresh->getTranslation('rank', 'en'));
        $this->assertSame(['ar' => 'مرحبا', 'en' => 'Hello'], $fresh->getTranslations('title'));

        $fresh->setTranslation('summary', 'en', 'Short')->save();
        $this->assertSame(['en' => 'Short'], json_decode((string) DB::table('json_pages')->where('id', $page->getKey())->value('summary'), true));

        $this->assertSame('Hello', $fresh->fresh()?->toArray()['title'] ?? null, 'Serialization shows the displayed value, not the raw column.');
    }
}
