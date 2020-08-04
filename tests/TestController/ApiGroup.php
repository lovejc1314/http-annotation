<?php


namespace EasySwoole\HttpAnnotation\Tests\TestController;


use EasySwoole\HttpAnnotation\AnnotationController;
use EasySwoole\HttpAnnotation\AnnotationTag\Api;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiGroup as ApiGroupTag;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiGroupDescription;

/**
 * Class ControllerA
 * @package EasySwoole\HttpAnnotation\Tests\TestController
 * @ApiGroupTag(groupName="GroupA")
 * @ApiGroupDescription()
 */
class ApiGroup extends AnnotationController
{
    /**
     * @Api(path="/A/test",name="a")
     */
    function test()
    {

    }

    /**
     * @Api(path="/",name="22")
     */
    function test2()
    {

    }
}