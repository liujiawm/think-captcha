<?php
/**
 * 图片验证码
 * 适用于thinkphp6
 * @author liujiawm (liujiawm@163.com)
 * @homepage www.phpu.cn
 * @link https://liujiawm.github.io
 * @license Apache-2.0
 */
declare (strict_types = 1);

namespace phpu;

use think\Config;
use think\Session;
use think\Response;

class ThinkCaptcha
{
    /**
     * 当前版本号
     */
    const VERSION = '2.0.2';

    /**
     * 默认配置
     * @var array
     */
    private $config = [
        'char_preset' => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', // 预设字符集，不支持多字节字符
        'length'     => 5, // 验证码位数
        'width'      => 0, // 图片宽
        'height'     => 0, // 图片高
        'font_size'   => 48, // 验证码字体大小(px)
        'bg'         => [243, 251, 254], // 背景颜色
        'use_curve'   => true, // 是否画混淆曲线
        'use_noise'   => true, // 是否添加杂点
        'use_img_bg'   => true, // 是否使用背景图片

    ];


    /**
     * 配置
     * @var Config
     */
    private $thinkConfig;

    /**
     * @var Session
     */
    private $thinkSession;


    /**
     * 待选字体名称，文件路径 assets/fonts
     * @var string[]
     */
    private $ttfs = ['1.ttf','2.ttf','3.ttf','4.ttf','5.ttf','6.ttf','7.ttf'];


    /**
     * 待选背景图，文件路径 assets/bgs
     * @var string[]
     */
    private $bgs = ['1.jpg','2.jpg','3.jpg','4.jpg','5.jpg','6.jpg','7.jpg','8.jpg'];


    /**
     * 验证码字体颜色
     * @var int[]
     */
    private $textColor = [];

    /**
     * 图片标识符
     * @var resource
     */
    private $im = null;

    /**
     * 图片宽
     * @var int
     */
    protected $imageWidth = 0;

    /**
     * 图片高
     * @var int
     */
    protected $imageHeight = 0;


    /**
     * ThinkCaptcha constructor.
     * @param Config $config
     * @param Session $session
     */
    public function __construct(Config $config, Session $session){
        $this->thinkConfig = $config;
        $this->thinkSession = $session;
    }

    /**
     * 当前版本号
     * @return string
     */
    public function version(): string{
        return self::VERSION;
    }

    /**
     * 指定配置
     *
     * @param string|null $configName
     * @return $this
     */
    public function configure(string $configName = null){
        $this->setConfig($configName);
        return $this;
    }

    /**
     * 指定配置
     * @param string|null $configName
     */
    private function setConfig(string $configName = null){
        if($this->thinkConfig->has('phpu_captcha.default')){
            $this->config = $this->thinkConfig->get('phpu_captcha.default', []);
        }

        if (!is_null($configName) && $configName !== 'default') {
            if($this->thinkConfig->has('phpu_captcha.' . $configName)){
                $config = $this->verifyConfig($this->thinkConfig->get('phpu_captcha.'.$configName, []));
                $this->config = array_merge($this->config, array_change_key_case($config));
            }
        }
    }

    /**
     * 取当前配置
     *
     * @return array
     */
    public function getConfig(){
        return $this->config;
    }

    /**
     * 验证
     * @param string $code 传入的验证码字符串
     * @param string $key 独立验证码key
     * @param int $reset 验证后是否重置,0不重置，1成功后重置，2无论成功与否都重置
     * @param int $expire 有效时间(秒)
     * @return bool
     */
    public function check(string $code,string $key='',int $reset=2,int $expire=1800): bool{
        if ($key !== '' && preg_match('/[^0-9a-zA-Z]/',$key)){
            return false;
        }

        $sessionValueName = 'phpu_captcha_data_'.$key;

        if (!$this->thinkSession->has($sessionValueName)) {
            return false; // 不存在
        }

        $currentTime = time();

        $captchaData = $this->thinkSession->get($sessionValueName);

        if ($currentTime - $expire > $captchaData['time']){
            $this->thinkSession->delete($sessionValueName);
            return false; // 超时
        }

        $code = mb_strtolower($code, 'UTF-8');

        if ($reset === 2){
            $this->thinkSession->delete($sessionValueName);
        }
        if (password_verify($code, $captchaData['hash']) === true){
            if ($reset === 1){
                $this->thinkSession->delete($sessionValueName);
            }
            return true; // 正确
        }

        return false; // 错误
    }


