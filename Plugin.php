<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * ThumbOptimizer 是由即刻学术开发的缩略图优化插件，生成成功会 添加 tothumb 字段，内容为附件 id
 * <br> 插件限制生成头图尺寸 300x200
 * 
 * @package ThumbOptimizer
 * @author gogobody
 * @version 1.0.0
 * @link http://www.ijkxs.com
 */
define('BASE_THUMB_PLUGIN_PATH',Helper::options()->pluginDir('ThumbOptimizer').'/ThumbOptimizer/');
require_once BASE_THUMB_PLUGIN_PATH . 'vendor/autoload.php';

require_once 'TUtils.php';

use Grafika\Grafika;

class ThumbOptimizer_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {

        Typecho_Plugin::factory('Widget_Contents_Post_Edit')->finishPublish = array('ThumbOptimizer_Plugin', 'post_update');
        Typecho_Plugin::factory("Widget_Contents_Post_Edit")->getDefaultFieldItems = array("ThumbOptimizer_Plugin", "UTThemeFields");


        // test
        Helper::addRoute('testfunc','/testfunc','ThumbOptimizer_Action','test_func');


    }
    
    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate(){
        Helper::removeRoute('testfunc');
    }
    
    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        ?>
        <div class="j-setting-contain">
        <link href="<?php echo Helper::options()->rootUrl ?>/usr/plugins/ThumbOptimizer/assets/css/joe.setting.min.css" rel="stylesheet" type="text/css" />
        <div>
            <div class="j-aside">
                <div class="logo">ThumbOptimizer</div>
                <ul class="j-setting-tab">
                    <li data-current="j-setting-notice">插件公告</li>
                    <li data-current="j-setting-global">全局设置</li>
                </ul>
                <?php require_once('Backups.php'); ?>
            </div>
        </div>
        <span id="j-version" style="display: none;">1.0.0</span>
        <div class="j-setting-notice">暂不支持在线检测更新。唯一发布地址<a href="https://www.ijkxs.com/archives/185.html">即刻学术</a><br><strong>请勿随意传播，谢谢合作。</strong><br>如遇到问题请访问<a href="https://www.ijkxs.com">即刻学术</a>反馈。</div>

        <script src="<?php echo Helper::options()->rootUrl ?>/usr/plugins/ThumbOptimizer/assets/js/joe.setting.min.js"></script>
        <?

        $cut_type = new Typecho_Widget_Helper_Form_Element_Radio(
            'cut_type',
            array(
                '0' => '居中裁剪',
                '1' => '强制缩放'
            ),
            '0',
            '裁剪类型',
            ''
        );
        $cut_type->setAttribute('class', 'j-setting-content j-setting-global');
        $form->addInput($cut_type);

        $t_username = new Typecho_Widget_Helper_Form_Element_Text(
            't_username',
            null,
            null,
            '用户名',
            '用于上传附加的用户，可以设置自己的，也可以专门建一个，要有编辑者以上权限'
        );
        $t_username->setAttribute('class', 'j-setting-content j-setting-global');
        $form->addInput($t_username);

        $t_password = new Typecho_Widget_Helper_Form_Element_Text(
            't_password',
            null,
            null,
            '用户密码',
            '用于上传附加的用户，可以设置自己的，也可以专门建一个，要有编辑者以上权限'
        );
        $t_password->setAttribute('class', 'j-setting-content j-setting-global');
        $form->addInput($t_password);

        $replace_thumb = new Typecho_Widget_Helper_Form_Element_Radio(
                'replace_thumb',
                array(
                    'off' => '关闭',
                    'thumb' => 'thumb缩略图字段，如 joe 主题,spimes主题',
                    'img'=> 'img字段,如spzac主题',
                    'udefine' => '自定义(下一栏)'
                ),'off','缩略图字段','替换主题缩略图字段'
        );
        $replace_thumb->setAttribute('class', 'j-setting-content j-setting-global');
        $form->addInput($replace_thumb);

        $udefine_thumb = new Typecho_Widget_Helper_Form_Element_Text(
                'udefine_thumb',
            null,
            null,
            '自定义缩略图字段','需要在上一栏选自定义字段'
        );
        $udefine_thumb->setAttribute('class', 'j-setting-content j-setting-global');
        $form->addInput($udefine_thumb);
    }

//    public static function configHandle($config, $is_init)
//    {
//        if (!$is_init){
//            $name = $config['t_username'];
//            $pswd = $config['t_password'];
//            if (!Typecho_Widget::widget('Widget_User')->login($name,$pswd)){
//                throw new Typecho_Widget_Exception('用户密码验证失败');
//            }
//        }
//    }
    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}
    
    /**
     * 插件实现方法
     * 
     * @access public
     * @return string
     */
    /* 随机图片 */
    public static function GetThumbnail($text,$thumb = null)
    {
        $pattern = '/\<img.*?src\=\"(.*?)\"[^>]*>/i';
        $patternMD = '/\!\[.*?\]\((http(s)?:\/\/.*?(jpg|jpeg|gif|png|webp))/i';
        $patternMDfoot = '/\[.*?\]:\s*(http(s)?:\/\/.*?(jpg|jpeg|gif|png|webp))/i';
        $t = preg_match_all($pattern, $text, $thumbUrl);
        if ($thumb) {
            $img = $thumb;
        } elseif ($t) {
            $img = $thumbUrl[1][0];
        } elseif (preg_match_all($patternMD, $text, $thumbUrl)) {
            $img = $thumbUrl[1][0];
        } elseif (preg_match_all($patternMDfoot, $text, $thumbUrl)) {
            $img = $thumbUrl[1][0];
        } else{
            $img = null;
        }
        return $img;
    }
    /**
     * 创建上传路径
     *
     * @access private
     * @param string $path 路径
     * @return boolean
     */
    public static function makeUploadDir($path)
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
    public static function curl_post($url,$post_data)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        $output = curl_exec($ch);
        $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        return  $output;
    }

    public static function post_update($contents, $class)
    {
        $db = Typecho_Db::get();
        $use_utoptimizer = $db->fetchObject($db->select('str_value')->from('table.fields')->where('cid = ? and name = ?',$class->cid,'use_utoptimizer'));

        if ($use_utoptimizer->str_value == '1'){
            $post_cid = $class->cid;
            $full_image_url = self::GetThumbnail($contents['text']);
            $utils = new TUtils(new Typecho_Request(),new Typecho_Response());
            $res = $utils->make_thumb($full_image_url,$post_cid);
        }

    }

    // 添加自定义字段
    public static function UTThemeFields($layout)
    {
        $use_utoptimizer = new Typecho_Widget_Helper_Form_Element_Radio(
            'use_utoptimizer',
            array(
                '0' => '关闭',
                '1' => '启用'
            ),
            '0',
            '头图优化',
            '本篇文章是否开启 optimizer 头图优化，默认关闭'
        );
        $layout->addItem($use_utoptimizer);
    }

}
