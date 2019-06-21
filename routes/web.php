<?php
use Illuminate\Support\Facades\Route;

Route::domain('pay.'.env('SM_DOMAIN'))->group(function(){
    Route::any('/{merchant}/{status}','PaymentController@update')->where('status','success|fail|back|form');
    Route::any('/{merchant}/status/{order_id?}','PaymentController@index');
});
?>
