<?php

namespace Yajra\DataTables\Utilities;

use Closure;
use DateTime;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use ReflectionFunction;
use ReflectionMethod;

class Helper
{
    /**
     * Places item of extra columns into results by care of their order.
     *
     * @param  array  $item
     * @param  array  $array
     * @return array
     */
    public static function includeInArray($item, $array)
    {
        if (self::isItemOrderInvalid($item, $array)) {
            return array_merge($array, [$item['name'] => $item['content']]);
        }

        $count = 0;
        $last = $array;
        $first = [];
        foreach ($array as $key => $value) {
            if ($count == $item['order']) {
                continue;
            }

            unset($last[$key]);
            $first[$key] = $value;

            $count++;
        }

        return array_merge($first, [$item['name'] => $item['content']], $last);
    }

    /**
     * Check if item order is valid.
     *
     * @param  array  $item
     * @param  array  $array
     * @return bool
     */
    protected static function isItemOrderInvalid($item, $array)
    {
        return $item['order'] === false || $item['order'] >= count($array);
    }

    /**
     * Gets the parameter of a callable thing (from is_callable) and returns it's arguments using reflection.
     *
     * @param  callable  $callable
     * @return \ReflectionParameter[]
     *
     * @throws \ReflectionException
     * @throws \InvalidArgumentException
     */
    private static function reflectCallableParameters($callable)
    {
        /*
        loosely after https://github.com/technically-php/callable-reflection/blob/main/src/CallableReflection.php#L72-L86.
        Licence is compatible, both project use MIT
        */
        if ($callable instanceof Closure) {
            $reflection = new ReflectionFunction($callable);
        } elseif (is_string($callable) && function_exists($callable)) {
            $reflection = new ReflectionFunction($callable);
        } elseif (is_string($callable) && str_contains($callable, '::')) {
            $reflection = new ReflectionMethod($callable);
        } elseif (is_object($callable) && method_exists($callable, '__invoke')) {
            $reflection = new ReflectionMethod($callable, '__invoke');
        } else {
            throw new \InvalidArgumentException('argument is not callable or the code is wrong');
        }

        return $reflection->getParameters();
    }

    /**
     * Determines if content is callable or blade string, processes and returns.
     *
     * @param  mixed  $content  Pre-processed content
     * @param  array  $data  data to use with blade template
     * @param  array|object  $param  parameter to call with callable
     * @return mixed
     *
     * @throws \ReflectionException
     */
    public static function compileContent($content, array $data, array|object $param)
    {
        if (is_string($content)) {
            return static::compileBlade($content, static::getMixedValue($data, $param));
        }

        if (is_callable($content)) {
            $arguments = self::reflectCallableParameters($content);

            if (count($arguments) > 0) {
                return app()->call($content, [$arguments[0]->name => $param]);
            }

            return $content($param);
        }

        return $content;
    }

    /**
     * Parses and compiles strings by using Blade Template System.
     *
     * @param  string  $str
     * @param  array  $data
     * @return false|string
     */
    public static function compileBlade($str, $data = [])
    {
        if (view()->exists($str)) {
            /** @var view-string $str */
            return view($str, $data)->render();
        }

        ob_start() && extract($data, EXTR_SKIP);
        eval('?>'.app('blade.compiler')->compileString($str));
        $str = ob_get_contents();
        ob_end_clean();

        return $str;
    }

    /**
     * Get a mixed value of custom data and the parameters.
     *
     * @param  array  $data
     * @param  array|object  $param
     * @return array
     */
    public static function getMixedValue(array $data, array|object $param)
    {
        $casted = self::castToArray($param);

        $data['model'] = $param;

        foreach ($data as $key => $value) {
            if (isset($casted[$key])) {
                $data[$key] = $casted[$key];
            }
        }

        return $data;
    }

    /**
     * Cast the parameter into an array.
     *
     * @param  array|object  $param
     * @return array
     */
    public static function castToArray(array|object $param): array
    {
        if ($param instanceof Arrayable) {
            return $param->toArray();
        }

        return (array) $param;
    }

    /**
     * Get equivalent or method of query builder.
     *
     * @param  string  $method
     * @return string
     */
    public static function getOrMethod($method)
    {
        if (! Str::contains(Str::lower($method), 'or')) {
            return 'or'.ucfirst($method);
        }

        return $method;
    }

