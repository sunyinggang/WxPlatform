<?php
class Index{

    //微信公众号接口配置
    public function testToken()
    {
        $nonce = $_GET['nonce'];
        $timestamp = $_GET['timestamp'];
        $signature = $_GET['signature'];
        $echostr = $_GET['echostr'];
        $token = '';//自定义的token
        $arr = array($nonce, $timestamp, $token);
        sort($arr);
        $str = sha1(implode($arr));
        if ($str == $signature && $echostr) {
            echo $echostr;
            exit;
        } else {
            $this->reponseMsg();
        }
    }
    //接收事件推送并回复
    public function reponseMsg()
    {
        //必有部分
        $postArr = $GLOBALS['HTTP_RAW_POST_DATA'];  //获取事件的XML字符串
        $postObj = simplexml_load_string($postArr); //将XML字符串转为对象
        //可选部分

        //根据不同的事件推送返回文本内容
        $type = '';//事件类型（如：关注subscribe）
        $content = '';//自定义文本消息
        $this->eventResTxt($postObj,$type,$content);

        //根据用户输入，自动回复文本消息
        $content='';//自定义文本消息
        $this->textResTxt($postObj,$content);

        //根据点击菜单返回图文消息
        $arr = array();//参考下面的图文消息格式
        $this->clickResImgTxt($postObj,'',$arr); //通过传递不同的key，个性化设置不同按钮返回不同的图文消息
    }