    /**
     * 生成图片
     *
     * @param string $key 独立验证码key
     * @return false|resource
     */
    public function create($key=''){
        if ($key !== '' && preg_match('/[^0-9a-zA-Z]/',$key)){
            return false;
        }

        $generator = $this->generate($key);

        $this->imageWidth = $this->config['width'];
        $this->imageHeight = $this->config['height'];

        // 图片宽(px)
        if(!$this->imageWidth){
            $this->imageWidth = intval(ceil($this->config['length'] * $this->config['font_size'] + ($this->config['length']+1) * 10));
        }
        // 图片高(px)
        if(!$this->imageHeight){
            $this->imageHeight = intval(ceil($this->config['font_size'] + 20));
        }

        // 建立一幅 $this->imageW x $this->imageH 的图像
        if (function_exists('imagecreatetruecolor')) {
            $this->im = imagecreatetruecolor((int)$this->imageWidth, (int)$this->imageHeight);
        }else{
            $this->im = imagecreate($this->imageWidth, $this->imageHeight);
            // 添加背景后8位验证码图片在加杂点时颜色代码会出现不被允许的情况，所以要不不加背景要不不加杂点
            $this->config['use_img_bg'] = false;
        }

        // 背景色
        $background_color = imagecolorallocate($this->im, $this->config['bg'][0], $this->config['bg'][1], $this->config['bg'][2]);
        // 画一个方形并填背景色
        imagefill($this->im, 0, 0, $background_color);

        // 验证码字体随机颜色
        for ($i = 0; $i < $this->config['length']; $i++) {
            $this->textColor[] = imagecolorallocate($this->im, mt_rand(1, 150), mt_rand(1, 150), mt_rand(1, 150));
        }

        // 验证码使用随机字体
        $ttfPath = __DIR__ . '/../assets/fonts/';
        $ttfs = [];
        if (empty($this->ttfs)){
            $dir  = dir($ttfPath);
            while (false !== ($file = $dir->read())) {
                if ('.' != $file[0] && substr($file, -4) == '.ttf') {
                    $ttfs[] = $file;
                }
            }
            $dir->close();
        }else{
            $ttfs = $this->ttfs;
        }
        $ttf = $ttfs[array_rand($ttfs)];
        $fontttf = $ttfPath . $ttf;


        // 添加背景图
        if ($this->config['use_img_bg']) {
            $this->background();
        }

        // 绘杂点
        if ($this->config['use_noise']) {
            $this->writeNoise();
        }

        // 绘验证码
        $texts = preg_split('//u', $generator['char'], -1, PREG_SPLIT_NO_EMPTY); // 验证码
        $mx = intval(ceil(($this->imageWidth - $this->config['font_size'] * $this->config['length']) / ($this->config['length']+1) * 1.2));
        $my = intval(ceil(($this->imageHeight - $this->config['font_size']) / 2));
        $mx = $mx > 0 ? $mx : 0;
        $my = $my > 0 ? $my : 5; // 上边距为负数时，修改上边距随机最大数为5
        $x = $mx;
        foreach ($texts as $i => $char) {
            $y     = intval(ceil(($this->config['font_size'] + mt_rand(0, $my))  * 1.2));
            $angle = mt_rand(-10, 10);

            imagettftext($this->im, $this->config['font_size'], $angle, $x, $y, $this->textColor[$i], $fontttf, $char);
            $x     += $this->config['font_size'] + mt_rand(0, $mx);
        }

        // 绘干扰线
        if ($this->config['use_curve']) {
            $this->writeCurve();
        }

        return $this->im;
    }

    public function printImg($key=''){
        return $this->printPng($key);
    }
    /**
     * png格式显示验证码
     * @param string $key 独立验证码key
     * @return mixed
     */
    public function printPng($key=''){

        if(false === $this->create($key)){
            return false;
        }

        ob_start();
        imagepng($this->im);
        $printContent = ob_get_clean();
        ob_end_clean();
        imagedestroy($this->im);
        return Response::create($printContent, 'html', 200)
            ->header(['Content-Length' => strlen($printContent)])
            ->contentType('image/png');

    }

