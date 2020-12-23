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

use think\facade\Session;
use think\Response;

class ThinkCaptcha
{
    /**
     * 当前版本号
     */
    const VERSION = '1.0.1';

    /**
     * 默认配置
     * @var array
     */
    private $config = [
        'charPreset' => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', // 预设字符集，不支持多字节字符
        'length'     => 5, // 验证码位数
        'width'      => 0, // 图片宽
        'height'     => 0, // 图片高
        'fontSize'   => 48, // 验证码字体大小(px)
        'bg'         => [243, 251, 254], // 背景颜色
        'useCurve'   => true, // 是否画混淆曲线
        'useNoise'   => true, // 是否添加杂点
        'useImgBg'   => true, // 是否使用背景图片

    ];

    /**
     * 待选字体名称，文件路径 assets/ttfs
     * @var string[]
     */
    private $ttfs = ['1.ttf','2.ttf','3.ttf','4.ttf'];


    /**
     * 待选背景图，文件路径 assets/bgs
     * @var string[]
     */
    private $bgs = ['1.jpg','2.jpg','3.jpg','4.jpg','5.jpg','6.jpg','7.jpg','8.jpg'];


    /**
     * 验证码字体颜色
     * @var int
     */
    private $color = 0;

    /**
     * 图片标识符
     * @var resource
     */
    private $im = null;

    /**
     * 图片宽
     * @var int
     */
    protected $imageW = 0;

    /**
     * 图片高
     * @var int
     */
    protected $imageH = 0;

    /**
     * 验证码图片类型
     * @var string
     */
    protected $imageType = 'png';


    /**
     * ThinkCaptcha constructor.
     * @param null|array $config 配置
     */
    public function __construct($config = null){
        if(!is_null($config) && is_array($config)){
            $config = self::verifyConfig($config);
            $this->config = array_merge($this->config, $config);
        }
    }

    public function type(string $type){
        $types = ['jpg','gif','png','base64'];
        if (in_array($type, $types)){
            $this->imageType = $type;
        }
        return $this;
    }


    /**
     * @param null $name 键名，如果用数组方式设置，该值为配置数组
     * @param null|bool|string|int|array|mixed $val 在用键名单独设置一个配置值时，该参数是配置的值
     * @return $this
     */
    public function setConfig($name = null,$val=null){
        if(is_array($name) && is_null($val)){
            $config = self::verifyConfig($name);
            $this->config = array_merge($this->config, $config);
        }else if(is_string($name) && array_key_exists($name, $this->config)){
            $this->config[$name] = $val;
        }
        return $this;
    }

    /**
     * 取配置值
     * @param null|string $name
     * @return array|mixed|string
     */
    public function getConfig($name=null){
        if(is_null($name)){
            return $this->config;
        }

        if(array_key_exists($name, $this->config)){
            return $this->config[$name];
        }

        return 'Nothing';
    }

    /**
     * 当前版本号
     * @return string
     */
    public function version(): string{
        return self::VERSION;
    }

    /**
     * 验证
     * @param string $code 传入的验证码字符串
     * @param string $key 独立验证码key
     * @param int $reset 验证后是否重置,0不重置，1成功后重置，2无论成功与否都重置
     * @param int $expire 有效时间(秒)
     * @return int (-2不存在，-1超时，0错误，1正确)
     */
    public function check(string $code,string $key='',int $reset=2,int $expire=1800): int{
        $name = 'captcha_data_'.$key;

        if (!Session::has($name)) {
            return -2; // 不存在
        }

        $currentTime = time();

        $captchaData = Session::get($name);

        if ($currentTime - $expire > $captchaData['time']){
            Session::delete($name);
            return -1; // 超时
        }

        $code = mb_strtolower($code, 'UTF-8');

        if ($reset === 2){
            Session::delete($name);
        }
        if (false === password_verify($code, $captchaData['value'])){
            return 0; // 错误
        }else{
            if ($reset === 1){
                Session::delete($name);
            }
            return 1; // 正确
        }
    }

