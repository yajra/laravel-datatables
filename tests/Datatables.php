<?php

use Mockery as m;
use Bllim\Datatables\Datatables as Datatables;

/**
* Demo Table
*/
class Demo extends Illuminate\Database\Eloquent\Model {}

class DatatablesTest extends Illuminate\Foundation\Testing\TestCase  {

	public function createApplication()
	{
		$unitTesting = true;

		$testEnvironment = 'testing';

		return require __DIR__.'/bootstrap/start.php';
	}

	public function setUp()
	{
		parent::setUp();

		Config::set('database.default', 'sqlite');
		Config::set('database.sqlite.database', ':memory:');

		Schema::dropIfExists('demos');
		Schema::create('demos', function($table){
			$table->increments('id');
			$table->string('name');
		});
		Demo::insert(array('name'=>'demo datatables'));
	}

	public function tearDown()
	{
		Schema::dropIfExists('demos');
	}

	public function test_demo_count()
	{
		$this->assertEquals(1, Demo::count());
	}

	public function test_datatables_make_function()
	{
		$demo = DB::table('demos')->select('id','name');
		$output = Datatables::of($demo)->make();
		$this->assertInstanceOf('Illuminate\Http\JsonResponse', $output);
	}


}
