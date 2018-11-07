<?php
/**
 * 189 IOT interface API
 *
 * @author  [ty1921] <[ty1921@gmail.com]>
 * @version [V1.0]   [debug mode]
 * @create  [2018-09-04]
 */


//=========================================================================================
//------------------------------------- debug code ----------------------------------------
//=========================================================================================

//display_errors
ini_set('display_errors', TRUE);

ini_set('display_startup_errors', TRUE);

//error_reporting(E_ALL);

error_reporting(E_ALL && ~E_NOTICE);

header('Content-type:text/html;charset=utf-8');

date_default_timezone_set("PRC");

//debug end================================================================================



//=========================================================================================
//------------------------------------- Class start ---------------------------------------
//=========================================================================================

class Iot
{
    public function __construct()
    {
        $this->host    = 'https://180.101.147.89:8743/iocm/app/';

        $this->appid   = 'GKOGbIA2kzeIS24Y3i318TLv8lka';      //appid

        $this->secret  = 'ZgI2LfdgEB1W9drfCOR9oW6t9dsa';    //secret

        $this->devkey  = '98dfe5aca5654d85bec5';    //密钥  

        $this->serviceId = 'CarLock';

        $this->debug   = 0;           //debug info show(1) or hide(0)

        $this->cert    = getcwd().'/ssl/outgoing.CertwithKey.pem';
    }


    /**
     * [1 login]
     * @return [type] [description]
     */
    public function login()
    {
        $api = 'sec/v1.1.0/login' ;

        $headers =  array( 'Content-Type:application/x-www-form-urlencoded' );

        $parm =  "appId="   . $this->appid
                ."&secret=" . $this->secret ;
      
        //开始发起curl请求
        $url = $this->host . $api ;

        $this->log( 'POST：' . $url );

        $this->log( $parm );

        $result = $this->fnCertCurl( $url, $parm, 1 );

        $this->log( $result );

        //log
        $this->fnLogFile( './logs/login.log', $result );

        $result = json_decode( $result, true );

        if( $result['accessToken'] )
        {
            //存token
            $this->fnLogFile( './token/access.token', $result['accessToken'], 1 );

            //存token
            $this->fnLogFile( './token/refresh.token', $result['refreshToken'], 1 );

            return $result['accessToken'];
        }
        else
        {
            return false;
        }
    }


    /**
     * [2 refreshToken description]
     * @return [type]               [description]
     */
    public function refreshToken()
    {
        $api = 'sec/v1.1.0/refreshToken';

        $refreshToken = file_get_contents( "./token/refresh.token");

        if( empty($refreshToken) )  return false;

        $headers =  array( 'Content-Type:application/x-www-form-urlencoded' );

        $parm_arr = array( 'appId' => $this->appid, 
                           'secret' => $this->secret, 
                           'refreshToken' => $refreshToken, 
                         );

        $parm = json_encode( $parm_arr, true );
      
        //开始发起curl请求
        $url = $this->host . $api ;

        $this->log( 'POST：' . $url );

        $this->log( $parm );

        $result = $this->fnCertCurl( $url, $parm );

        $this->log( $result );

        //log
        $this->fnLogFile( './logs/refreshTok.log', $result );

        $result = json_decode( $result, true );

        if( $result['accessToken'] && strlen( $result['accessToken'] ) >= 16 )
        {
            //存token
            $this->fnLogFile( '/token/refresh.token', $result['refreshToken'], 1 );

            return $result['accessToken'];
        }
        else
        {
            return false;
        }
    }



    /**
     * [3 deviceCredentials 注册设备]
     * @param  [type] $nodeId [设备编号]
     * @return [type]         [description]
     */
    public function deviceCredentials( $nodeId )
    {
        $api = 'reg/v1.1.0/deviceCredentials';

        $parm_arr = array( 'appId'      => $this->appid, 
                           'verifyCode' => $nodeId,   
                           'nodeId'     => $nodeId, 
                           'timeout'    => 0,
                         );

        $parm = json_encode( $parm_arr, true );
      
        //开始发起curl请求
        $url = $this->host . $api . '?appId=' . $this->appid ;

        $this->log( 'POST：' . $url );

        $this->log( $parm );

        $result = $this->fnCertCurl( $url, $parm );

        $this->log( $result );

        //log
        $this->fnLogFile( './logs/deviceCredentials.log', $result );

        $result = json_decode( $result, true );

        if( $result['deviceId'] && strlen( $result['deviceId'] ) >= 6 )
        {
            return $result['deviceId'];
        }
        else
        {
            return false;
        }
    }



