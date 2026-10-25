<?php
/**
 * Справочники свойств и кодов состояний miIO-устройств.
 *
 * Раньше это были глобальные define() внутри класса модуля. Константы класса
 * читаются так же быстро, но не засоряют глобальное пространство и переживают
 * повторное подключение модуля без предупреждений PHP.
 *
 * 2026-10-25 — define() заменены на константы класса; карты состояний хранятся массивами.
 */
final class XiaomiMiioConst
{
public const MIIO_YEELIGHT_WHITE_BULB_PROPS = 'power,bright,flow_params,flowing';
public const MIIO_YEELIGHT_COLOR_BULB_PROPS = 'power,bright,ct,rgb,hue,sat,color_mode,flow_params,flowing';
public const MIIO_YEELIGHT_STRIP_PROPS = 'power,bright,ct,rgb,hue,sat,color_mode,flow_params,flowing';
public const MIIO_YEELIGHT_CEILING_LIGHT_PROPS = 'power,bright,ct,nl_br,color_mode,flow_params,flowing';
public const MIIO_YEELIGHT_LAMP_LIGHT_PROPS = 'power,bright,ct,flow_params,flowing';
public const MIIO_YEELIGHT_BSLAMP1_PROPS = 'power,bright,ct,rgb,hue,sat,nl_br,color_mode,flow_params,flowing,pdo_status,save_state,nighttime,miband_sleep';
public const MIIO_YEELIGHT_CEILING_LIGHT_650_PROPS = 'power,bright,ct,nl_br,color_mode,flow_params,flowing,bg_power,bg_bright,bg_rgb,bg_ct,bg_flow_params,bg_flowing';

public const MIIO_PHILIPS_LIGHT_BULB_PROPS = 'power,bright,cct,snm,dv';
public const MIIO_PHILIPS_LIGHT_CANDLE_PROPS = 'power,bright,cct';
public const MIIO_PHILIPS_LIGHT_CEILING_PROPS = 'power,bright,cct,snm,dv,bl,ac';

public const MIIO_PHILIPS_EYECARE_LAMP_2_PROPS = 'power,bright,notifystatus,ambstatus,ambvalue,eyecare,scene_num,bls,dvalue';

public const MIIO_CHUANGMI_PLUG_M1_PROPS = 'power,temperature';
public const MIIO_CHUANGMI_PLUG_V1_PROPS = 'power,usb_on,temperature,wifi_led';

public const MIIO_ZIMI_POWERSTRIP_2_PROPS = 'power,temperature,current,power_consume_rate,wifi_led';
public const MIIO_QMI_POWERSTRIP_1_PROPS = 'power,temperature,current,power_consume_rate,voltage';

public const MIIO_ZHIMI_HUMIDIFIER_PROPS = 'power,humidity,temp_dec,mode,led_b,buzzer';
public const MIIO_ZHIMI_HUMIDIFIER_2_PROPS = 'power,humidity,temp_dec,mode,depth,speed,dry,use_time,led_b,buzzer,child_lock,limit_hum';

public const MIIO_ZHIMI_AIRPURIFIER_MA2_PROPS = 'power,aqi,average_aqi,humidity,temp_dec,bright,mode,favorite_level,filter1_life,use_time,purify_volume,led,buzzer,child_lock';
public const MIIO_ZHIMI_AIRPURIFIER_V3_PROPS = 'power,aqi,bright,mode,filter1_life,led,buzzer,child_lock';

public const MIIO_MIWIFISPEAKER_V1_PROPS = 'umi,volume,rel_time';

public const MIIO_ZHIMI_FAN_SA1_PROPS = 'power,angle,speed,speed_level,natural_level,poweroff_time,ac_power,use_time,led_b,buzzer,child_lock';

public const MIIO_AIRMONITOR_S1_PROPS = 'battery,battery_state,co2,humidity,pm25,temperature,tvoc';

public const MIIO_YUNMI_KETTLE_R1_PROPS = 'water_remain_time,flush_time,flush_flag,tds,time,curr_tempe,setup_tempe,custom_tempe1,min_set_tempe,drink_remind,drink_remind_time,run_status,work_mode,drink_time_count';

public const MIIO_DEERMA_HUMIDIFIER_MJJSQ_PROPS = 'OnOff_State,TemperatureValue,Humidity_Value,HumiSet_Value,Humidifier_Gear,Led_State,TipSound_State,waterstatus,watertankstatus';

public const MIIO_DEERMA_HUMIDIFIER_JSQ_PROPS = 'Humidifier_Gear,Humidity_Value,HumiSet_Value,Led_State,OnOff_State,TemperatureValue,TipSound_State,waterstatus,watertankstatus';

public const MIIO_HFJH_FISHBOWL_V1_PROPS = 'Equipment_Status,feed_switch,heater_switch,led_board_brightness,led_board_color,led_board_model,led_board_speed,pump_switch,pump_value,water_tds,water_temp,water_temp_value';

public const MIIO_AIRFRESH_A1_PROPS = 'power,mode,pm25,co2,temperature_outside,favourite_speed,control_speed,filter_rate,filter_day,child_lock,sound,display';

public const MIIO_VIOMIVACUUM_V7_PROPS = 'run_state,mode,err_state,battary_life,box_type,s_time,s_area,suction_grade,water_grade';

public const MIIO_MIVACUUM_1_STATE_CODES = array('0' => 'Unknown',
    '1' => 'Initiating',
    '2' => 'Sleeping',
    '3' => 'Waiting',
    '4' => 'Remote control active',
    '5' => 'Cleaning',
    '6' => 'Back to home',
    '7' => 'Manual mode',
    '8' => 'Charging',
    '9' => 'Charging Error',
    '10' => 'Pause',
    '11' => 'Spot Cleaning',
    '12' => 'In Error',
    '13' => 'Shutting down',
    '14' => 'Updating',
    '15' => 'Docking',
    '16' => 'Going to target',
    '17' => 'Zoned cleaning',
    '18' => 'Room cleaning',
    '100' => 'Full',
    '101' => 'Wet cleaning',
    '105' => 'Turbo');

public const MIIO_VIOMIVACUUM_V7_STATE_CODES = array('0' => 'Waiting',
    '1' => 'Going to target',
    '2' => 'Waiting',
    '3' => 'Cleaning',
    '4' => 'Back to home',
    '5' => 'Docking',
    '6' => 'Zoned cleaning');

public const MIIO_DREAMVACUUM_C1_STATE_CODES = array('1' => 'Sweeping',
    '2' => 'Waiting',
    '3' => 'Pause',
    '4' => 'Error',
    '5' => 'Back to home',
    '6' => 'Docking');

public const MIIO_MIVACUUM_1_ERROR_CODES = array('0' => 'No error',
    '1' => 'Laser distance sensor error',
    '2' => 'Collision sensor error',
    '3' => 'Wheels on top of void, move robot',
    '4' => 'Clean hovering sensors, move robot',
    '5' => 'Clean main brush',
    '6' => 'Clean side brush',
    '7' => 'Main wheel stuck',
    '8' => 'Device stuck, clean area',
    '9' => 'Dust collector missing',
    '10' => 'Clean filter',
    '11' => 'Stuck in magnetic barrier',
    '12' => 'Low battery',
    '13' => 'Charging fault',
    '14' => 'Battery fault',
    '15' => 'Wall sensors dirty, wipe them',
    '16' => 'Place me on flat surface',
    '17' => 'Side brushes problem, reboot me',
    '18' => 'Suction fan problem',
    '19' => 'Unpowered charging station',
    '20' => 'Unknown Error',
    '21' => 'Laser pressure sensor problem',
    '22' => 'Charge sensor problem',
    '23' => 'Dock problem',
    '24' => 'No-go zone or invisible wall detected',
    '254' => 'Bin full',
    '255' => 'Internal error',
    '-1' => 'Unknown Error');

public const MIIO_VIOMIVACUUM_V7_ERROR_CODES = array('500' => 'Radar timed out',
    '501' => 'Wheels stuck',
    '502' => 'Low battery',
    '503' => 'Dust bin missing',
    '508' => 'Uneven ground',
    '509' => 'Cliff sensor error',
    '510' => 'Collision sensor error',
    '511' => 'Could not return to dock',
    '512' => 'Could not return to dock',
    '513' => 'Could not navigate',
    '514' => 'Vacuum stuck',
    '515' => 'Charging error',
    '516' => 'Mop temperature error',
    '521' => 'Water tank is not installed',
    '522' => 'Mop is not installed',
    '525' => 'Insufficient water in water tank',
    '527' => 'Remove mop',
    '528' => 'Dust bin missing',
    '529' => 'Mop and water tank missing',
    '530' => 'Mop and water tank missing',
    '531' => 'Water tank is not installed',
    '2101' => 'Unsufficient battery, continuing cleaning after recharge');
}

