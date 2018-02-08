<?php

/*
 * amazon工具类
 */
namespace DynamoHuang\AmazonSpider;

use GuzzleHttp\Client;
use Sunra\PhpSimple\HtmlDomParser;
use GuzzleHttp\Exception\RequestException;
Use Predis\Client as Redis;


define('MAX_FILE_SIZE', 60000000);
class AmazonCommonInfo
{

    static protected $price_index = array('priceblock_dealprice', 'riceblock_saleprice', 'priceblock_ourprice');
    static protected $desc_index = array('feature-bullets', 'productDescription');
    static private $instance;
    static  private  $user_agent_list = [
        "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/22.0.1207.1 Safari/537.1",
        "Mozilla/5.0 (X11; CrOS i686 2268.111.0) AppleWebKit/536.11 (KHTML, like Gecko) Chrome/20.0.1132.57 Safari/536.11",
        "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/536.6 (KHTML, like Gecko) Chrome/20.0.1092.0 Safari/536.6",
        "Mozilla/5.0 (Windows NT 6.2) AppleWebKit/536.6 (KHTML, like Gecko) Chrome/20.0.1090.0 Safari/536.6",
        "Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/19.77.34.5 Safari/537.1",
        "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/536.5 (KHTML, like Gecko) Chrome/19.0.1084.9 Safari/536.5",
        "Mozilla/5.0 (Windows NT 6.0) AppleWebKit/536.5 (KHTML, like Gecko) Chrome/19.0.1084.36 Safari/536.5",
        "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/536.3 (KHTML, like Gecko) Chrome/19.0.1063.0 Safari/536.3",
        "Mozilla/5.0 (Windows NT 5.1) AppleWebKit/536.3 (KHTML, like Gecko) Chrome/19.0.1063.0 Safari/536.3",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_0) AppleWebKit/536.3 (KHTML, like Gecko) Chrome/19.0.1063.0 Safari/536.3",
        "Mozilla/5.0 (Windows NT 6.2) AppleWebKit/536.3 (KHTML, like Gecko) Chrome/19.0.1062.0 Safari/536.3",
        "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/536.3 (KHTML, like Gecko) Chrome/19.0.1062.0 Safari/536.3",
        "Mozilla/5.0 (Windows NT 6.2) AppleWebKit/536.3 (KHTML, like Gecko) Chrome/19.0.1061.1 Safari/536.3",
        "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/536.3 (KHTML, like Gecko) Chrome/19.0.1061.1 Safari/536.3",
        "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/536.3 (KHTML, like Gecko) Chrome/19.0.1061.1 Safari/536.3",
        "Mozilla/5.0 (Windows NT 6.2) AppleWebKit/536.3 (KHTML, like Gecko) Chrome/19.0.1061.0 Safari/536.3",
        "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/535.24 (KHTML, like Gecko) Chrome/19.0.1055.1 Safari/535.24",
        "Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/535.24 (KHTML, like Gecko) Chrome/19.0.1055.1 Safari/535.24",
    ];
    static  private $redis_instance;

    static function genClient()
    {
        return new Client(['headers' => ['User-Agent' => array_rand(self::$user_agent_list,1)],'cookies' => false]);
    }

    static public  function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    static public function getRedisInstance(){
        if (is_null(self::$redis_instance)) {
            self::$redis_instance =  new Redis();
        }
        return self::$redis_instance;

    }

    public function getProductInfoByAsin($asin,$append = array('desc'),$refresh = false)
    {
        $redis_key = 'amazon_info_' . $asin;
        if($refresh == false) {
            $info = self::getRedisInstance()->get($redis_key);
            if ($info) {
                return json_decode($info, true);
            }
        }
        $info = array();
        $url = "https://www.amazon.com/dp/".$asin;
        $info['url'] = $url;

        //fetch the description in detail page
        if(in_array('desc',$append)||in_array('rank',$append)) {

            $client = self::genClient();
            try {
                $res = $client->request('GET', $url);
            } catch (RequestException $e) {
                throw new \Exception('[dynamo/amazon] can\'t fetch product detail page');
            }

            if ($res->getStatusCode() != 200) {
                throw new \Exception('[dynamo/amazon] product detail page don\'t return 200');
            }

            $content = $res->getBody()->getContents();
            $dom = HtmlDomParser::str_get_html($content);
            $info['desc'] = '';
            foreach (self::$desc_index as $item) {
                $find = $dom->getElementById($item);
                if ($find && isset($find->outertext)  ) {
                    $info['desc'] = trim($find->outertext);
                    break;
                }
            }
            $info['desc'] = preg_replace('/This fits your.*this fits\.\s+/','',$info['desc']);
            if(in_array('rank',$append)) {
                //大类目及排名 echo
                $body = $dom->find('body', 0)->innertext();
                $preg = '#\#([0-9,]+)[\s]+in[\s]+(.*?)\((?:[\s\S]*)[Ss]ee [Tt]op 100#';
                preg_match($preg, $body, $result);
                if ( ! empty($result)) {
                    $info['rank'] = str_replace(',', '', $result[1]);
                    $info['classify'] = $result[2];
                }
            }
        }

        //fetch base info
        $url = "https://www.amazon.com/product-reviews/".$asin;
        $client = self::genClient();
        try {
            $res = $client->request('GET', $url);
        }catch(RequestException $e){
            throw new \Exception('[dynamo/amazon] can\'t fetch product review page');
        }
        $dom = HtmlDomParser::str_get_html($res->getBody()->getContents());
        $info['title']  = $dom->find('.product-title',0)->find('h1 > a',0)->innertext();

        $price_dom = $dom->find('.arp-price',0);
        if($price_dom){
            $info['price'] = $price_dom->innertext();
            $info['price']  = floatval(trim(ltrim($info['price'], '$')));
        }else{
            $info['price'] = 0;
        }

        $info['image']  =str_replace('US60','US358',$dom->find(".product-image img",0)->getAttribute('src'));

        self::getRedisInstance()->set($redis_key,json_encode($info));
        return $info;
    }



   /*
    * Rule:
    *  1,check the $reviewUrl asin is matched with asin
    *  2,check the $reviewUrl customerId is matched with $profile customerId
    * return the star of the review
    *
    * :todo 3 curl made  to make faster  advise to cache the user profile data in redis
    */

    public function checkProductReviewByProfile($reviewUrl, $profileUrl, $asin)
    {

        $reviewInfo = self::getReviewInfo($reviewUrl);
        // $reviewInfo['star'] < 4.9
        // $reviewInfo['verified'] == 0
        if (!$reviewInfo || $reviewInfo['asin'] != $asin ) {
            return false;
        }

        $profileInfo = self::getProfileInfo($profileUrl);
        if (!$profileInfo || $profileInfo['customerId'] != $reviewInfo['customerId']) {
            return false;
        }
        return $reviewInfo['star'];
    }


    public function getProfileInfo($profileUrl)
    {

        try {
            $client = new Client();
            $res = $client->request('GET', $profileUrl, [
                'allow_redirects' => true,
                'cookies' => false
            ]);
        } catch (RequestException $e) {

            return false;
        }

        if ($res->getStatusCode() != 200) {
            return false;
        }
        $preg = '#\"customerId\":\"([A-Za-z0-9]+)\"#';

        preg_match($preg, $res->getBody()->getContents(), $result);
        if(empty($result)){
            return false;
        }
        $info['customerId'] = $result[1];
        return $info;
    }


    public function getReviewInfo($reviewUrl){
        $client = new Client();
        try {
            $res = $client->request('GET', $reviewUrl);
        }catch(RequestException $e){
            return false;
        }
        if($res->getStatusCode()!=200){
            return false;
        }
        $html = $res->getBody()->getContents();
        $dom = HtmlDomParser::str_get_html($html);

        $preg = '#\/(customer-reviews|review)\/([A-Za-z0-9]+)#';
        preg_match($preg,$reviewUrl, $result);
        if(empty($result)){
            return false;
        }
        $id = $result[2];
        $id_dom = $dom->getElementById($id);


        $profile_url = $id_dom->find('.author',0)->getAttribute('href');
        $info['star'] = floatval(trim(explode('out of',$id_dom->find('a',0)->plaintext)[0]));
        $preg = '#href=\"\/gp\/customer-reviews\/.*?ASIN=([A-Za-z0-9]{10})\"#';
        preg_match($preg, $id_dom->innertext(), $result);
        if(empty($result)){
            return false;
        }
        $info['asin'] = $result[1];

        $user_info = $this->getProfileInfo('https://www.amazon.com'.$profile_url);
        if(!$user_info){
            return false;
        }
        $info['customerId'] = $user_info['customerId'];
        $preg = '#[Vv]erified#';
        preg_match($preg, $id_dom->innertext(), $result);
        $info['verified'] = empty($result)?0:1;
        return $info;
    }
}