    /**
     * [4 devices del ]
     * @param  [type] $nodeId [设备ID，可能是IMEI]
     * @return [type]         [description]
     */
    public function devices( $deviceId )
    {
        $api = 'dm/v1.4.0/devices/';

        $parm_arr = array( 'deviceId' => $deviceId, 
                           'cascade'  => 1
                         );

        $parm = json_encode( $parm_arr, true );
      
        //开始发起curl请求
        $url = $this->host . $api . $deviceId . '?appId=' . $this->appid . '&cascade=1' ;

        $this->log( 'POST：' . $url );

        $this->log( $parm );

        $result = $this->fnCertCurl( $url, $parm );

        $this->log( $result );

        //log
        $this->fnLogFile( './logs/devices.log', $result );

        $result = json_decode( $result, true );

        if( $result['deviceId'] && strlen( $result['deviceId'] ) >= 6 )
        {
            return $result['deviceId'];
        }
        else
        {
            return false;
        }
    }



    /**
     * [5 devices   查询设备激活状态 ]
     * @param  [type] $nodeId [设备ID]
     * @return [type]         [description]
     */
    public function devStatus( $deviceId )
    {
        Retry5:

        $api = 'reg/v1.1.0/deviceCredentials/';

        $parm_arr = array( 'deviceId' => $deviceId );

        $parm = json_encode( $parm_arr, true );
      
        //开始发起curl请求
        $url = $this->host . $api . $deviceId . '?appId=' . $this->appid ;

        $this->log( 'POST：' . $url );

        $this->log( $parm );

        $result = $this->fnCertCurl( $url, $parm );

        $this->log( $result );

        //log
        $this->fnLogFile( './logs/devices.log', $result );

        $result = json_decode( $result, true );

        if( $result['resultcode'] == '1010005' )
        {
            $this->login();

            goto Retry5;
        }

        if( $result['deviceId'] && strlen( $result['deviceId'] ) >= 6 )
        {
            return $result['deviceId'];
        }
        else
        {
            return false;
        }
    }
    

    /**
     * [6 multiStatus   批量查询设备状态 ]
     * @param  [type] $nodeId [设备ID]
     * @return [type]         [description]
     */
    public function multiStatus( $gateway='', $pageNo=0 )
    {
        Retry6:

        $api = 'dm/v1.4.0/devices';
      
        //开始发起curl请求
        $url = $this->host . $api . '?appId=' . $this->appid . '&pageNo=' . $pageNo ; 

        $this->log( 'POST：' . $url );

        $result = $this->fnCertCurl( $url, $parm );

        $this->log( $result );

        //log
        $this->fnLogFile( './logs/devices.log', $result );

        $result = json_decode( $result, true );

        if( $result['resultcode'] == '1010005' )
        {
            $this->login();

            goto Retry6;
        }

        return $result;
    }



    /**
     * [7.1 multiOrder   批量订阅设备数据 ]
     * @param  string $notifyType 
     *                  1. bindDevice（绑定设备）
                        2. deviceAdded（添加新设备）
                        3. deviceInfoChanged（设备信息变化）
                        4. deviceDataChanged（设备数据变化）    ★★★★★默认
                        5. deviceDatasChanged（设备数据批量变化）
                        6. deviceDeleted（删除设备）
                        7. messageConfirm（消息确认）
                        8. commandRsp（命令响应）
                        9. deviceEvent（设备事件）
                        10.serviceInfoChanged（服务信息变化）
                        11.ruleEvent（规则事件）
                        12.deviceModelAdded（添加设备模型）
                        13.deviceModelDeleted（删除设备模型）
                        14.deviceDesiredPropertiesModifyStatusChanged（设备影子状态变更）
     * @param  string  $type [默认1订阅  2取消订阅]                
     * @return [type]   [description]
     */
    public function multiOrder( $notifyType = 'deviceDataChanged', $type = 1 )
    {
        Retry71:

        $api = 'sub/v1.2.0/subscriptions';
      
        //开始发起curl请求
        $url = $this->host . $api ;

        $this->log( 'POST：' . $url );

        if( $type == 1 )
        {
            //默认：订阅
            $parm_arr = array( 'appId'       => $this->appid,
                               'notifyType'  => $notifyType,   
                               'callbackUrl' => 'https://www.oneh5.com:443/thq/LOCK/backend_api/189/callback.php' //订阅回调地址
                             );

            $parm = json_encode( $parm_arr, true );

            $this->log( $parm );
        }
        else
        {
            //取消订阅
            $url = $url . "?appId={$this->appid}&notifyType={$notifyType}&callbackUrl={$callbackUrl}" ;

            $this->log( 'POST：' . $url );
        }

        $result = $this->fnCertCurl( $url, $parm );

        $this->log( $result );

        //log
        $this->fnLogFile( './logs/devices.log', $result );

        $result = json_decode( $result, true );

        if( $result['resultcode'] == '1010005' )
        {
            $this->login();

            goto Retry71;
        }

        return $result;
    }