    /**
     * 输出图片并写入SESSION
     * @param string $key 独立验证码key
     * @return Response 输出png图片
     */
    public function create($key=''): Response{
        $generator = $this->generate($key);

        $this->imageW = $this->config['width'];
        $this->imageH = $this->config['height'];

        // 图片宽(px)
        if(!$this->imageW){
            $this->imageW = intval(ceil($this->config['length'] * $this->config['fontSize'] + ($this->config['length']+1) * 10));
        }
        // 图片高(px)
        if(!$this->imageH){
            $this->imageH = intval(ceil($this->config['fontSize'] + 20));
        }

        // 建立一幅 $this->imageW x $this->imageH 的图像
        $this->im = imagecreate($this->imageW, $this->imageH);
        //$this->im = imagecreatetruecolor((int)$this->imageW, (int)$this->imageH);
        // 设置背景
        imagecolorallocate($this->im, $this->config['bg'][0], $this->config['bg'][1], $this->config['bg'][2]);

        // 验证码字体随机颜色
        $this->color = imagecolorallocate($this->im, mt_rand(1, 150), mt_rand(1, 150), mt_rand(1, 150));

        // 验证码使用随机字体
        $ttfPath = __DIR__ . '/../assets/ttfs/';


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



        if ($this->config['useNoise']) {
            // 绘杂点
            $this->writeNoise();
        }
        if ($this->config['useCurve']) {
            // 绘干扰线
            $this->writeCurve();
        }

        if ($this->config['useImgBg']) {
            $this->background();
        }

        // 绘验证码
        $text = str_split($generator['char']); // 验证码
        $mx = intval(ceil(($this->imageW - $this->config['fontSize'] * $this->config['length']) / ($this->config['length']+1) * 1.2));
        $my = intval(ceil(($this->imageH - $this->config['fontSize']) / 2));
        $x = $mx;
        foreach ($text as $index => $char) {

            $y     = $this->config['fontSize'] + mt_rand(0, $my);
            $angle = mt_rand(-10, 10);

            imagettftext($this->im, $this->config['fontSize'], $angle, $x, $y, $this->color, $fontttf, $char);
            $x     += $this->config['fontSize'] + mt_rand(0, $mx);
        }

        ob_start();
        // 输出图像
        switch ($this->imageType){
            case 'jpg':
            case 'jpeg':
                imagejpeg($this->im);
                $printContent = ob_get_clean();
                imagedestroy($this->im);
                return Response::create($printContent, 'html', 200)
                    ->header(['Content-Length' => strlen($printContent)])
                    ->contentType('image/jpeg');
                break;
            case 'gif':
                imagegif($this->im);
                $printContent = ob_get_clean();
                imagedestroy($this->im);
                return Response::create($printContent, 'html', 200)
                    ->header(['Content-Length' => strlen($printContent)])
                    ->contentType('image/gif');
                break;
            case 'base64':
                imagepng($this->im);
                $printContent = base64_encode(ob_get_clean());
                imagedestroy($this->im);
                $printContent = 'data:image/png;base64,'.$printContent;
                return Response::create($printContent, 'html', 200)
                    ->header(['Content-Length' => strlen($printContent)]);
                break;
            case 'png':
            default:
                imagepng($this->im);
                $printContent = ob_get_clean();
                imagedestroy($this->im);
                return Response::create($printContent, 'html', 200)
                    ->header(['Content-Length' => strlen($printContent)])
                    ->contentType('image/png');
        }
        

        

    }

    /**
     * 创建验证码
     * @param string $key 独立验证码key
     * @return array ['char' => $char,'value' => $hash] char生成的验证码字符，value处理后的验证码哈希
     */
    protected function generate($key=''): array{
        $char = '';

        $characters = str_split($this->config['charPreset']);

        for ($i = 0; $i < $this->config['length']; $i++) {
            $char .= $characters[mt_rand(0, count($characters) - 1)];
        }

        $captcha_data = mb_strtolower($char, 'UTF-8');

        $hash = password_hash($captcha_data, PASSWORD_BCRYPT);

        $name = 'captcha_data_'.$key;

        Session::set($name,['value'=>$hash,'time'=>time()]);

        return [
            'char' => $char,
            'value'   => $hash,
        ];
    }

