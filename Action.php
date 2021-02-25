<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

define('BASE_THUMB_PLUGIN_PATH',Helper::options()->pluginDir('ThumbOptimizer').'/ThumbOptimizer/');
require_once BASE_THUMB_PLUGIN_PATH . 'vendor/autoload.php';

require_once 'TUtils.php';

use Grafika\Grafika;

class ThumbOptimizer_Action extends Typecho_Widget implements Widget_Interface_Do
{
    const UPLOAD_DIR = '/usr/uploads';
    private $db;

    public function __construct($request, $response, $params = NULL)
    {
        parent::__construct($request, $response, $params);
        $this->db = Typecho_Db::get();
    }

    public function action()
    {
        // TODO: Implement action() method.
    }

    public function curl_post($url, $post_data)
    {
//        Typecho_Widget::widget('Widget_Security')->getTokenUrl(Typecho_Common::url('/action/upload', $this->rootUrl));
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        $output = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        var_dump($url);
        var_dump($httpCode);
        var_dump($err);
//        $fp = fopen('write.txt', 'a+b'); //a+读写方式打开，将文件指针指向文件末尾。b为强制使用二进制模式. 如果文件不存在则尝试创建之。
//        fwrite($fp,print_r($url,true));
//        fwrite($fp,print_r($httpCode,true));
//        fwrite($fp,print_r($err,true));
//        fwrite($fp,print_r($output,true));
//        fclose($fp); //关闭打开的文件。
        return $output;
    }


    /**
     * 创建上传路径
     *
     * @access private
     * @param string $path 路径
     * @return boolean
     */
    public function makeUploadDir($path)
    {
        $path = preg_replace("/\\\+/", '/', $path);
        $current = rtrim($path, '/');
        $last = $current;

        while (!is_dir($current) && false !== strpos($path, '/')) {
            $last = $current;
            $current = dirname($current);
        }

        if ($last == $current) {
            return true;
        }

        if (!@mkdir($last)) {
            return false;
        }

        $stat = @stat($last);
        $perms = $stat['mode'] & 0007777;
        @chmod($last, $perms);

        return self::makeUploadDir($path);
    }

    /**
     * 检查字段名是否符合要求
     *
     * @param string $name
     * @access public
     * @return boolean
     */
    public function checkFieldName($name)
    {
        return preg_match("/^[_a-z][_a-z0-9]*$/i", $name);
    }
    /**
     * 设置单个字段
     *
     * @param string $name
     * @param string $type
     * @param string $value
     * @param integer $cid
     * @access public
     * @return integer
     */
    public function setField($name, $type, $value, $cid)
    {
        if (empty($name) || !$this->checkFieldName($name)
            || !in_array($type, array('str', 'int', 'float'))) {
            return false;
        }

        $exist = $this->db->fetchRow($this->db->select('cid')->from('table.fields')
            ->where('cid = ? AND name = ?', $cid, $name));

        if (empty($exist)) {
            return $this->db->query($this->db->insert('table.fields')
                ->rows(array(
                    'cid'           =>  $cid,
                    'name'          =>  $name,
                    'type'          =>  $type,
                    'str_value'     =>  'str' == $type ? $value : NULL,
                    'int_value'     =>  'int' == $type ? intval($value) : 0,
                    'float_value'   =>  'float' == $type ? floatval($value) : 0
                )));
        } else {
            return $this->db->query($this->db->update('table.fields')
                ->rows(array(
                    'type'          =>  $type,
                    'str_value'     =>  'str' == $type ? $value : NULL,
                    'int_value'     =>  'int' == $type ? intval($value) : 0,
                    'float_value'   =>  'float' == $type ? floatval($value) : 0
                ))
                ->where('cid = ? AND name = ?', $cid, $name));
        }
    }

    public function test_func()
    {
//        $url = 'https://pic2.zhimg.com/80/v2-40d42747bec5c00503e4bd47566beb65_720w.jpg';
//        $ret = $utils->save_file($url,'admin','814976',395);
//        var_dump($ret);




    }
}