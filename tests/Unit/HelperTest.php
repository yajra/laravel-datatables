<?php

namespace Yajra\DataTables\Tests\Unit;

use Carbon\Carbon;
use stdClass;
use Yajra\DataTables\Tests\TestCase;
use Yajra\DataTables\Utilities\Helper;

class HelperTest extends TestCase
{
    public function test_include_in_array()
    {
        $data = ['id' => 1];
        $item = [
            'name' => 'user',
            'content' => 'John',
            'order' => false,
        ];

        $data = Helper::includeInArray($item, $data);
        $expected = [
            'id' => 1,
            'user' => 'John',
        ];
        $this->assertEquals($expected, $data);
    }

    public function test_include_in_array_with_order()
    {
        $data = [
            'id' => 1,
            'foo' => 'bar',
        ];
        $item = [
            'name' => 'user',
            'content' => 'John',
            'order' => 1,
        ];

        $data = Helper::includeInArray($item, $data);
        $expected = [
            'id' => 1,
            'user' => 'John',
            'foo' => 'bar',
        ];
        $this->assertEquals($expected, $data);
    }

    public function test_include_in_array_with_order_outside_of_array_length()
    {
        $data = [
            'id' => 1,
            'foo' => 'bar',
        ];
        $item = [
            'name' => 'user',
            'content' => 'John',
            'order' => 2,
        ];

        $data = Helper::includeInArray($item, $data);
        $expected = [
            'id' => 1,
            'foo' => 'bar',
            'user' => 'John',
        ];
        $this->assertEquals($expected, $data);
    }

    public function test_compile_content_blade()
    {
        $content = '{!! $id !!}';
        $data = ['id' => 2];
        $obj = new stdClass();
        $obj->id = 2;

        $compiled = Helper::compileContent($content, $data, $obj);
        $this->assertEquals(2, $compiled);
    }

    public function test_compile_content_string()
    {
        $content = 'string';
        $data = ['id' => 2];
        $obj = new stdClass();
        $obj->id = 2;

        $compiled = Helper::compileContent($content, $data, $obj);
        $this->assertEquals('string', $compiled);
    }

    public function test_compile_content_integer()
    {
        $content = 1;
        $data = ['id' => 2];
        $obj = new stdClass();
        $obj->id = 2;

        $compiled = Helper::compileContent($content, $data, $obj);
        $this->assertEquals(1, $compiled);
    }

    public function test_compile_content_function()
    {
        $content = function ($obj) {
            return $obj->id;
        };
        $data = ['id' => 2];
        $obj = new stdClass();
        $obj->id = 2;

        $compiled = Helper::compileContent($content, $data, $obj);
        $this->assertEquals(2, $compiled);
    }

    public function test_compile_content_callable_class()
    {
        $content = new class
        {
            public function __invoke($obj)
            {
                return $obj->id;
            }
        };
        $data = ['id' => 2];
        $obj = new stdClass();
        $obj->id = 2;

        $compiled = Helper::compileContent($content, $data, $obj);
        $this->assertEquals(2, $compiled);
    }

    public function test_compile_blade()
    {
        $content = '{!! $id !!}';
        $data = ['id' => 2];
        $compiled = Helper::compileBlade($content, $data);
        $this->assertEquals(2, $compiled);
    }

    public function test_get_mixed_value()
    {
        $carbon = Carbon::createFromDate(2015, 1, 1);
        $data = [
            'id' => 1,
            'name' => 'John',
            'created_at' => '1234',
        ];
        $class = new stdClass();
        $class->id = 1;
        $class->name = 'John';
        $class->created_at = $carbon;

        $compiled = Helper::getMixedValue($data, $class);
        $expected = [
            'id' => 1,
            'name' => 'John',
            'created_at' => $carbon,
            'model' => $class,
        ];
        $this->assertEquals($expected, $compiled);
    }

    public function test_cast_to_array_an_object()
    {
        $class = new stdClass();
        $class->id = 1;
        $compiled = Helper::castToArray($class);
        $this->assertEquals(['id' => 1], $compiled);
    }

    public function test_cast_to_array_an_array()
    {
        $class = ['id' => 1];
        $compiled = Helper::castToArray($class);
        $this->assertEquals(['id' => 1], $compiled);
    }

    public function test_get_or_method()
    {
        $method = 'whereIn';
        $result = Helper::getOrMethod($method);
        $this->assertEquals('orWhereIn', $result);

        $method = 'orWhereIn';
        $result = Helper::getOrMethod($method);
        $this->assertEquals('orWhereIn', $result);
    }

    public function test_convert_to_array()
    {
        $row = new stdClass();
        $row->id = 1;
        $row->name = 'John';
        $row->posts = ['id' => 1, 'title' => 'Demo'];
        $author = new stdClass();
        $author->name = 'Billy';
        $row->author = $author;

        $result = Helper::convertToArray($row);
        $expected = [
            'id' => 1,
            'name' => 'John',
            'posts' => [
                'id' => 1,
                'title' => 'Demo',
            ],
            'author' => [
                'name' => 'Billy',
            ],
        ];
        $this->assertEquals($expected, $result);
    }

    public function test_transform()
    {
        $data = [
            [
                'id' => 1,
                'author' => 'John',
                'created_at' => Carbon::createFromFormat('Y-m-d H:i:s', '2015-1-1 00:00:00'),
            ],
            [
                'id' => 2,
                'author' => 'Billy',
                'created_at' => Carbon::createFromFormat('Y-m-d H:i:s', '2015-1-1 00:00:00'),
            ],
        ];
        $result = Helper::transform($data);
        $expected = [
            ['id' => 1, 'author' => 'John', 'created_at' => '2015-01-01 00:00:00'],
            ['id' => 2, 'author' => 'Billy', 'created_at' => '2015-01-01 00:00:00'],
        ];
        $this->assertEquals($expected, $result);
    }

    public function test_build_parameters_with_3_args()
    {
        $args = ['whereIn', ['foo', ['y', 'x']], 'keyword'];
        $result = Helper::buildParameters($args);
        $expected = [
            'whereIn',
            'foo',
            ['y', 'x'],
        ];
        $this->assertEquals($expected, $result);
    }

    public function test_build_parameters_with_2_args()
    {
        $args = [['where', 'foo'], 'keyword'];
        $result = Helper::buildParameters($args);
        $expected = ['where', 'foo'];
        $this->assertEquals($expected, $result);
    }

    public function test_replace_pattern_with_keyword()
    {
        $subject = [
            'foo in ?',
            ['$1'],
        ];
        $keyword = 'bar';
        $result = Helper::replacePatternWithKeyword($subject, $keyword, '$1');
        $this->assertEquals(['foo in ?', ['bar']], $result);
    }

    public function test_wildcard_like_string()
    {
        $str = 'keyword';

        $keyword = Helper::wildcardLikeString($str);

        $this->assertEquals('%k%e%y%w%o%r%d%', $keyword);
    }

    public function test_wildcard_string()
    {
        $str = 'Keyword';

        $keyword = Helper::wildcardString($str, '.*', true);

        $this->assertEquals('.*k.*e.*y.*w.*o.*r.*d.*', $keyword);
    }
}
