# thinkcaptcha
thinkphp6图片验证码



## 安装

composer
```
composer require phpu/thinkcaptcha
```
如果项目未开启SESSION,则需要开启，开启方式参考thinkphp6完全开发手册中杂项之SESSION

图片生成使用gd扩展库

## 输出验证码图片

验证码显示控制器
```
    public function captcha(){
        $config = [
            'length'     => 4, // 验证码位数
        ];

        $tc = new ThinkCaptcha($config);

        return $tc->create();
    }
```
## 验证
验证码验证控制器
```
    public function check($code){

        $tc = new ThinkCaptcha();

        if(1 !== $re = $tc->check($code)){
            return response($re,200);
        }

        return response('验证码正确',200);

    }
```

如果创建验证码时使用独立的key
`
create('test')
`
那么验证时也需要传入同名key
`
check($code,'test')
`

```
    // 创建验证码的控制器
    public function captcha(){

        $tc = new ThinkCaptcha();

        return $tc->create('userlogin');
    }

    // 验证例子，实际应用中不需要单独创建该控制器，
    public function check(){

        $tc = new ThinkCaptcha();

        if(1 !== $re = $tc->check($code,'userlogin')){
            // 错误处理
            // -2验证码不存在，-1验证码过期，0错误
        }else{
            // 1正确
        }
    }

```

`check()`默认无论正确与否都会在验证后删除该验证码，如果需要改变，需要给`check()`
第三个参数`check(string $code,string $key='',int $reset=2)`
$reset值有三种，分别是：0不重置，1成功后删除，2无论成功与否都删除


更多使用方式参考代码注释

```
     /**
     * 默认配置
     * @var array 
     */
    private $config = [
        'charPreset' => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', // 预设字符集
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
     * ThinkCaptcha constructor.
     * @param null|array $config 配置
     */
    public function __construct($config = null)

     /**
     * 输出图片并写入SESSION
     * @param string $key 独立验证码key
     * @return Response 输出png图片
     */
    public function create($key=''): Response



     /**
     * 验证
     * @param string $code 传入的验证码字符串
     * @param string $key 独立验证码key
     * @param int $reset 验证后是否重置,0不重置，1成功后重置，2无论成功与否都重置
     * @param int $expire 有效时间(秒)
     * @return int (-2不存在，-1超时，0错误，1正确)
     */
    public function check(string $code,string $key='',int $reset=2,int $expire=1800): int
```