    /**
     * 发送Http请求
     * @param $url           //需要请求的url地址
     * @param string $type  请求类型，默认为get请求
     * @param string $res
     * @param string $arr   post请求时需要添加的请求参数
     * @return array|int|mixed|object
     */
    function http_curl($url,$type='get',$res='json',$arr=''){
        //1.初始化curl
        $ch = curl_init();
        //2.设置curl的参数
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);//https请求 不验证证书 其实只用这个就可以
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,false);//https请求 不严重HOST
        if($type == 'post'){
            curl_setopt($ch,CURLOPT_POST,1);//确定是post请求
            curl_setopt($ch,CURLOPT_POSTFIELDS,$arr);//添加post请求参数
        }
        //3.采集
        $output = curl_exec($ch);
        //4.关闭
        if($res == 'json'){
            if(curl_errno($ch)){//这里的if是针对微信公众号请求添加的
                return curl_errno($ch);//公众号中返回信息错误码为零时，请求成功；其他则为请求失败，返回错误信息
            }else{
                return json_decode($output,true);//如果是json格式，转为php数组
            }
        }
        curl_close($ch);
    }

    /**
     * 获取access_token
     * 方法优点：获取到access_token永远都在有效期之内
     * @return mixed
     */
    public function getWxAccessToken(){
        //通过if判断，决定是否更新access_token
        if(session('access_token') && session('expire_time') > time()){
            return session('access_token');
        }else{
            $appid = '';//填写自己的appid和appsecret，也可以写成配置项，通过变量传递过来
            $appsecret =  '';
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$appid."&secret=".$appsecret;
            $res = $this->http_curl($url,'get','json');//调用上面定义的发送Http请求方法
            $access_token = $res['access_token'];
            session('access_token',$access_token);
            session('expire_time',time()+7000);
            return $access_token;
        }
    }

    /**
     * 创建自定义菜单
     * 使用方法：通过浏览器请求此方法的url，代表创建自定义菜单
     * 该方法下面存在var_dump，主要是方便查看创建结果及状态
     * 菜单结构按钮类型--参考官方文档：https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1421141013
     * 这里列举常用的两个：
     * 1、click：点击推事件用户点击click类型按钮后，微信服务器会通过消息接口推送消息类型为event的结构给开发者（参考消息接口指南），
     *    并且带上按钮中开发者填写的  key值，开发者可以通过自定义的key值与用户进行交互；
     * 2、view：跳转URL用户点击view类型按钮后，微信客户端将会打开开发者在按钮中填写的网页URL，可与网页授权获取
     *     用户基本信息接口结合，获得用户基本信息。
     */
    public function definedItem(){
        header('content-type:text/html;charset=utf-8');
        $access_token = $this->getWxAccessToken();//使用上面的方法获取access_token
        $url = "https://api.weixin.qq.com/cgi-bin/menu/create?access_token=".$access_token;
        //自定义自己的菜单结构
        $postArr = array(
            'button' => array(
                array(),//一级菜单
                array(
                    array()//二级菜单
                )
            ),
        );
        //打印创建的菜单及状态
        echo '<hr />';
        var_dump($postArr);
        echo '<hr />';
        echo $postJson = urldecode( json_encode($postArr));
        $res = $this->http_curl($url,'post','json',$postJson);
        echo "<hr />";
        var_dump($res);
    }

    /**
     *  网页授权请求方法
     *  与下一个函数方法getUserOpenId组合，实现完整的网页授权
     */
    public function getBaseInfo()
    {
        $appid = '';//填写自己的appid，也可以写成配置项，通过变量传递过来
        $redirect_uri = urlencode("http://.../.../getUserOpenId");//定义回调函数，请求地址根据个人情况修改
        $url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=" . $appid . "&redirect_uri=" . $redirect_uri . "&response_type=code&scope=snsapi_base&state=123#wechat_redirect";
        header('location:' . $url);
    }

    /**
     *  网页授权回调函数
     *  主要用于获取用户信息及实现页面跳转
     */
    public function getUserOpenId()
    {
        $appid = "";//填写自己的appid和appsecret，也可以写成配置项，通过变量传递过来
        $appsecret = '';
        $code = $_GET['code'];
        $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=" . $appid . "&secret=" . $appsecret . "&code=" . $code . "&grant_type=authorization_code";
        $res = $this->http_curl($url, 'get');//通过url发送网页授权请求，获取到用户的基本信息
        $keyid = $res['openid'];//这里仅取到用户的唯一标识openid做测试
        session('key', $keyid);//保存用户的openid，方便使用
        $this->redirect('');//书写需要跳转到的页面
    }

    /**
     *  展示图文消息（可实现单图文或多图文）
     * @param $postObj    //接收事件推送中获取到的对象
     * @param $arr        //图文消息内容
     * 图文消息内容格式
     * ----单图文----
     *    $arr = array(
     *            array(
     *              'title'=>'',
     *              'description' => '',
     *              'picUrl'=>'',  //支持JPG、PNG格式
     *              'url'=>'',
     *             ),
     *          )
     * ----多图文----
     *    $arr = array(
     *            array(
     *              'title'=>'',
     *              'description' => '',
     *              'picUrl'=>'',  //支持JPG、PNG格式
     *              'url'=>'',
     *             ),
     *            array(
     *              'title'=>'',
     *              'description' => '',
     *              'picUrl'=>'',  //支持JPG、PNG格式
     *              'url'=>'',
     *             ),
     *          )
     */
    public function imgTxtMessage($postObj, $arr)
    {
        $toUser = $postObj->FromUserName;
        $fromUser = $postObj->ToUserName;
        $time = time();
        $msgType = 'news';
        //每一项不能有空格！！！！！！！！！！
        $template = "<xml>
                            <ToUserName><![CDATA[%s]]></ToUserName>
                            <FromUserName><![CDATA[%s]]></FromUserName>
                            <CreateTime>%s</CreateTime>
                            <MsgType><![CDATA[%s]]></MsgType>
                            <ArticleCount>" . count($arr) . "</ArticleCount>
                            <Articles>";
        foreach ($arr as $k => $v) {
            $template .= "<item>
                            <Title><![CDATA[" . $v['title'] . "]]></Title>
                            <Description><![CDATA[" . $v['description'] . "]]></Description>
                            <PicUrl><![CDATA[" . $v['picUrl'] . "]]></PicUrl>
                            <Url><![CDATA[" . $v['url'] . "]]></Url>
                            </item>";
        }
        $template .= "</Articles>
                         </xml>";
        echo sprintf($template, $toUser, $fromUser, $time, $msgType);
    }

    public function clickResImgTxt($postObj,$key,$arr)
    {
        if(strtolower($postObj->Event) == 'click'){
            if(strtolower($postObj->EventKey == $key)){
                $this->imgTxtMessage($postObj, $arr);
            }
        }
    }

    /**
     * 根据不同的事件推送返回文本内容
     * @param $postObj
     * @param $type
     * @param $content
     */
    public function eventResTxt($postObj,$type,$content){
        if(strtolower($postObj->MsgType) == 'event'){
            if(strtolower($postObj->Event == $type)){
                $this->printTextTemp($postObj,$content);
            }
        }
    }

    /**
     *  根据用户输入，自定义返回信息
     * @param $postObj
     * @param string $content
     */
    public function textResTxt($postObj,$content=''){
        if (strtolower($postObj->MsgType) == 'text') {  //判断接收的事件为文本
            if($content == ''){
                switch(trim($postObj->Content)){  //根据接收的文本内容不同，设置不同的返回信息
                    case 'X':
                        $content = '';
                        break;
                    case 'XX':
                        $content = '';
                        break;
                    case 'XXX':
                        $content = '';
                        break;
                }
            }
            $this->printTextTemp($postObj,$content);
        }
    }

    /**
     *  使用文本内容返回的模板
     * @param $postObj
     * @param $content
     */
    public function printTextTemp($postObj,$content){
        $template = "<xml>
                         <ToUserName><![CDATA[%s]]></ToUserName>
                         <FromUserName><![CDATA[%s]]></FromUserName>
                         <CreateTime>%s</CreateTime>
                         <MsgType><![CDATA[%s]]></MsgType>
                         <Content><![CDATA[%s]]></Content>
                         </xml>";
        $toUser = $postObj->FromUserName;
        $fromUser = $postObj->ToUserName;
        $time = time();
        $msgType = 'text';
        echo sprintf($template, $toUser, $fromUser, $time, $msgType,$content);
    }

}