    /**
     * base64输出验证码
     *
     * @param string $key 独立验证码key
     * @return mixed
     */
    public function printBase64($key=''){

        if(false === $this->create($key)){
            return false;
        }

        ob_start();
        imagepng($this->im);
        $printContent = base64_encode(ob_get_clean());
        ob_end_clean();
        imagedestroy($this->im);
        $printContent = 'data:image/png;base64,'.$printContent;
        return Response::create($printContent, 'html', 200)
            ->header(['Content-Length' => strlen($printContent)]);
    }

    /**
     * 创建验证码
     * @param string $key 独立验证码key
     * @return array ['char' => $char,'hash' => $hash] char生成的验证码字符，hash处理后的验证码哈希
     */
    private function generate($key=''): array{
        $char = '';

        //$characters = str_split($this->config['char_preset']);
        $characters = preg_split('//u', $this->config['char_preset'], -1, PREG_SPLIT_NO_EMPTY);

        for ($i = 0; $i < $this->config['length']; $i++) {
            $char .= $characters[mt_rand(0, count($characters) - 1)];
        }

        $captcha_data = mb_strtolower($char, 'UTF-8');

        $hash = password_hash($captcha_data, PASSWORD_BCRYPT);

        $sessionValueName = 'phpu_captcha_data_'.$key;

        $this->thinkSession->set($sessionValueName,['hash'=>$hash,'time'=>time()]);

        return [
            'char' => $char,
            'hash'   => $hash,
        ];
    }

    /**
     * 加图像背景图
     */
    private function background(): void{
        $path = __DIR__ . '/../assets/bgs/';

        $bgs = [];
        if(empty($this->bgs)){
            $dir  = dir($path);
            while (false !== ($file = $dir->read())) {
                if ('.' != $file[0] && substr($file, -4) == '.jpg') {
                    $bgs[] = $file;
                }
            }
            $dir->close();
        }else{
            $bgs = $this->bgs;
        }


        $gb = $path . $bgs[array_rand($bgs)];

        list($width, $height) = @getimagesize($gb);
        $width = intval(ceil($width));
        $height = intval(ceil($height));
        // Resample
        $bgImage = @imagecreatefromjpeg($gb);
        @imagecopyresampled($this->im, $bgImage, 0, 0, 0, 0, $this->imageWidth, $this->imageHeight, $width, $height);
        @imagedestroy($bgImage);

    }

    /**
     * 画杂点
     * 往图片上写不同颜色的字母或数字
     */
    private function writeNoise(): void{
        $codeSet = '2345678abcdefhijkmnpqrstuvwxyz';
        $m = intval(ceil($this->imageWidth / 20));
        for ($i = 0; $i < $m; $i++) {
            //杂点颜色
            //$noiseColor = imagecolorallocate($this->im, mt_rand(150, 225), mt_rand(150, 225), mt_rand(150, 225));
            $noiseColor = $this->textColor[array_rand($this->textColor,1)];
            for ($j = 0; $j < 3; $j++) {
                // 绘杂点
                imagestring($this->im, mt_rand(1, 5), mt_rand(-10, $this->imageWidth), mt_rand(-10, $this->imageHeight), $codeSet[mt_rand(0, 29)], $noiseColor);
            }
        }
    }

