<?php

namespace Yajra\DataTables\Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Tests\Models\User;
use Yajra\DataTables\Tests\TestCase;

class MinSearchLengthDataTableTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_returns_all_records_when_search_is_empty()
    {
        $crawler = $this->call('GET', '/eloquent/min-length', [
            'start' => 0,
            'length' => 10,
            'columns' => [
                ['data' => 'id'],
                ['data' => 'name'],
                ['data' => 'email'],
            ],
            'search' => [
                'value' => '',
                'regex' => false,
            ],
        ]);

        $crawler->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 20,
        ]);
    }

    #[Test]
    public function it_returns_an_error_when_search_keyword_length_is_less_than_required()
    {
        $crawler = $this->call('GET', '/eloquent/min-length', [
            'start' => 0,
            'length' => 10,
            'columns' => [
                ['data' => 'id'],
                ['data' => 'name'],
                ['data' => 'email'],
            ],
            'search' => [
                'value' => 'abc',
                'regex' => false,
            ],
        ]);

        $crawler->assertJson([
            'draw' => 0,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => "Exception Message:\n\nPlease enter at least 5 characters to search.",
        ]);
    }

    #[Test]
    public function it_returns_filtered_records_when_search_keyword_length_is_met()
    {
        $crawler = $this->call('GET', '/eloquent/min-length', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'columns' => [
                ['data' => 'id'],
                ['data' => 'name'],
                ['data' => 'email'],
            ],
            'search' => [
                'value' => 'Record-17',
                'regex' => false,
            ],
        ]);

        $crawler->assertJson([
            'draw' => 1,
            'recordsTotal' => 20,
            'recordsFiltered' => 1,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/eloquent/min-length', fn() => (new EloquentDataTable(User::query()))
            ->minSearchLength(5)
            ->toJson());
    }
}
