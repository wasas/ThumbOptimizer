<?php

//define('BASE_THUMB_PLUGIN_PATH',Helper::options()->pluginDir('ThumbOptimizer').'/ThumbOptimizer/');
require_once BASE_THUMB_PLUGIN_PATH . 'vendor/autoload.php';

use Grafika\Grafika;

class TUtils extends Widget_Abstract_Contents
{
    //上传文件目录
    const UPLOAD_DIR = '/usr/uploads';
    public $db;

    public function __construct($request, $response, $params = NULL)
    {
        parent::__construct($request, $response, $params);
        $this->db = Typecho_Db::get();
    }

    /**
     * 内容是否可以被修改
     *
     * @access public
     * @param Typecho_Db_Query $condition 条件
     * @param $user
     * @return mixed
     * @throws Typecho_Db_Exception
     */
    public function isWriteableNow(Typecho_Db_Query $condition, $user)
    {
        $db = Typecho_Db::get();
        $post = $db->fetchRow($condition->select('authorId')->from('table.contents')->limit(1));
        return $post && ($user->pass('editor', true) || $post['authorId'] == $user->uid);
    }

    /**
     * 获取远程或本地文件信息
     * @param  string   $strUrl     远程文件或本地文件地址
     * @param  integer  $intType    调用方式(1:get_headers 2:fsocketopen 3:curl 4:本地文件)
     * @param  array    $arrOptional
     * @return array
     * @author mengdj<mengdj@outlook.com>
     */
    public function remote_filesize($strUrl,$intType=1,$arrOptional=array()){
        $arrRet=array(
            "length"=>0,                    //大小，字节为单位
            "mime"=>"",                     //mime类型
            "filename"=>"",                 //文件名
            "status"=>0                     //状态码
        );
        switch($intType){
            case 1:
                //利用get_headers函数
                if(($arrTmp=get_headers($strUrl,true))){
                    $arrRet=array("length"=>$arrTmp['Content-Length'],"mime"=>$arrTmp['Content-Type']);
                    if(preg_match('/filename=\"(.*)\"/si',$arrTmp['Content-Disposition'],$arr)){
                        $arrRet["filename"]=$arr[1];
                    }
                    if(preg_match('/\s(\d+)\s/',$arrTmp[0],$arr)){
                        $arrRet["status"]=$arr[1];
                    }
                }
                break;
            case 2:
                //利用fsocket
                if(($arrUrl=parse_url($strUrl))){
                    if($fp=@fsockopen($arrUrl['host'],empty($arrUrl['port'])?80:$arrUrl['port'],$error)){
                        @fputs($fp,"GET ".(empty($arrUrl['path'])?'/':$arrUrl['path'])." HTTP/1.1\r\n");
                        @fputs($fp,"Host: $arrUrl[host]\r\n");
                        @fputs($fp,"Connection: Close\r\n\r\n");
                        while(!feof($fp)){
                            $tmp=fgets($fp);
                            if(trim($tmp)==''){
                                //此行代码只读到头信息即可
                                break;
                            }else{
                                (preg_match('/(HTTP.*)(\s\d{3}\s)/',$tmp,$arr))&&$arrRet['status']=trim($arr[2]);
                                (preg_match('/Content-Length:(.*)/si',$tmp,$arr))&&$arrRet['length']=trim($arr[1]);
                                (preg_match('/Content-Type:(.*)/si',$tmp,$arr))&&$arrRet['mime']=trim($arr[1]);
                                (preg_match('/filename=\"(.*)\"/si',$tmp,$arr))&&$arrRet['filename']=trim($arr[1]);
                            }
                        }
                        @fclose($fp);
                    }
                }
                break;
            case 3:
                //利用curl
                if(($ch=curl_init($strUrl))){
                    curl_setopt($ch,CURLOPT_HEADER,1);
                    curl_setopt($ch,CURLOPT_NOBODY,1);
                    curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
                    if(isset($arrOptional['user'])&&isset($arrOptional['password'])){
                        $headers=array('Authorization: Basic '.base64_encode($arrOptional['user'].':'.$arrOptional['password']));
                        curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);
                    }
                    $tmp=curl_exec($ch);
                    curl_close($ch);
                    (preg_match('/Content-Length:\s([0-9].+?)\s/',$tmp,$arr))&&$arrRet['length']=trim($arr[1]);
                    (preg_match('/Content-Type:\s(.*)\s/',$tmp,$arr))&&$arrRet['mime']=trim($arr[1]);
                    (preg_match('/filename=\"(.*)\"/i',$tmp,$arr))&&$arrRet['filename']=trim($arr[1]);
                    (preg_match('/(HTTP.*)(\s\d{3}\s)/',$tmp,$arr))&&$arrRet['status']=trim($arr[2]);
                }
                break;
            case 4:
                //本地处理
                if(file_exists($strUrl)) {
                    $arrRet=array(
                        "length"=>filesize($strUrl),
                        "mime" =>mime_content_type($strUrl),
                        "filename"=>basename($strUrl),
                        "status"=>200
                    );
                }else{
                    $arrRet=array(
                        "length"=>0,
                        "mime" =>'',
                        "filename"=>basename($strUrl),
                        "status"=>404
                    );
                }
                break;
        }
        if(isset($arrOptional['getimagesize'])&&$arrRet['status']=='200'){
            if(($arrTmp=@getimagesize($strUrl))){
                $arrRet['width']=$arrTmp[0];
                $arrRet['height']=$arrTmp[1];
                $arrRet['type']=$arrTmp[2];
                $arrRet['tag']=$arrTmp[3];
                $arrRet['bits']=$arrTmp['bits'];
                $arrRet['channels']=$arrTmp['channels'];
                !isset($arrRet['mime'])&&$arrRet['mime']=$arrTmp['mime'];
            }
        }
        return $arrRet;
    }

    /**
     * 创建上传路径
     *
     * @access private
     * @param string $path 路径
     * @return boolean
     */
    private function makeUploadDir($path)
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

    public function save_netfile_to_attachment($url,$username,$password,$cid = null)
    {
        // 用户登录
        $user = Typecho_Widget::widget('Widget_User');
        if (!$user->login($username,$password)){
            return false;
        }
        //
        $file = [];
        $file['name'] = 'tmpfile';
        // 获取ext
        $farr = self::remote_filesize($url);
        $mime = $farr['mime'];
        $ext = 'png';
        if (!empty($mime)){
            $ext = explode('/',$mime)[1];
            if (empty($ext)){
                $ext = 'png';
            }
        }

        $date = new Typecho_Date();
        $path = Typecho_Common::url(defined('__TYPECHO_UPLOAD_DIR__') ? __TYPECHO_UPLOAD_DIR__ : self::UPLOAD_DIR,
                defined('__TYPECHO_UPLOAD_ROOT_DIR__') ? __TYPECHO_UPLOAD_ROOT_DIR__ : __TYPECHO_ROOT_DIR__)
            . '/' . $date->year . '/' . $date->month;

        //创建上传目录
        if (!is_dir($path)) {
            if (!self::makeUploadDir($path)) {
                return false;
            }
        }

        //获取文件名
        $fileName = sprintf('%u', crc32(uniqid())) . '.' . $ext;
        $path = $path . '/' . $fileName;

        if (!file_exists($path)) {
            $doc_image_data = file_get_contents($url);
            $ret = file_put_contents($path, $doc_image_data);
            if ($ret){
                $file['size'] = filesize($path);
            }else{
                return false;
            }
        }

        //相对存储路径
        $result = array(
            'name' => $fileName,
            'path' => (defined('__TYPECHO_UPLOAD_DIR__') ? __TYPECHO_UPLOAD_DIR__ : self::UPLOAD_DIR)
                . '/' . $date->year . '/' . $date->month . '/' . $fileName,
            'size' => $file['size'],
            'type' => $ext,
            'mime' => Typecho_Common::mimeContentType($path)
        );
        return self::insert_attachment($result,$user,$cid);
    }

    /**
     * 根据本地文件构造 meta
     * @param $path
     * @param $username
     * @param $password
     * @param null $cid
     */
    public function build_meta($path)
    {
        if (!file_exists($path)) {
            return false;
        }
        $pathi = pathinfo($path);
        $file_name = $pathi['filename'];
        $ext = $pathi['extension'];
        if (!$ext or empty($ext)){
            $ext = 'jpg';
        }
        if (!$file_name){
            $file_name = sprintf('%u', crc32(uniqid())) . '.' . $ext;
        }else{
            $file_name = $file_name. '.' . $ext;
        }
        $date = new Typecho_Date();
        //相对存储路径
        $result = array(
            'name' => $file_name,
            'path' => (defined('__TYPECHO_UPLOAD_DIR__') ? __TYPECHO_UPLOAD_DIR__ : self::UPLOAD_DIR)
                . '/' . $date->year . '/' . $date->month . '/' . $file_name,
            'size' => filesize($path),
            'type' => $ext,
            'mime' => Typecho_Common::mimeContentType($path)
        );
        return $result;
    }

    public function save_local_file_to_attachment($path,$username,$password,$cid = null)
    {
        // 暂时不需要
//        if (!file_exists($path)) {
//            return false;
//        }
//        $pathi = pathinfo($path);
//        $file_name = $pathi['filename'];
//        $ext = $pathi['extension'];
//        if (!$ext){
//            $ext = 'jpg';
//        }
//        if (!$file_name){
//            $file_name = sprintf('%u', crc32(uniqid())) . '.' . $ext;;
//        }

    }

    // 保存文件到附件
    public function insert_attachment($file_info, $user, $cid = null)
    {
        $db = Typecho_Db::get();

        $struct = array(
            'title'     =>  $file_info['name'],
            'slug'      =>  $file_info['name'],
            'type'      =>  'attachment',
            'status'    =>  'publish',
            'text'      =>  serialize($file_info),
            'allowComment'      =>  1,
            'allowPing'         =>  0,
            'allowFeed'         =>  1
        );

        if ($cid){
            if (self::isWriteableNow($db->sql()->where('cid = ?', $cid),$user)) {
                $struct['parent'] = $cid;
            }
        }

        $insertId = $this->insert($struct);

        $this->db->fetchRow($this->select()->where('table.contents.cid = ?', $insertId)
            ->where('table.contents.type = ?', 'attachment'), array($this, 'push'));

        return array(
            'cid'       =>  $insertId,
            'title'     =>  $this->attachment->name,
            'type'      =>  $this->attachment->type,
            'size'      =>  $this->attachment->size,
            'bytes'      =>  number_format(ceil($this->attachment->size / 1024)) . ' Kb',
            'isImage'   =>  $this->attachment->isImage,
            'url'       =>  $this->attachment->url,
            'permalink' =>  $this->permalink
        );
    }

    /**
     * @param $post_cid
     * @throws Typecho_Db_Exception
     */
    public function del_attachment($post_cid)
    {
        $condition = $this->db->sql()->where('cid = ?', $post_cid);
        $row = $this->db->fetchRow($this->select()
            ->where('table.contents.type = ?', 'attachment')
            ->where('table.contents.cid = ?', $post_cid)
            ->limit(1), array($this, 'push'));

        if ($this->isWriteable($condition) && $this->delete($condition)) {
            /** 删除文件 */
            Widget_Upload::deleteHandle($row);

            /** 删除评论 */
            $this->db->query($this->db->delete('table.comments')
                ->where('cid = ?', $post_cid));

        }

        unset($condition);
    }

    /**
     * @param $post_cid
     * @param $thumb_url
     * @return false
     */
    public function change_thumb_field($post_cid, $thumb_url)
    {
        try{
            $plugin_options = Helper::options()->plugin('ThumbOptimizer');
        } catch (Exception $e) {
            echo "<script>alert(\"获取配置失败\")</script>";

            return false;
        }
        $replace_thumb = $plugin_options->replace_thumb;
        $udefine_thumb = $plugin_options->udefine_thumb;
        if ($replace_thumb == 'off'){
            return false;
        }
        if ($replace_thumb == 'udefine'){
            $thumb_field = $udefine_thumb;
        }else{
            $thumb_field = $replace_thumb;
        }
        if (empty($thumb_field)){
            return false;
        }
        $this->db->query($this->db->update('table.fields')->rows(['str_value' => $thumb_url])->where('cid = ? and name = ?',$post_cid,$thumb_field));
        return true;
    }
    /**
     * @param $full_image_url
     * @param $post_cid
     * @return array|false
     * @throws Typecho_Exception
     */
    public function make_thumb($full_image_url, $post_cid)
    {

        try{
            $plugin_options = Helper::options()->plugin('ThumbOptimizer');
        } catch (Exception $e) {
            echo "<script>alert(\"获取配置失败\")</script>";

            return false;
        }


        // 用户登录
        $username = $plugin_options->t_username;
        $password = $plugin_options->t_password;

        $user = Typecho_Widget::widget('Widget_User');
        if (!$user->login($username,$password)){
            echo '<script>alert("ThumbOptimizer插件提示：登录失败！");</script>';
            throw new Typecho_Exception('ThumbOptimizer插件提示：用户名密码错误');
        }

        if (!$post_cid){
            return false;
        }

        if ($full_image_url){
            //检测之前的字段是否存在
            $tothumb = $this->db->fetchObject($this->db->select('str_value')->from('table.fields')->where('cid = ? and name = ?',$post_cid,'tothumb'));
            if ($tothumb->str_value){
                $this->del_attachment($tothumb->str_value);
            }
            //
            $tpls    = ['tpl/tpl_1.png', 'tpl/tpl_2.png', 'tpl/tpl_3.png', 'tpl/tpl_4.png', 'tpl/tpl_5.png'];
            $now_tpl = BASE_THUMB_PLUGIN_PATH . $tpls[mt_rand(0, 4)];

            $editor = Grafika::createEditor();

            $tmp_file_name = md5($full_image_url . microtime()) . '.png';
            $tmp_file      = BASE_THUMB_PLUGIN_PATH . 'tmp/' . $tmp_file_name;
            if (!copy($full_image_url, $tmp_file)) {
                return false;
            }
            $in = $tmp_file;
            $date = new Typecho_Date();
            $path = Typecho_Common::url(defined('__TYPECHO_UPLOAD_DIR__') ? __TYPECHO_UPLOAD_DIR__ : self::UPLOAD_DIR,
                    defined('__TYPECHO_UPLOAD_ROOT_DIR__') ? __TYPECHO_UPLOAD_ROOT_DIR__ : __TYPECHO_ROOT_DIR__)
                . '/' . $date->year . '/' . $date->month;

            //创建上传目录
            if (!is_dir($path)) {
                if (!self::makeUploadDir($path)) {
                    return false;
                }
            }
            $out_path = $path. '/' . $tmp_file_name;
            $cut_type = $plugin_options->cut_type;

            // 第一种
            $editor->open($image1, $in);
            if ($cut_type == '0'){
                $editor->resizeFill($image1, 211, 125);
            }else {
                $editor->resizeExact($image1, 211, 125);
            }

            $editor->save($image1, BASE_THUMB_PLUGIN_PATH . 'tmp/tmp.png');
            $editor->open($image1, $now_tpl);
            $editor->open($image2, BASE_THUMB_PLUGIN_PATH . 'tmp/tmp.png');
            $editor->blend($image1, $image2, 'normal', 1, 'top-left', 45, 40);
            $editor->save($image1, $out_path);
            // 设置 meta
            unlink($tmp_file);

            $result = $this->build_meta($out_path);

            $rarr = $this->insert_attachment($result,$user,$post_cid);

            // 插入附件的cid
            $attach_cid = $rarr['cid'];
            if (!$attach_cid){
                return false;
            }
            // 设置 自定义 字段
            self::setField('tothumb','str',$attach_cid,$post_cid);
            $pos = strpos($this->attachment->url,'/usr/uploads');
            if ($pos ==false) return [];
            $afterFix = substr($this->attachment->url,$pos);
            $url = Typecho_Common::url($afterFix, Helper::options()->siteUrl);
            // 修改 缩略图字段
            $this->change_thumb_field($post_cid,$url);
            return $rarr;
        }
        return [];
    }
}