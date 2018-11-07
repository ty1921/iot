<?php
/**
 * 189 IOT API
 *
 * @author  [ty1921] <[ty1921@gmail.com]>
 * @version [V1.0]   [debug mode]
 * @create  [2018-09-17]
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



$device_id = $_REQUEST['device_id'];

$lock_no = $_REQUEST['lock_no'];

$action = (int)$_REQUEST['action'];

if( empty($device_id) || empty($lock_no) )
{
	exit('parm lost.');
}


require_once 'iot.php';

$Iot = new Iot();

//获取令牌
//$res = $Iot->login();

//注册设备
//$res = $Iot->deviceCredentials('test000011');

//订阅设备的信息变更，每个设备都需要订阅
//$res = $Iot->multiOrderPush( '99dd8139-b239-41e9-a95f-4299a6ecf000' );

//删除设备
//$res = $Iot->devices('7edcee35-5ad7-43b0-ab7e-afcde608ff75');

//查询设备激活状态
//$res = $Iot->devStatus('7edcee35-5ad7-43b0-ab7e-afcde608ff75');

//批量查询
//$res = $Iot->multiStatus( '', 0 );


//批量订阅数据变动消息
//$res = $Iot->multiOrder();

//批量取消订阅
//$res = $Iot->multiOrder( 'deviceEvent', 2 );


//批量查询数据变动的订阅情况
//$res = $Iot->multiOrderCheck();



//查询设备历史信息，网关和设备号
//$res = $Iot->devHistory( '99dd8139-b239-41e9-a95f-4299a6ecf000', '99dd8139-b239-41e9-a95f-4299a6ecf000' );

//下发指令,method=LockSet
/*$res = $Iot->devCmd(    '99dd8139-b239-41e9-a95f-4299a6ecf000', 
                        array( 'value'  => 1, 'set'    => 1, 'device' => 'test00000001' ),
                        'LockSet'
                     );*/

//下发指令,默认method=LockControl
$res = $Iot->devCmd( $device_id, array(   'device' => $lock_no,
                                          'action' => $action
                                       ) 
                     );


echo $res;

