<?php
/**
 * 电信IOT callback 异步回调处理
 * 
 * @version [ty1921]
 * @date    [2018-9-17 14:40:04]
 */


require '../comm/head_api.php';

$datas  = file_get_contents("php://input");

//=================================================================================
//参数检查
if( empty($datas) )
{
    $res = array ('code'=>9000,'msg'=>'parameter lost.','time'=>time() );

    exit( json_encode($res) );
}
else
{
	//记录日志
    fileLog( "======================================================= \n get iot's data:" . $datas );
}


$datas_arr = json_decode( $datas, true );

require '../comm/conn.php';

$last_time = time();


//开始处理状态上报
switch ( $datas_arr['notifyType'] ) 
{   

//设备数据改变
case 'deviceDataChanged':

    $action = $datas_arr['service']['data']['action']; //heart心跳

    //=====================================================================================
    //1 心跳：网关状态改变
    if( $action == 'heart' )
    {
    	$gateway_id = $datas_arr['service']['data']['gateway']; 

        $deviceId   = $datas_arr['deviceId']; 

        //1.1 查询网关的状态，并进行更新
        $sql = "  SELECT `gateway`,`last_time` 
                  FROM `gateway` 
                  WHERE gateway= '{$gateway_id}' 
               ";

        $stmt = $pdo->query( $sql ); 
        
        $row_count = $stmt->rowCount(); 

        //网关不存在，新加入
        if( count( $row_count ) <= 0 )
        {
            $sql = "  INSERT INTO `gateway`( 
                                              `client_id`,
                                              `gateway`,
                                              `last_time`
                                            )
                           VALUES (
                                    '{$deviceId}',
                                    '{$gateway_id}',
                                    '{$last_time}'
                                  )
                   ";

            $insert_id = $pdo->query( $sql );

            if( $insert_id > 0 )
            {
              $msg = " +++> new gateway {$gateway_id} joined. \n";
            }
            else
            {
              $msg = " ---> gateway {$gateway_id} join failed! \n";
            }
        }
        else
        {
            //网关存在，更新旧网关的时间 状态
            $sql = "  UPDATE `gateway` 
                         SET `client_id` = '{$deviceId}',
                             `status`    = 1,
                             `last_time` = '{$last_time}'
                       WHERE `gateway`   = '{$gateway_id}'
                   ";

            $row_count = $pdo->query( $sql );

            //echo "update:" . $row_count . "\n";

            if( $row_count > 0 )
            {
              $msg = " ===> gateway {$gateway_id} update success \n";
            }
            else
            {
              $msg = " ---> gateway {$gateway_id} update failed! \n";
            }
        }



        //1.2 检查是否有脱网设备
            
        $offline = (int)$datas_arr['service']['data']['offline']; ;

        if( $offline > 0 )
        {
            //遍历数组，修改其标志位，并记录日志
            
            $off_arr = $datas_arr['service']['data']['deviceid'];

            $locks = '';

            for( $i=1; $i<=$offline; $i++ )
            {
                if( !empty($off_arr[ $i ]) )
                {
                    $locks .= "'" . $off_arr[ $i ] . "',";
                }
            }

            $locks = substr( $locks, 0, -1 );

            //更新离线标志，status=2:故障(黄色)
            $sql_p = " UPDATE `parking` 
                          SET `status` = 2
                        WHERE `lock_no` in ( {$locks} )
                   ";

            $row_count2 = $pdo->exec( $sql_p );

            //echo "update:" . $row_count . '|' . $sql_p . "\n";

            if( $row_count2 > 0 )
            {
                $msg .= "\n===> [{$sql_p}] offline success. \n";
            }
            else
            {
                $msg .= "\n---> [{$sql_p}] offline failed! \n";
            }
        }


        fileLog( $msg );
        

        exit;


        break;

    }
    //=====================================================================================
    //2 status上报的处理
    elseif( $action == 'status' )
    {
        $lock_no = $datas_arr['service']['data']['status']['id'];

        $inout   = (int)$datas_arr['service']['data']['status']['inout'];

        $act     = (int)$datas_arr['service']['data']['status']['act']; 

        $power   = (int)$datas_arr['service']['data']['status']['power']; 

        $gateway = $datas_arr['service']['data']['gateway'];


        //2.2 新增：更新网关心跳时间
        $sql0 = "   UPDATE `gateway` 
                       SET `status` = 1,
                           `last_time` = {$last_time}
                     WHERE `gateway` = '{$gateway}'
                 ";

        //echo $sql0;

        $row_count = $pdo->query( $sql0 );



        //2.3 抓取当前锁的状态
        $sql = "  SELECT `id`,`power`,`status`,`gateway`
                    FROM `parking` 
                   WHERE lock_no = '{$lock_no}' 
               ";

        $stmt = $pdo->query( $sql ); 
        
        $row_count = $stmt->rowCount(); 


        //锁不存在
        if( $row_count <= 0 )
        {
            $msg = "锁[{$lock_no}]不存在";

            fileLog( $msg );

            $res = array ('code'=>9012, 'msg'=> $msg, 'time'=>time() ); 

            exit( json_encode($res) ); 
        }


        $lock_arr = $stmt->fetch(PDO::FETCH_ASSOC);

        //print_r($lock_arr);

        $status = (int)$lock_arr['status'];          
       

        //日志记录
        $sql_log = " INSERT INTO `log_status`(`parking_id`, `add_time`, `online`, `power`, `act`, `inout`) 
                          VALUES ( {$lock_arr['id']}, {$last_time}, 1, {$power}, {$act}, {$inout} )
                           ";

        //echo $sql_log;

        $pdo->query( $sql_log ); 


        //开始拼接SQL，无变化则只更新时间戳
        
        $condition = '';

        if( $power != $lock_arr['power'] )
        {
            $condition .= " ,`power` = {$power} ";
        }

        if( $gateway != $lock_arr['gateway'] )
        {
            $condition .= " ,`gateway` = '{$gateway}' ";
        }


        //状态判断，1:正常(绿色 无车竖起00)  2:故障(黄色 有车竖起10) 3:占用(红色 有车放倒11) 4:休眠(灰色) 
        //         5:占用(无车放倒01)

        if( $inout == 0 && $act == 0 )
        {
            //正常，已关闭(无车竖起00)
            $condition .= " ,`status` = 1, `bind_user` = '' ";

            //此处还需要考虑停车计费的联动更新等等，2018-3-17 18:23:02
            //
            //
            //
            //
            //

            //变为正常（关闭）
            $sql = " UPDATE `log_lock` 
                        SET `status` = 4,
                            `change_time` = {$last_time},
                            `end_time` = {$last_time}
                      WHERE `parking_id` = {$lock_arr['id']} 
                        AND `end_time` = 0
                   ";

            $pdo->query( $sql ); 
            
        }
        elseif( $inout == 0 && $act == 1 )
        { 
            //占用(无车放倒01)
            $condition .= " ,`status` = 5 ";

            //从正常变为占用无车
            //if( $status == 5 || $status == 3 )
            //{
                $sql = " UPDATE `log_lock` 
                            SET `status` = 5, 
                                `change_time` = {$last_time} 
                          WHERE `parking_id` = {$lock_arr['id']} 
                            AND `end_time` = 0
                       ";

                $pdo->query( $sql ); 
            //}
        }
        elseif( $inout == 1 && $act == 0 ) 
        { 
            //故障(有车竖起10)
            $condition .= " ,`status` = 2 "; 
        } 
        elseif( $inout == 1 && $act == 1 )
        {
            //占用(有车放倒11)
            $condition .= " ,`status` = 3 ";

            $sql = " UPDATE `log_lock` 
                        SET `status` = 3, 
                            `change_time` = {$last_time} 
                      WHERE `parking_id` = {$lock_arr['id']} 
                        AND `end_time` = 0 
                   "; 

            $pdo->query( $sql ); 
        }


        //self::fLog( 'heart', date('H:i:s') . 'recv: ' . "[{$sql}] | {$res}\n\n" );


        //更新车锁的状态
        $sql_lock = " UPDATE `parking` 
                         SET `heart_time` = {$last_time},
                             `change_time` = {$last_time}
                             {$condition} 
                       WHERE `lock_no` = '{$lock_no}' 
                   ";

        fileLog( 'status: ' . $sql_lock );

        $row_count2 = $pdo->query( $sql_lock );

        //echo "update:" . $row_count2 . '|' . $sql . "\n";

        if( $row_count2 > 0 )
        {
            $msg = "===> {$lock_no} [{$condition}] report status success. \n";

            echo '{"status":"1"}';
        }
        else
        {
            $msg = "===> {$lock_no} [{$condition}] report status failed! \n";

            echo '{"status":"0"}';
        }


        fileLog( 'status over: ' . $msg );

        //上报结束
        break;

    }
    //3 app蓝牙开锁的处理
    elseif( $action == 'app' )
    {
        //获取参数
        //$gateway_id = $datas_arr['service']['data']['gateway']; 

        $data       = $datas_arr['service']['data']['data']; 

        $lock_no    = substr( $data, 0, 12 );

        $mobilphone = substr( $data, 12, 11 );

        $open       = substr( $data, 23, 2 );

        // ack的定义（1位）： 
        // 0：关 （车位锁执行指令）
        // 1：开 （车位锁执行指令）
        // 2：无权限 
        // 3：欠费 
        // 4：车位使用数量达到上限 
        // 5：休眠 
        // 6：故障 其他故障，或有车竖起 inout=1 ack=0 
        // 7：正常 无车竖起 inout=0 ack=0 
        // 8：占用 无车放倒 inout=0 ack=1 【新增】 
        // 9：占用 有车放倒 inout=1 ack=1 
        // x：用户不存在 
        // y：锁不存在或网关离线
        // z：状态6(紫色)，表示车锁开启5分钟后依然没有状态变更，可能损坏





        //用户存在且有权限，取锁的相关信息
        $sql = "
                  SELECT g.`client_id`, p.`id` AS parking_id, p.`status`, p.`power`, p.`bind_company`,p.`bind_user`
                    FROM `gateway` g,`parking` p, `company` c
                   WHERE g.`gateway` = p.`gateway` 
                     AND p.`bind_company` = c.`company_id`
                     AND g.`status` = 1 
                     AND p.`lock_no` ='{$lock_no}' 
               ";

        $stmt = $pdo->query( $sql ); 
        
        $cnt = $stmt->rowCount();  

        if( $cnt <= 0 )
        {
            $res = "[{$lock_no}] & device_id not exist."; 

            fileLog( $res );

            break;
        }



        $lock_arr = $stmt->fetch(PDO::FETCH_ASSOC);


        $device_id = $lock_arr['client_id'];



        //引用IOT方法类，下发指令或返回数据用
        require_once 'iot.php';

        $Iot = new Iot();


        //判断是否有开启当前锁的权限
        $sql_auth = "
                        SELECT u.`company_id`,u.`status`,u.`lock_limit`,c.`status` AS c_status,count(p.`id`) AS locked
                          FROM `users` u
                     LEFT JOIN company c ON u.`company_id` = c.`company_id` 
                     LEFT JOIN `parking` p ON u.`mobilphone` = p.`bind_user` AND p.`status` IN (3,5,8)
                         WHERE 1 = 1
                           AND u.`mobilphone` = '{$mobilphone}' 
                         GROUP BY u.`company_id`,u.`status`,u.`lock_limit`,c_status
                    "; 

        $stmt = $pdo->query( $sql_auth ); 
        
        $cnt = $stmt->rowCount(); 

        if( $cnt <= 0 )
        {
            //下发指令,默认method=LockControl 
            $res = $Iot->devCmd( $device_id, array(   'device' => $lock_no,
                                                      'ack'    => 'x'
                                                   ),
                                 'LockAppAck' 
                               );

            fileLog( $res );

            break;
        }


        $auth_arr = $stmt->fetch(PDO::FETCH_ASSOC);


        //1.1 单位状态或用户状态检查
        if( (int)$auth_arr['c_status'] == 3 || (int)$auth_arr['status'] == 3 || (int)$auth_arr['status'] == 4 )
        {
            $res = "[{$mobilphone}] 's status error."; 

            //下发指令,默认method=LockControl 
            $res = $Iot->devCmd( $device_id, array(   'device' => $lock_no,
                                                      'ack'    => '2'
                                                   ),
                                 'LockAppAck' 
                               );
            break;
        }





        //用户只能关闭自己打开的锁
        if( $open == 0 && $lock_arr['bind_user'] != $mobilphone) 
        { 
            $ack = '2';

            $res = "[{$mobilphone}] other’s lock can not be closed."; 
        }

        //正常单位的用户，用户开锁数量超过限制
        if( $open == 1 && (int)$auth_arr['c_status'] == 0 && (int)$auth_arr['lock_limit'] <= (int)$auth_arr['locked'] ) 
        { 
            $ack = '4';

            $res = "[{$mobilphone}] locked(".(int)$auth_arr['locked'].") >= limit(" . (int)$auth_arr['lock_limit'] .")" ; 
        } 
        else
        {
            //开锁数量未超限

            //无权限或未通过审核
            if( $auth_arr['status'] != 1 ) 
            { 
                $ack = '2';

                $res = "[{$mobilphone}] permission denied."; 
            } 
            else 
            { 

                $status     = (int)$lock_arr['status']; 

                $parking_id = (int)$lock_arr['parking_id'];


                if( $status == 4 )
                {
                    //休眠状态，返回错误
                    $ack = '5';

                    $res = "[{$lock_no}] sleeped!";
                }
                elseif( $status == 3 )
                {
                    //9：占用 有车放倒 inout=1 ack=1
                    $ack = '9';

                    $res = "lock [{$lock_no}] used, can't operate.";
                }
                elseif( $status == 2 )
                {
                    //6：故障 有车竖起 inout=1 ack=0
                    $ack = '6';

                    $res = "[{$lock_no}] failure, unable to work!";
                }
                elseif ( $status == 5 )
                {
                    //8：占用 无车放倒 inout=0 ack=1
                    if( $open == 1 )
                    {
                        //希望开锁，但锁已经开了
                        $ack = '8';

                        $res = "[{$lock_no}] already opened.";
                    }
                    else
                    {
                        //希望关锁
                        $ack = '0';

                        $res = "[{$lock_no}] closing.";

                        //更新关锁日志
                        $sql = " UPDATE `log_lock` 
                                    SET `status` = 2 
                                  WHERE `parking_id` = {$parking_id} 
                                    AND `end_time` = 0
                               ";

                        $pdo->exec( $sql ); 


                        //请求成功，记录或清理用户信息
                         $sql = " UPDATE `parking` 
                                    SET `bind_user` = ''
                                  WHERE `id` = {$parking_id} 
                               ";

                        $pdo->exec( $sql );
                    }
                }
                elseif ( $status == 1 || $status == 8 ) 
                {
                    //7：正常 无车竖起时 inout=0 ack=0
                    if( $open == 1 )
                    {
                        //希望开锁
                        $ack = '1';

                        $res = "[{$lock_no}] opening.";

                        //更新关锁日志
                        $sql = " INSERT INTO `log_lock`( `parking_id`,
                                                         `user_id`, 
                                                         `start_time`, 
                                                         `status`
                                                       ) 
                                      VALUES ( {$parking_id},
                                               '{$mobilphone}',
                                               '" . time() . "',
                                               1
                                             )
                               ";

                        $pdo->exec( $sql ); 


                        //请求成功，记录或清理用户信息
                         $sql = " UPDATE `parking` 
                                    SET `bind_user` = '{$mobilphone}'
                                  WHERE `id` = {$parking_id} 
                               ";

                        $pdo->exec( $sql );

                    }
                    else
                    {
                        //希望关锁，但锁本身是关的状态
                        $ack = '7';

                        $res = "[{$lock_no}] already closed.";
                    }
                }
                else
                {
                    //其他特殊情况
                    $ack = 'z';

                    $res = "[{$lock_no}] abnormal.";
                }

            }
        }


        //下发指令,默认method=LockControl 
        $res2 = $Iot->devCmd( $device_id, array(   'device' => $lock_no,
                                                   'ack'    => $ack
                                               ),
                              'LockAppAck' 
                            );

        fileLog( $res . '|' . $res2 );

        break;
    }



default:

    $res = array ('code'=>9000, 'msg'=>'event error:'.$datas_arr['event'], 'time'=>time() );

    exit( json_encode($res) );

    break;
}




function fileLog( $msg )
{
    $fd = fopen('./logs/events.log',"a");

    fwrite( $fd, "\n[" . date('Y-m-d H:i:s') . "] " . $msg );

    fclose( $fd );
}

