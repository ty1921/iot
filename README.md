
    
### # 中国电信物联网开放平台——WEB API（北向）SDK
———————————————————————————————————
其实已经有【Zeevin/Libiocm】包了，但我Git到本地以及服务器就是无法运行，始终报错Appid字符串未定义。

也曾联系作者寻求帮助，但由于“众所周知的”玄学因素，同样代码在作者那里正常，在我这里始终无法运行。

加之工期非常紧张，无奈之下只好重新造了个轮子。

———————————————————————————————————

**目录结构：**
./
│  api.php				示例参考demo
│  iot.php				核心sdk
│  README.md			
│
├─**logs**
│      deviceCredentials.log	创建设备日志
│      devices.log				设备上下行日志
│      login.log				平台登录日志
│      refreshTok.log			令牌刷新日志
│
**├─ssl**
│      outgoing.CertwithKey.pem	平台证书
│
**└─token**
|        access.token			平台令牌
|        refresh.token			平台刷新令牌

 **备注** 

- 暂无


