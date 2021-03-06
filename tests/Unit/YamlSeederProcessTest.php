<?php

namespace AMBERSIVE\YamlSeeder\Tests\Unit\Classes;


use Config;
use File;
use AMBERSIVE\Tests\TestCase;

use AMBERSIVE\YamlSeeder\Classes\YamlSeederProcess;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class YamlSeederProcessTest extends TestCase
{

    use DatabaseMigrations;
    use RefreshDatabase;

    public YamlSeederProcess $process;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('yaml-seeder.path', __DIR__.'/../Examples/Seeders');
        File::copy(__DIR__.'/../Examples/Seeders/demo.yml', __DIR__.'/../Examples/Seeders/demo.ori');

        $this->process = new YamlSeederProcess(__DIR__.'/../Examples/Seeders/demo.yml');

    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Reset the demo file
        File::delete(__DIR__.'/../Examples/Seeders/demo.yml');
        File::move(__DIR__.'/../Examples/Seeders/demo.ori', __DIR__.'/../Examples/Seeders/demo.yml');

    }
    
        /**
     * Test if the yaml seeder will seed the yaml files into you application
     */
    public function testIfYamlSeederProcessCanExtractModel():void {

        $this->process->load();
        $modelInstance = $this->invokeMethod($this->process, 'extractModelInstance');
        $this->assertNotNull($modelInstance);

    }

    /**
     * Test if the process throws a ValidationException when the data was not loaded
     */
    public function testIfYamlSeederProcessThrowExeptionIfYamlNotLoaded():void {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $modelInstance = $this->invokeMethod($this->process, 'extractModelInstance');
    }

    /**
     * Test if the process will sanitize the item by using the fillable model
     */
    public function testIfYamlSeederProcessSanitizItem():void {

        $this->process->load();

        $result = $this->invokeMethod($this->process, 'sanitizeItem', [[
            'id' => 1000,
            'monkey' => true
        ]]);

        $this->assertNotNull($result);
        $this->assertTrue(isset($result['id']));
        $this->assertFalse(isset($result['monkey']));

    }

    /**
     * Test if the sanitize function requires the yaml to be loaded
     */
    public function testIfYamlSeederProcessSanitizeItemWillReturnEmptyIfLoadWasNotExecuted():void {

        $result = $this->invokeMethod($this->process, 'sanitizeItem', [[
            'id' => 1000,
            'monkey' => true
        ]]);

        $this->assertNotNull($result);
        $this->assertFalse(isset($result['id']));
        $this->assertFalse(isset($result['monkey']));

    }

    /**
     * Test if a single entry will be savedd
     */
    public function testIfYamlSeederProccessSaveItemWillUpdateTheYamlData():void {

        // Prepare
        $this->process->load();

        // Execute
        $itemOri = $this->process->yamlData['data'][0];
        
        $result = $this->invokeMethod($this->process, 'saveItem', [$itemOri,0]);

        // Assertions
        $this->assertNotNull($itemOri);
        $this->assertTrue($result);

    }

    /**
     * Test if the createItemData function will not remove the id
     */
    public function testIfYamlSeeerProcessCreateItemDataWillAlwaysContainTheId(): void {

        // Prepare
        $item = $this->invokeMethod($this->process, 'sanitizeItem', [[
            'id' => 1000,
            'monkey' => true
        ]]);
        
        // Executed
        $result = $this->invokeMethod($this->process, 'createItemData', [$item]);

        $this->assertNotNull($result);
        $this->assertFalse(isset($result['id']));
        $this->assertFalse(isset($result['monkey']));

    }

    /**
     * Test if createItemData will always return the required data for the create of the model data
     */
    public function testIfYamlSeederProcessCreateItemDataWillAddAllRequiredParameters(): void {

        // Prepare
        $this->process->load();

        // Execute
        $itemOri = $this->process->yamlData['data'][0];
        $item = $this->invokeMethod($this->process, 'convertData', [$itemOri]);

        $result = $this->invokeMethod($this->process, 'createItemData', [$item]);

        // Assertions

        $this->assertEquals(3, collect($result)->count());

    }

    public function testIfYamlSeederProcessCreateItemDataWillExtractTheInformationFromYamlAttribute():void {

        // Prepare
        $process = new YamlSeederProcess(__DIR__.'/../Examples/Seeders/demo2.yml');
        $process->load();    

        // Execute
        $itemOri = $process->yamlData['data'][0];
        $item = $this->invokeMethod($process, 'convertData', [$itemOri]);

        $result = $this->invokeMethod($process, 'createItemData', [$item]);



        // Assertions

        $this->assertEquals(1, collect($result)->count());


    }

    /**
     * Test if the exclude option will be returned 
     */
    public function testIfYamlSeederProcessExcludeReturnsABooleanIfTheFileShouldNotRunInTheSeedingProcces():void {

        $result = $this->process->exclude();
        $this->assertFalse($result);

    }

    /**
     * This test checks if a true value will be returned
     */
    public function testIfYamlSeederProcessWillReturnTheCorrectValueForTheExcludeOption():void {

        // Prepare
        $process = new YamlSeederProcess(__DIR__.'/../Examples/Seeders/demo2.yml');
        $result = $process->load()->exclude();
        
        // Assetions
        $this->assertTrue($result);

    }

    /**
     * This test checks if a true value will be returned
     */
    public function testIfYamlSeederProcessWillHandlePreSeedFiles():void {


        // Prepare
        $process = new YamlSeederProcess(__DIR__.'/../Examples/Seeders/demo3.yml');
        $result = $process->load()->runAsPre();
        
        // Assetions
        $this->assertTrue($result);

    }

    /**
     * Test if the convert data will transform the to a valid output format
     */
    public function testIfYamlSeederProcessConvertDataWillReturnTheDataCorrectly():void {

        $item = [
            'id' => 1000,
            'migration_raw' => [
                'field'      => 'migration',
                'convertTo'  => 'password',
                'value'      => 'asdfasdf'
            ]
        ];
        
        $result = $this->invokeMethod($this->process, 'convertData', [$item]);

        $this->assertEquals(2, collect($result)->count());
        $this->assertTrue(isset($result['migration']));
        $this->assertNotEquals(data_get($item, 'migration_raw.value'), $result['migration']);

    }

    public function testIfYamlSeederProcessWillModifyTheYamlIfAConvertionHappend():void {

        $this->process->load();
        
        $yamlCurrent = $this->process->yamlData;
        $yamlOri = $this->process->yamlOrginal;

        $this->assertEquals($yamlCurrent, $yamlOri);
        
        $result = $this->invokeMethod($this->process, 'execute', []);

        $yamlCurrent = $this->process->yamlData;
        $yamlOri = $this->process->yamlOrginal;

        $this->assertNotEquals($yamlCurrent, $yamlOri);

    }
        
    /**
     * Make a private function callable
     *
     * @param  mixed $object
     * @param  mixed $methodName
     * @param  mixed $parameters
     * @return void
     */
    protected function invokeMethod(&$object, $methodName, array $parameters = []) {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }

}