    /**
     * 加图像背景图
     */
    protected function background(): void{
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
        @imagecopyresampled($this->im, $bgImage, 0, 0, 0, 0, $this->imageW, $this->imageH, $width, $height);
        @imagedestroy($bgImage);
    }

    /**
     * 画杂点
     * 往图片上写不同颜色的字母或数字
     */
    protected function writeNoise(): void{
        $codeSet = '2345678abcdefhijkmnpqrstuvwxyz';
        $m = intval(ceil($this->imageW / 20));
        for ($i = 0; $i < $m; $i++) {
            //杂点颜色
            $noiseColor = imagecolorallocate($this->im, mt_rand(150, 225), mt_rand(150, 225), mt_rand(150, 225));
            for ($j = 0; $j < 2; $j++) {
                // 绘杂点
                imagestring($this->im, mt_rand(1, 5), mt_rand(-10, $this->imageW), mt_rand(-10, $this->imageH), $codeSet[mt_rand(0, 29)], $noiseColor);
            }
        }
    }

    /**
     * 画一条由两条连在一起构成的随机正弦函数曲线作干扰线(你可以改成更帅的曲线函数)
     */
    protected function writeCurve(): void{
        $px = $py = 0;

        // 曲线前部分
        $A = mt_rand(1, (int)ceil($this->imageH / 2)); // 振幅
        $b = mt_rand((int)floor($this->imageH / 4 * -1), (int)ceil($this->imageH / 4)); // Y轴方向偏移量
        $f = mt_rand((int)floor($this->imageH / 4 * -1), (int)ceil($this->imageH / 4)); // X轴方向偏移量
        $T = mt_rand($this->imageH, $this->imageW * 2); // 周期
        $w = (2 * M_PI) / $T;

        $px1 = 0; // 曲线横坐标起始位置
        $px2 = mt_rand((int)ceil($this->imageW / 2), (int)ceil($this->imageW * 0.8)); // 曲线横坐标结束位置

        for ($px = $px1; $px <= $px2; $px = $px + 1) {
            if (0 != $w) {
                $py = $A * sin($w * $px + $f) + $b + $this->imageH / 2; // y = Asin(ωx+φ) + b
                $i = (int)round($this->config['fontSize'] / 6);
                while ($i > 0) {
                    imagesetpixel($this->im, intval($px + $i), intval($py + $i), $this->color); // 这里(while)循环画像素点比imagettftext和imagestring用字体大小一次画出（不用这while循环）性能要好很多
                    $i--;
                }
            }
        }

        // 曲线后部分
        $A   = mt_rand(1, (int)ceil($this->imageH / 2)); // 振幅
        $f   = mt_rand((int)floor($this->imageH / 4 * -1), (int)ceil($this->imageH / 4)); // X轴方向偏移量
        $T   = mt_rand($this->imageH, $this->imageW * 2); // 周期
        $w   = (2 * M_PI) / $T;
        $b   = $py - $A * sin($w * $px + $f) - $this->imageH / 2;
        $px1 = $px2;
        $px2 = $this->imageW;

        for ($px = $px1; $px <= $px2; $px = $px + 1) {
            if (0 != $w) {
                $py = $A * sin($w * $px + $f) + $b + $this->imageH / 2; // y = Asin(ωx+φ) + b
                $i  = (int) ($this->config['fontSize'] / 5);
                while ($i > 0) {
                    imagesetpixel($this->im, intval($px + $i), intval($py + $i), $this->color);
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
    private static function verifyConfig(array $config): array{

        if (isset($config['charPreset'])){
            $config['charPreset'] = trim($config['charPreset']);
            if (empty($config['charPreset'])){
                unset($config['charPreset']);
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

        if(isset($config['useImgBg']) && !is_bool($config['useImgBg'])){
            unset($config['useImgBg']);
        }
        if(isset($config['fontSize'])){
            $config['fontSize'] = intval($config['fontSize']);
            if ($config['fontSize'] < 1){
                unset($config['fontSize']);
            }
        }
        if(isset($config['useCurve']) && !is_bool($config['useCurve'])){
            unset($config['useCurve']);
        }
        if(isset($config['useNoise']) && !is_bool($config['useNoise'])){
            unset($config['useNoise']);
        }

        return $config;
    }



}