    /**
     * [7.2 multiOrderPush   推送：批量订阅设备数据 ]
     * @param  string $notifyType [deviceDataChanged]
     * @return [type]   [description]
     */
   /* public function multiOrderPush( $deviceId, $notifyType = 'deviceDataChanged' )
    {
        Retry72:

        $api = 'sub/v1.2.0/subscriptions';
      
        //开始发起curl请求
        $url = $this->host . $api ;

        $this->log( 'POST：' . $url );

        $parm_arr = array( 'appId'       => $this->appid,
                           'notifyType'  => $notifyType,  
                           'deviceId'    => $deviceId,    
                           'gatewayId'   => $deviceId,   
                           'service '    => array(  'serviceId'  => $this->serviceId, 
                                                    'serviceTyp' => $method, 
                                                    'data'       => array(  'action'  =>'heart', 
                                                                            'gateway' => $deviceId, 
                                                                            'data'       => $command,
                                                                            'eventTime'  => $command
                                                                         ), 
                                                    'eventTime'  => $command
                                                 ), 
                           'callbackUrl' => 'https://www.oneh5.com:443/thq/LOCK/backend_api/189/callback.php' //订阅回调地址
                         );

        $parm = json_encode( $parm_arr, true );

        $this->log( $parm );

        $result = $this->fnCertCurl( $url, $parm );

        $this->log( $result );

        //log
        $this->fnLogFile( './logs/devices.log', $result );

        $result = json_decode( $result, true );

        if( $result['resultcode'] == '1010005' )
        {
            $this->login();

            goto Retry72;
        }

        return $result;
    }*/


    /**
     * [7.3 multiOrderCheck   批量c=查询订阅 ]
     * @param  string $notifyType [deviceDataChanged]
     * @return [type]   [description]
     */
    public function multiOrderCheck( $notifyType='deviceDataChanged' )
    {
        Retry73:

        $api = 'sub/v1.2.0/subscriptions';
      
        //开始发起curl请求
        $url = $this->host . $api . "?appId={$this->appid}&notifyType={$notifyType}";

        $this->log( 'POST：' . $url );

/*        $parm_arr = array( 'appId'      => $this->appid,
                           'notifyType' => $notifyType
                         );

        $parm = json_encode( $parm_arr, true );

        $this->log( $parm );*/

        $result = $this->fnCertCurl( $url, $parm );

        $this->log( $result );

        //log
        $this->fnLogFile( './logs/devices.log', $result );

        $result = json_decode( $result, true );

        if( $result['resultcode'] == '1010005' )
        {
            $this->login();

            goto Retry73;
        }

        return $result;
    }


    /**
     * [8 设备的历史数据 devHistory?deviceId={deviceId}&gatewayId={gatewayId}&appId={appId}]
     * @param  [type] $nodeId [设备ID]
     * @return [type]         [description]
     */
    public function devHistory( $gatewayId, $deviceId )
    {
        Retry8:

        $api = 'data/v1.2.0/deviceDataHistory';
      
        //开始发起curl请求
        $url = $this->host . $api . "?gatewayId={$gatewayId}&appId={$this->appid}&deviceId={$deviceId}" ;

        $this->log( 'GET：' . $url );

        $result = $this->fnCertCurl( $url, $parm );

        $this->log( $result );

        //log
        $this->fnLogFile( './logs/devices.log', $result );

        $result = json_decode( $result, true );

        if( $result['resultcode'] == '1010005' )
        {
            $this->login();

            goto Retry8;
        }

        return $result;
    }



