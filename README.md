# thinkcaptcha
thinkphp6图片验证码



## 安装

composer
```
composer require phpu/thinkcaptcha
```
如果项目未开启SESSION,则需要开启，开启方式参考thinkphp6完全开发手册中杂项之SESSION

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

        if(1 !== $re = $tc->check($code,'test')){
            return response($re,200);
        }

        return response('验证码正确',200);

    }
```

更多使用方式参考代码注释