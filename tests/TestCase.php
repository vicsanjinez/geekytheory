<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

	/**
	 * The base URL to use while testing the application.
	 *
	 * @var string
	 */
	protected $baseUrl = 'http://localhost';

	public function setUp()
	{
		parent::setUp();
		$this->createApplication();
		Artisan::call('migrate:reset');
		Artisan::call('migrate');
		Artisan::call('db:seed', array("--class" => "TestDatabaseSeeder"));
	}

	public function tearDown()
	{
		Artisan::call('migrate:reset');
		parent::tearDown();
	}
}
