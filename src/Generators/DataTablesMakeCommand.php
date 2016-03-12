<?php

namespace Yajra\Datatables\Generators;

use Illuminate\Console\GeneratorCommand;

/**
 * Class DataTablesMakeCommand.
 *
 * @package Yajra\Datatables\Generators
 * @author  Arjay Angeles <aqangeles@gmail.com>
 */
class DataTablesMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'datatables:make';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new DataTable Service class.';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'DataTable';

    /**
     * Get the default namespace for the class.
     *
     * @param  string $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\DataTables';
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return __DIR__ . '/stubs/datatables.stub';
    }
}
