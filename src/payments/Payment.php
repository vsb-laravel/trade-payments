<?php namespace Vsb\payments;
class Payment{
    public function sale($args,$format='form'){
        $request = $this->build($args);
        $response = [];
        if($format == 'array'){
            $response=[
                'redirect'=>[
                    'url'=>$this->_gateUrl,
                    'method'=>'POST'
                ],
                'fields'=>$request->toArray()
            ];
        }
        else {
            $form = '<form action="'.$this->_gateUrl.'" method="POST">'.$request->toForm().'</form>';
            $response = [
                "redirect"=>[
                    "form"=>$form
                ]
            ];
        }
        return $response;
    }
    protected function getPostField($f,$def=''){
        return isset($_POST[$f])?$_POST[$f]:$def;
    }
}
// Server callback https://trade.bitooze.com/pay/fondy/back
// SUccess payment https://trade.bitooze.com/pay/fondy/success
// Failed payment https://trade.bitooze.com/pay/fondy/fail
// No recurent payments used
?>
