<?php

namespace Yajra\DataTables\Utilities;

use Closure;
use DateTime;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use ReflectionFunction;
use ReflectionMethod;

class Helper
{
    /**
     * Places item of extra columns into results by care of their order.
     */
    public static function includeInArray(array $item, array $array): array
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
     */
    protected static function isItemOrderInvalid(array $item, array $array): bool
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
    public static function compileContent(mixed $content, array $data, array|object $param)
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

        if (is_array($content)) {
            [$view, $viewData] = $content;

            return static::compileBlade($view, static::getMixedValue($data, $param) + $viewData);
        }

        return $content;
    }

    /**
     * Parses and compiles strings by using Blade Template System.
     *
     *
     * @throws \Throwable
     */
    public static function compileBlade(string $str, array $data = []): false|string
    {
        if (view()->exists($str)) {
            /** @var view-string $str */
            return view($str, $data)->render();
        }

        return Blade::render($str, $data);
    }

    /**
     * Get a mixed value of custom data and the parameters.
     */
    public static function getMixedValue(array $data, array|object $param): array
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
     */
    public static function getOrMethod(string $method): string
    {
        if (! Str::contains(Str::lower($method), 'or')) {
            return 'or'.ucfirst($method);
        }

        return $method;
    }

    /**
     * Converts array object values to associative array.
     */
    public static function convertToArray(mixed $row, array $filters = []): array
    {
        if (Arr::get($filters, 'ignore_getters') && is_object($row) && method_exists($row, 'getAttributes')) {
            $data = $row->getAttributes();
            if (method_exists($row, 'getRelations')) {
                foreach ($row->getRelations() as $relationName => $relation) {
                    if (is_iterable($relation)) {
                        foreach ($relation as $relationItem) {
                            $data[$relationName][] = self::convertToArray($relationItem, ['ignore_getters' => true]);
                        }
                    } else {
                        $data[$relationName] = self::convertToArray($relation, ['ignore_getters' => true]);
                    }
                }
            }

            return $data;
        }

        $row = is_object($row) && method_exists($row, 'makeHidden') ? $row->makeHidden(Arr::get($filters, 'hidden',
            [])) : $row;
        $row = is_object($row) && method_exists($row, 'makeVisible') ? $row->makeVisible(Arr::get($filters, 'visible',
            [])) : $row;

        $data = $row instanceof Arrayable ? $row->toArray() : (array) $row;
        foreach ($data as &$value) {
            if ((is_object($value) && ! $value instanceof DateTime) || is_array($value)) {
                $value = self::convertToArray($value);
            }

            unset($value);
        }

        return $data;
    }

    public static function transform(array $data): array
    {
        return array_map(fn ($row) => self::transformRow($row), $data);
    }

    /**
     * Transform row data into an array.
     *
     * @param  array  $row
     */
    protected static function transformRow($row): array
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
     */
    public static function buildParameters(array $args): array
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
     */
    public static function replacePatternWithKeyword(array $subject, string $keyword, string $pattern = '$1'): array
    {
        $parameters = [];
        foreach ($subject as $param) {
            if (is_array($param)) {
                $parameters[] = self::replacePatternWithKeyword($param, $keyword, $pattern);
            } else {
                $parameters[] = str_replace($pattern, $keyword, (string) $param);
            }
        }

        return $parameters;
    }

    /**
     * Get column name from string.
     */
    public static function extractColumnName(string $str, bool $wantsAlias): string
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
     */
    public static function wildcardLikeString(string $str, bool $lowercase = true): string
    {
        return static::wildcardString($str, '%', $lowercase);
    }

    /**
     * Adds wildcards to the given string.
     */
    public static function wildcardString(string $str, string $wildcard, bool $lowercase = true): string
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
                $values[] = trim((string) $value);
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
