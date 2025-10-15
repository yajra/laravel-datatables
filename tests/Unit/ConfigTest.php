<?php

namespace Yajra\DataTables\Tests\Unit;

use Yajra\DataTables\Tests\TestCase;
use Yajra\DataTables\Utilities\Config;

class ConfigTest extends TestCase
{
    /** @var Config */
    private $config;

    public function setUp(): void
    {
        parent::setUp();
        $this->config = app('datatables.config');
    }

    public function test_is_ignore_accents_default()
    {
        config(['datatables.search.ignore_accents' => false]);
        $this->assertFalse($this->config->isIgnoreAccents());
    }

    public function test_is_ignore_accents_enabled()
    {
        config(['datatables.search.ignore_accents' => true]);
        $this->assertTrue($this->config->isIgnoreAccents());
    }

    public function test_is_ignore_accents_with_null_config()
    {
        config(['datatables.search.ignore_accents' => null]);
        $this->assertFalse($this->config->isIgnoreAccents());
    }

    public function test_is_ignore_accents_with_string_true()
    {
        config(['datatables.search.ignore_accents' => 'true']);
        $this->assertTrue($this->config->isIgnoreAccents());
    }

    public function test_is_ignore_accents_with_string_false()
    {
        config(['datatables.search.ignore_accents' => 'false']);
        $this->assertTrue($this->config->isIgnoreAccents()); // non-empty string is truthy
    }

    public function test_is_ignore_accents_with_zero()
    {
        config(['datatables.search.ignore_accents' => 0]);
        $this->assertFalse($this->config->isIgnoreAccents());
    }

    public function test_is_ignore_accents_with_one()
    {
        config(['datatables.search.ignore_accents' => 1]);
        $this->assertTrue($this->config->isIgnoreAccents());
    }
}