    /**
     * Converts array object values to associative array.
     *
     * @param  mixed  $row
     * @param  array  $filters
     * @return array
     */
    public static function convertToArray($row, $filters = [])
    {
        $row = is_object($row) && method_exists($row, 'makeHidden') ? $row->makeHidden(Arr::get($filters, 'hidden',
            [])) : $row;
        $row = is_object($row) && method_exists($row, 'makeVisible') ? $row->makeVisible(Arr::get($filters, 'visible',
            [])) : $row;
        $data = $row instanceof Arrayable ? $row->toArray() : (array) $row;

        foreach ($data as &$value) {
            if (is_object($value) || is_array($value)) {
                $value = self::convertToArray($value);
            }

            unset($value);
        }

        return $data;
    }

    /**
     * @param  array  $data
     * @return array
     */
    public static function transform(array $data)
    {
        return array_map(function ($row) {
            return self::transformRow($row);
        }, $data);
    }

    /**
     * Transform row data into an array.
     *
     * @param  array  $row
     * @return array
     */
    protected static function transformRow($row)
    {
        foreach ($row as $key => $value) {
            if ($value instanceof DateTime) {
                $row[$key] = $value->format('Y-m-d H:i:s');
            } else {
                if (is_object($value) && method_exists($value, '__toString')) {
                    $row[$key] = $value->__toString();
                } else {
                    $row[$key] = $value;
                }
            }
        }

        return $row;
    }

    /**
     * Build parameters depending on # of arguments passed.
     *
     * @param  array  $args
     * @return array
     */
    public static function buildParameters(array $args)
    {
        $parameters = [];

        if (count($args) > 2) {
            $parameters[] = $args[0];
            foreach ($args[1] as $param) {
                $parameters[] = $param;
            }
        } else {
            foreach ($args[0] as $param) {
                $parameters[] = $param;
            }
        }

        return $parameters;
    }

    /**
     * Replace all pattern occurrences with keyword.
     *
     * @param  array  $subject
     * @param  string  $keyword
     * @param  string  $pattern
     * @return array
     */
    public static function replacePatternWithKeyword(array $subject, $keyword, $pattern = '$1')
    {
        $parameters = [];
        foreach ($subject as $param) {
            if (is_array($param)) {
                $parameters[] = self::replacePatternWithKeyword($param, $keyword, $pattern);
            } else {
                $parameters[] = str_replace($pattern, $keyword, $param);
            }
        }

        return $parameters;
    }

    /**
     * Get column name from string.
     *
     * @param  string  $str
     * @param  bool  $wantsAlias
     * @return string
     */
    public static function extractColumnName($str, $wantsAlias)
    {
        $matches = explode(' as ', Str::lower($str));

        if (count($matches) > 1) {
            if ($wantsAlias) {
                return array_pop($matches);
            }

            return array_shift($matches);
        } elseif (strpos($str, '.')) {
            $array = explode('.', $str);

            return array_pop($array);
        }

        return $str;
    }

    /**
     * Adds % wildcards to the given string.
     *
     * @param  string  $str
     * @param  bool  $lowercase
     * @return string
     */
    public static function wildcardLikeString($str, $lowercase = true)
    {
        return static::wildcardString($str, '%', $lowercase);
    }

    /**
     * Adds wildcards to the given string.
     *
     * @param  string  $str
     * @param  string  $wildcard
     * @param  bool  $lowercase
     * @return string
     */
    public static function wildcardString($str, $wildcard, $lowercase = true)
    {
        $wild = $wildcard;
        $chars = (array) preg_split('//u', $str, -1, PREG_SPLIT_NO_EMPTY);

        if (count($chars) > 0) {
            foreach ($chars as $char) {
                $wild .= $char.$wildcard;
            }
        }

        if ($lowercase) {
            $wild = Str::lower($wild);
        }

        return $wild;
    }

    public static function toJsonScript(array $parameters, int $options = 0): string
    {
        $values = [];
        $replacements = [];

        foreach (Arr::dot($parameters) as $key => $value) {
            if (self::isJavascript($value, $key)) {
                $values[] = trim($value);
                Arr::set($parameters, $key, '%'.$key.'%');
                $replacements[] = '"%'.$key.'%"';
            }
        }

        $new = [];
        foreach ($parameters as $key => $value) {
            Arr::set($new, $key, $value);
        }

        $json = (string) json_encode($new, $options);

        return str_replace($replacements, $values, $json);
    }

    public static function isJavascript(string|array|object|null $value, string $key): bool
    {
        if (empty($value) || is_array($value) || is_object($value)) {
            return false;
        }

        /** @var array $callbacks */
        $callbacks = config('datatables.callback', ['$', '$.', 'function']);

        if (Str::startsWith($key, 'language.')) {
            return false;
        }

        return Str::startsWith(trim($value), $callbacks) || Str::contains($key, ['editor', 'minDate', 'maxDate']);
    }
}
