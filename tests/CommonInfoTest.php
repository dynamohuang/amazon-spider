<?php
require './vendor/autoload.php';
use PHPUnit\Framework\TestCase;
use Amazon\AmazonCommonInfo;
class CommonInfoTest extends TestCase
{
    public function testGetProductInfoByAsin()
    {
        $info = AmazonCommonInfo::getInstance()->getProductInfoByAsin('B0093IHZQW');
        var_dump($info);
        $this->assertArrayHasKey('url', $info);
    }
}