    /**
     * [9 命令下发-创建设备命令 devCmd ]
     * @param  [type] $nodeId [设备ID]
     * @return [type]         [description]
     */
    public function devCmd( $deviceId, $command, $method='LockControl' )
    {
        Retry9:

        $api = 'cmd/v1.4.0/deviceCommands'; 
        
        //开始发起curl请求
        $url = $this->host . $api ;

        $this->log( 'POST:' . $url );


        $parm_arr = array( //'appId'       => $this->appid, 
                           'deviceId'    => $deviceId,  
                           'command'     => array(  'serviceId' => $this->serviceId, 
                                                    'method'    => $method,
                                                    'paras'     => $command
                                                 ), 
                           'expireTime'    => 0, 
                           // 'maxRetransmit' => 1, 
                           'callbackUrl'   => 'https://www.oneh5.com:443/thq/LOCK/backend_api/189/callback.php' //订阅回调地址
                         );

        $parm = json_encode( $parm_arr, true );

        $this->log( $parm_arr );

        //$this->log( $parm );


        $result = $this->fnCertCurl( $url, $parm );

        $this->log( "res:" . $result );


        //log
        $this->fnLogFile( './logs/devices.log', json_encode($command) . '|' . $result );

        $result_arr = json_decode( $result, true );

        if( $result_arr['resultcode'] == '1010005' )
        {
            $this->login();

            goto Retry9;
        }

        return $result;
    }



    //=========================================================================================
    //------------------------------ comm function section ------------------------------------
    //=========================================================================================

    /**
     * [fnCertCurl http curl ]
     * @param  [type]  $url          [host]
     * @param  string  $post         [post data array]
     * @param  string  $ssl          [CA]
     * @return [type]                [curl reslut]
     */
    public function fnCertCurl( $url, $parm, $auth = 0 )
    {   
        //$url = 'http://192.168.14.133/LOCK/backend_api/189//1.php';

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);

        //curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)');

        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);

        curl_setopt($curl, CURLOPT_AUTOREFERER, 1);


        //需要头部带上app_key等参数
        if( $auth )
        {
            $headers = array( 'Content-type' => 'application/x-www-form-urlencoded; charset=utf-8' );

            //print_r('=========================================');
        }
        else
        {
            $accessToken = file_get_contents( "./token/access.token");

            if( empty($accessToken) )  return false;

            //设置header信息
  
            $header = array(
                                'Content-type:application/json',
                                'app_key: ' .$this->appid, 
                                'Authorization: Bearer ' . $accessToken
                            );

            //print_r( $header );
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, $header );  //设置头信息的地方  


        if( count($parm) > 0 )
        {
            curl_setopt($curl, CURLOPT_POST, 1);

            curl_setopt($curl, CURLOPT_POSTFIELDS, $parm );
        }

        //curl_setopt($curl, CURLOPT_HEADER, 1); //返回response头部信息
        //curl_setopt($curl, CURLINFO_HEADER_OUT, true); //TRUE 允许查看请求header

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);// 信任任何证书，不是CA机构颁布的也没关系
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);// 检查证书中是否设置域名，如果不想验证也可设为0
        curl_setopt($curl, CURLOPT_VERBOSE, 0); //debug模式，方便出错调试
        curl_setopt($curl, CURLOPT_SSLCERT, $this->cert ); //证书路径
        curl_setopt($curl, CURLOPT_SSLCERTPASSWD, 'IoM@1234'); //client证书密码
        curl_setopt($curl, CURLOPT_CERTINFO, 1);


        curl_setopt($curl, CURLOPT_TIMEOUT, 5 );

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);


        $data = curl_exec($curl);


        //echo curl_getinfo($curl, CURLINFO_HEADER_OUT); //官方文档描述是“发送请求的字符串”，其实就是请求的header。这个就是直接查看请求header，因为上面允许查看

        //$ty = curl_getinfo($curl);

        //$this->log( $data );

        if (curl_errno($curl)) 
        {
            echo "★★★ curl_error:".curl_error($curl);
        }

        curl_close($curl);

        return $data;
    }



    public function log( $msg )
    {
        if( !$this->debug ) return;

        echo "<pre>[".date('Y-m-d H:i:s') . "] ";

        print_r( $msg );

        echo "</pre>";
    }


    /**
     * [logFile 文件日志记录]
     * @param  [type] $filename [文件名]
     * @param  [type] $msg      [待存入的日志内容]
     * @return [null]           
     */
    function fnLogFile( $filename, $msg, $cover=0 )
    {
        if( $cover > 0 ) 
        {
            $fd = fopen( $this->path . $filename,"w" ); //覆盖

            $str = $msg;
        }
        else
        {
            $fd = fopen( $filename,"a" );

            $str = "\n[".date("Y/m/d H:i:s",time())."] ".$msg ;
        }

        fwrite( $fd, $str );

        fclose( $fd );
    }
}