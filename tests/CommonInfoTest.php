<?php
require './vendor/autoload.php';
use PHPUnit\Framework\TestCase;
use Amazon\CommonInfo;
class CommonInfoTest extends TestCase
{
    public function testGetProductInfoByAsin()
    {
        $info = CommonInfo::getInstance()->getProductInfoByAsin('B0093IHZQW');
        var_dump($info);
        $this->assertArrayHasKey('url', $info);
    }
}