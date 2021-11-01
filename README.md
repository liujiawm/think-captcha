# thinkcaptcha
thinkphp6图片验证码

[https://liujiawm.github.io/](https://liujiawm.github.io/)

## 安装
php版本要求 >=7.1.0

composer
```
composer require phpu/think-captcha
```
如果项目未开启SESSION,则需要开启，开启方式参考[thinkphp6完全开发手册中杂项之SESSION](https://www.kancloud.cn/manual/thinkphp6_0/1037635)

## require
- mbstring
- gd
- topthink/framework ^6.0.0

### 注意：验证码不支持多字节字符

![](https://images.gitee.com/uploads/images/2021/0105/011626_7d5bbaca_1247621.png)

![](https://images.gitee.com/uploads/images/2021/0105/011636_1c8e7f1d_1247621.png)

![](https://github.com/liujiawm/think-captcha/raw/main/test1.png)

![](https://github.com/liujiawm/think-captcha/raw/main/test2.png)
## 使用方法

控制器文件use phpu\facade\ThinkCaptcha;

```
use phpu\facade\ThinkCaptcha;
```

### 输出验证码图片

验证码显示控制器
```
    public function captcha(){
        return ThinkCaptcha::printImg(); // png图片
        // return ThinkCaptcha::printBase64(); // Base64
    }
```
### 验证

验证码验证控制器
```
    public function check($code){
    
        if (false === ThinkCaptcha::check($code)){
            return response('验证码输入错误',200);
        }else{
            return response('验证码输入正确',200);
        }
    
    }
```
## 更多说明

如果创建验证码时使用独立的key

`
ThinkCaptcha::printImg('test') // 'test'是识别key,限数字和字母
`

那么验证时也需要传入同名key

`
ThinkCaptcha::check($code,'test') // 'test'是识别key,限数字和字母
`

默认验证完后不论成功还是错误都会删除验证码数据，如果验证完后不删除

`
ThinkCaptcha::check($code,'test',0) // 0表示不删除
`

或者，只有在验证成功后才删除

`
ThinkCaptcha::check($code,'test',1) // 1表示验证成功后才删除
`

当然，验证时也可以设置验证码过期时间，默认1800秒(30分钟内有效)

`
ThinkCaptcha::check($code,'test',2,3600) // 1小时内有效
`

## 独立配置

配置文件中提供独立配置，

如果无效果建议将配置文件config.php改名为`phpu_captcha.php`移入项目配置目录内！

使用`configure()`配置，参数是配置文件一级数组的索引，默认为`default`

例：

```
/**
 * 配置文件中提供独立配置，
 * 如果无效果建议将配置文件config.php改名为phpu_captcha.php移入项目配置目录内！
 */
    public function captcha(){
        return ThinkCaptcha::configure('sign')->printImg();
    }

```

配置文件

```
     return [
         // 默认配置
         'default' => [
             'char_preset' => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', // 预设字符集，不支持多字节字符
             'length'     => 5, // 验证码位数
             'width'      => 0, // 图片宽
             'height'     => 0, // 图片高
             'font_size'   => 48, // 验证码字体大小(px)
             'bg'         => [243, 251, 254], // 背景颜色
             'use_curve'   => true, // 是否画混淆曲线
             'use_noise'   => true, // 是否添加杂点
             'use_img_bg'   => true, // 是否使用背景图片
         ],
     
         // 独立配置
         'sign' => [
             'char_preset' => '0123456789', // 预设字符集
             'length'     => 4, // 验证码位数
             'width'      => 100, // 图片宽
             'height'     => 36, // 图片高
             'font_size'   => 24, // 验证码字体大小(px)
             'use_img_bg'   => false, // 是否使用背景图片
         ],
     ];
```