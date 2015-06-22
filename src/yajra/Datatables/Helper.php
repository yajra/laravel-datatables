<?php

 namespace yajra\Datatables;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Illuminate\View\Compilers\BladeCompiler;

class Helper {

    /**
     * Parses and compiles strings by using Blade Template System.
     *
     * @param       $str
     * @param array $data
     * @return string
     * @throws \Exception
     */
    public static function compileBlade($str, $data = [])
    {
        $empty_filesystem_instance = new Filesystem();
        $blade                     = new BladeCompiler($empty_filesystem_instance, 'datatables');
        $parsed_string             = $blade->compileString($str);

        ob_start() && extract($data, EXTR_SKIP);

        try {
            eval('?>' . $parsed_string);
        } catch (\Exception $e) {
            ob_end_clean();
            throw $e;
        }

        $str = ob_get_contents();
        ob_end_clean();

        return $str;
    }

    /**
     * Places item of extra columns into result_array by care of their order.
     *
     * @param  $item
     * @param  $array
     * @return array
     */
    public static function includeInArray($item, $array)
    {
        if ($item['order'] === false) {
            return array_merge($array, [$item['name'] => $item['content']]);
        } else {
            $count = 0;
            $last  = $array;
            $first = [];
            foreach ($array as $key => $value) {
                if ($count == $item['order']) {
                    return array_merge($first, [$item['name'] => $item['content']], $last);
                }

                unset($last[$key]);
                $first[$key] = $value;

                $count++;
            }
        }
    }

    /**
     * @param mixed $value
     * @param mixed $data
     * @param mixed $object
     * @return mixed
     * @throws \Exception
     */
    public static function compileContent($value, $data, $object)
    {
        if (is_string($value['content'])) :
            $value['content'] = Helper::compileBlade($value['content'], $data);

            return $value;
        elseif (is_callable($value['content'])) :
            $value['content'] = $value['content']($object);

            return $value;
        endif;

        return $value;
    }

    /**
     * Determines if content is callable or blade string, processes and returns.
     *
     * @param string|callable $content Pre-processed content
     * @param mixed $data data to use with blade template
     * @param mixed $param parameter to call with callable
     * @return string Processed content
     */
    public static function getContent($content, $data = null, $param = null)
    {
        if (is_string($content)) {
            $return = Helper::compileBlade($content, $data);
        } elseif (is_callable($content)) {
            $return = $content($param);
        } else {
            $return = $content;
        }

        return $return;
    }

    /**
     * Get equivalent or method of query builder.
     *
     * @param string $method
     * @return string
     */
    public static function getOrMethod($method)
    {
        if ( ! Str::contains(Str::lower($method), 'or')) {
            return 'or' . ucfirst($method);
        }

        return $method;
    }

    /**
     * Wrap value depending on database type.
     *
     * @param string $database
     * @param string $value
     * @return string
     */
    public static function wrapValue($database, $value)
    {
        $parts  = explode('.', $value);
        $column = '';
        foreach ($parts as $key) {
            $column = Helper::wrapColumn($database, $key, $column);
        }

        return substr($column, 0, strlen($column) - 1);
    }
    /**
     * Database column wrapper
     *
     * @param string $database
     * @param string $key
     * @param string $column
     * @return string
     */
    public static function wrapColumn($database, $key, $column)
    {
        switch ($database) {
            case 'mysql':
                $column .= '`' . str_replace('`', '``', $key) . '`' . '.';
                break;

            case 'sqlsrv':
                $column .= '[' . str_replace(']', ']]', $key) . ']' . '.';
                break;

            case 'pgsql':
            case 'sqlite':
                $column .= '"' . str_replace('"', '""', $key) . '"' . '.';
                break;

            default:
                $column .= $key . '.';
        }

        return $column;
    }
}
