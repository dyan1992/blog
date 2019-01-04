<?php
/**
 * Created by PhpStorm.
 * User: dy
 * Date: 2018/12/28
 * Time: 17:37
 */
use Yaf\Controller_Abstract;
class Base_Controller extends Controller_Abstract
{
    public function init(){

    }

    /**
     * 获取 GET 参数
     * @param $key
     * @param bool $filter
     * @return null|string
     */
    public function get($key,$filter = true){
        if($filter){
            return filterStr($key);
        }
        return $key;
    }

    /**
     * 获取 GET 参数
     *
     * @param      $key
     * @param bool $filter
     * @return array|null|string
     */
    public function getQuery($key, $filter = true)
    {
        if ($filter) {
            return filterStr($this->getRequest()->getQuery($key));
        }

        return $this->getRequest()->getQuery($key);
    }

    /**
     * 获取 POST 参数
     *
     * @param      $key
     * @param bool $filter
     * @return array|null|string
     */
    public function getPost($key, $filter = true)
    {
        if ($filter) {
            return filterStr($this->getRequest()->getPost($key));
        }

        return $this->getRequest()->getPost($key);
    }
}