    /**
     * 画一条由两条连在一起构成的随机正弦函数曲线作干扰线(你可以改成更帅的曲线函数)
     */
    private function writeCurve(): void{
        $px = $py = 0;

        // 线条颜色
        $lineColor = $this->textColor[array_rand($this->textColor,1)];

        // 曲线前部分
        $A = mt_rand(1, (int)ceil($this->imageHeight / 2)); // 振幅
        $b = mt_rand((int)floor($this->imageHeight / 4 * -1), (int)ceil($this->imageHeight / 4)); // Y轴方向偏移量
        $f = mt_rand((int)floor($this->imageHeight / 4 * -1), (int)ceil($this->imageHeight / 4)); // X轴方向偏移量
        $T = mt_rand($this->imageHeight, $this->imageWidth * 2); // 周期
        $w = (2 * M_PI) / $T;

        $px1 = 0; // 曲线横坐标起始位置
        $px2 = mt_rand((int)ceil($this->imageWidth / 2), (int)ceil($this->imageWidth * 0.8)); // 曲线横坐标结束位置

        for ($px = $px1; $px <= $px2; $px = $px + 1) {
            if (0 != $w) {
                $py = $A * sin($w * $px + $f) + $b + $this->imageHeight / 2; // y = Asin(ωx+φ) + b
                $i = (int)round($this->config['font_size'] / 6);
                while ($i > 0) {
                    imagesetpixel($this->im, intval($px + $i), intval($py + $i), $lineColor); // 这里(while)循环画像素点比imagettftext和imagestring用字体大小一次画出（不用这while循环）性能要好很多
                    $i--;
                }
            }
        }

        // 曲线后部分
        $A   = mt_rand(1, (int)ceil($this->imageHeight / 2)); // 振幅
        $f   = mt_rand((int)floor($this->imageHeight / 4 * -1), (int)ceil($this->imageHeight / 4)); // X轴方向偏移量
        $T   = mt_rand($this->imageHeight, $this->imageWidth * 2); // 周期
        $w   = (2 * M_PI) / $T;
        $b   = $py - $A * sin($w * $px + $f) - $this->imageHeight / 2;
        $px1 = $px2;
        $px2 = $this->imageWidth;

        for ($px = $px1; $px <= $px2; $px = $px + 1) {
            if (0 != $w) {
                $py = $A * sin($w * $px + $f) + $b + $this->imageHeight / 2; // y = Asin(ωx+φ) + b
                $i  = (int) ($this->config['font_size'] / 5);
                while ($i > 0) {
                    imagesetpixel($this->im, intval($px + $i), intval($py + $i), $lineColor);
                    $i--;
                }
            }
        }
    }


    /**
     * 处理配置项的值使其合法
     * @param array $config
     * @return array
     */
    private function verifyConfig(array $config): array{

        if (isset($config['char_preset'])){
            $config['char_preset'] = trim($config['char_preset']);
            if (empty($config['char_preset'])){
                unset($config['char_preset']);
            }
        }
        if(isset($config['length'])){
            $config['length'] = intval($config['length']);
            if ($config['length'] < 1){
                unset($config['length']);
            }
        }
        if(isset($config['width'])){
            $config['width'] = intval($config['width']);
        }
        if(isset($config['height'])){
            $config['height'] = intval($config['height']);
        }

        if(isset($config['bg'])){
            if(is_string($config['bg'])){
                if(strlen($config['bg']) > 7 || strlen($config['bg']) < 3){
                    unset($config['bg']);
                }
                $config['bg'] = self::hex2rgb($config['bg']);
            }else if(!is_array($config['bg']) || count($config['bg']) != 3){
                unset($config['bg']);
            }else if(intval($config['bg'][0]) > 255 || intval($config['bg'][1]) > 255 || intval($config['bg'][2]) > 255){
                unset($config['bg']);
            }
            $config['bg'] = [intval($config['bg'][0]),intval($config['bg'][1]),intval($config['bg'][2])];
        }

        if(isset($config['use_img_bg']) && !is_bool($config['use_img_bg'])){
            unset($config['use_img_bg']);
        }
        if(isset($config['font_size'])){
            $config['font_size'] = intval($config['font_size']);
            if ($config['font_size'] < 1){
                unset($config['font_size']);
            }
        }
        if(isset($config['use_curve']) && !is_bool($config['use_curve'])){
            unset($config['use_curve']);
        }
        if(isset($config['use_noise']) && !is_bool($config['use_noise'])){
            unset($config['use_noise']);
        }

        return $config;
    }

    /**
     * 将十六进制的颜色代码转为RGB
     * @param string $hexColor 十六进制颜色代码
     * @return int[] RGB颜色数组[r,g,b]
     */
    private static function hex2rgb(string $hexColor):array {
        if ($hexColor[0]=='#') $hexColor = substr($hexColor,1);
        $hexColor = preg_replace("/[^0-9A-Fa-f]/", '', $hexColor);
        if (strlen($hexColor)==3){
            $hexColor = $hexColor[0].$hexColor[0].$hexColor[1].$hexColor[1].$hexColor[2].$hexColor[2];
        }
        $int = hexdec($hexColor);

        return [0xFF & ($int >> 0x10), 0xFF & ($int >> 0x8), 0xFF & $int];
    }
}