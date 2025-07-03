<?php

namespace Vanderbilt\REDCap\Tests\Form;

use PHPUnit\Framework\TestCase;

class FormTest extends TestCase
{
    function testGetValueInActionTag(){
        $paramName = 'FOO';
        
        $assert = function($expected, $actionTags) use ($paramName){
            $actual = \Form::getValueInActionTag($actionTags, "@$paramName");
            $this->assertSame($expected, $actual);
        };

        $assert('1', "@$paramName=1");
        $assert('2', "@$paramName=2 @SOME-OTHER-TAG"); // first of multiple
        $assert('3', "@SOME-TAG @$paramName=3"); // last of multiple
        $assert('4', "@SOME-TAG @$paramName=4 @SOME-OTHER-TAG"); // middle of mutliple
        $assert('5 6', "@$paramName='5 6'");
        $assert('7 8', "@$paramName=\"7 8\"");
        $assert('', '');
        $assert('', null);
        $assert('', "@SOME-OTHER-TAG");
    }


	public function testParseSliderLabels()
	{
		$labels = \Form::parseSliderLabels('0');
		$this->assertEquals(0, $labels['left']);

		//
		$labels = \Form::parseSliderLabels('0|100');
		$this->assertEquals(0,   $labels['left']);
		$this->assertEquals(100, $labels['right']);

		//
		$labels = \Form::parseSliderLabels('0|50|100');
		$this->assertEquals(0,   $labels['left']);
		$this->assertEquals(50,  $labels['middle']);
		$this->assertEquals(100, $labels['right']);
	}
}