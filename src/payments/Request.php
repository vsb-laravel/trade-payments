<?php namespace Vsb\payments;
use Log;
class Request extends PObject{
    public function post($url,$dataType='query'){
        $data = '';
        switch($dataType){
            case 'query':$data=$this->toUri();break;
            case 'json':$data=$this->toJson();break;
        }
        return $this->send($url,'post',$data,$dataType);
    }
    protected function send($url,$method,$data,$dataType='query'){
        $curl = curl_init($url);
        // Log::debug('curl.'.$method.' to '.$url.' data:'.$data);
        $headers = [];
        if($dataType == 'json') $headers = ['Content-type: application/json; charset: utf-8'];
        curl_setopt($curl,CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, 0);
        // curl_setopt($curl,CURLOPT_HEADER, 0); // Colate HTTP header
        curl_setopt($curl,CURLOPT_HTTPHEADER, $headers ); //  HTTP headers
        curl_setopt($curl,CURLOPT_RETURNTRANSFER, 1);// Show the output
        curl_setopt($curl,CURLOPT_POST,true); // Transmit datas by using POST
        curl_setopt($curl,CURLOPT_POSTFIELDS,$data);//Transmit datas by using POST
        Log::debug('CURL REQUEST: '.$data);
        // curl_setopt($curl,CURLOPT_REFERER,$this->returnUrl);
        $xmlrs = curl_exec($curl);
        curl_close ($curl);
        Log::debug('CURL RESPONSE: '.$xmlrs);
        return $xmlrs ;
    }
};
?>
