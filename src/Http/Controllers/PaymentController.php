<?php namespace Vsb\Http\Controllers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use App\User;
use App\Account;
use App\Invoice;
use Vsb\payments\Gateway;

class PaymentController extends BaseController
{
    protected $pm;
    public function __construct(){
        $this->pm = app('vsb.payments');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request){
        $validator = Validator::make($request->all(), [
            "account_id" => 'required|exists:accounts,id',
            "amount" => 'required',
            "type" => 'required|in:debit,credit,transfer_debit,transfer_credit',
            "from_account_id" => "exists:accounts,id"
        ]);
        if ($validator->fails()) {
            response()->json($validator->errors(), 422,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        }
        list($res,$code) = [[],200];
        try{
            $user = ($request->input('user_id',false)!=false)?User::find($request->user_id):$request->user();
            $account = //($request->input('account_id',false)!=false)?Account::find($request->account_id):
            Account::with(['currency'])->where('user_id',$user->id)->where('type','real')->first();
            $merchant = Merchant::where('name',$request->merchant)->first();
            if(is_null($merchant)) $merchant = Merchant::find($request->input("merchant_id",-1));
            $amount = $request->input("amount");
            $trx = ($request->input("order_id",false)==false)
                ?$this->pm->createTransaction([
                    'type'=>'deposit',
                    'user' => $user,
                    'merchant'=>$merchant->id,
                    'account'=>$account->id,
                    'amount'=>$amount,
                ])
                :Transaction::find($request->input("order_id"));
            $res = $trx;
            $trx->events()->create(['type'=>'request','user_id'=>$trx->user_id]);
            if($merchant->name=='payboutique'){
                $pb = new PayBoutique();
                $pbResponse = $pb->deposit([
                    "method" =>preg_replace('/\s*\(.+?\)/uim','',$request->input('method','CreditCard')),
                    "amount"=>$amount,
                    "user"=>$user,
                    "account"=>$account
                ]);
                if(isset($pbResponse["RedirectURL"])) {
                    $i = Invoice::create([
                        'merchant_id'=>$merchant->id,
                        'transaction_id'=>$res->id,
                        'user_id'=>$user->id,
                        'account_id'=>$account->id,
                        'order_id'=>$pbResponse["OrderID"],
                        'reference_id'=>$pbResponse["ReferenceID"],
                        'amount'=>$amount,
                        'currency'=>$pbResponse["BuyerCurrency"],
                        'raw'=>json_encode($pbResponse)
                    ]);
                    $res = $pbResponse;
                }
            }
            else if($merchant->name=='megatransfer'){
                //RedirectURL
                $pb = new MegaTransfer();
                $pbResponse = $pb->deposit([
                    "amount"=>$amount,
                    "user"=>$user,
                    "account"=>$account,
                    "order_id"=>$res->id
                ]);
                if(isset($pbResponse["RedirectURL"])) {
                    $i = Invoice::create([
                        'merchant_id'=>$merchant->id,
                        'method'=>$request->input('method','notset'),
                        'transaction_id'=>$res->id,
                        'user_id'=>$user->id,
                        'account_id'=>$account->id,
                        'order_id'=>$pbResponse["OrderID"],
                        'reference_id'=>$pbResponse["ReferenceID"],
                        'amount'=>$amount,
                        'currency'=>$pbResponse["BuyerCurrency"],
                        'raw'=>json_encode($pbResponse)
                    ]);
                    $res = $pbResponse;
                }
            }
            else if ($merchant->name=='paylane'){
                $args = $request->all();
                $args["user"] = $user;

                $args["description"]=$res->id;
                $res = Gateway::sale($merchant->name,$args);
            }
            else if ($merchant->name=='bonus'){
                $trx->update(['code'=>200]);
                $this->pm->makeBalance($account,$amount,'deposit');
            }
            else if ($merchant->name=='pbs'){
                $args = $request->all();
                $args["user"] = $user;
                $args["order_id"]=$trx->id;
                $args["ip"]=$request->ip();
                $res = Gateway::sale($merchant->name,$args);
                if(is_object($res)){
                    $i = Invoice::create([
                        'merchant_id'=>$trx->merchant_id,
                        'transaction_id'=>$trx->id,
                        'user_id'=>$trx->user_id,
                        'account_id'=>$trx->account_id,
                        'order_id'=>$res->tradeNo,
                        'reference_id'=>$res->tradeNo,
                        'amount'=>$trx->amount,
                        'currency'=>$account->currency->code,
                        'error'=>0,
                        'raw'=>json_encode($res)
                    ]);
                    $ret = [
                        "redirect"=>[
                            "url"=>($res->status==='success')?'/pay/pbs/success?'.$res->toUri():'/pay/pbs/fail'
                            // "url"=>'/pay/pbs/success?'.$res->toUri()
                        ]
                    ];
                    if($res->status==='success'){
                        $res = $ret;
                        $code = 200;
                        $trx->code = 200;
                        $i->error= 0;
                        $this->pm->makeBalance($account,$trx->amount,'deposit');

                        $i->events()->create(['type'=>'success','user_id'=>$trx->user_id]);
                    }
                    else{
                        $trx->code = 500;
                        $i->error= 500;
                        $i->events()->create(['type'=>'failed','user_id'=>$trx->user_id]);
                    }
                    $trx->save();
                    $i->save();
                    $res = $ret;
                }
            }
            else if($merchant->name=='payeer'){
                $args = $request->all();
                $args["user"] = $user;
                $args["order_id"]=$res->id;
                $args["amount"]=$amount;
                $res = Gateway::sale($merchant->name,$args);
                $i = Invoice::create([
                    'merchant_id'=>$trx->merchant_id,
                    'transaction_id'=>$trx->id,
                    'user_id'=>$trx->user_id,
                    'account_id'=>$trx->account_id,
                    'order_id'=>$trx->id,
                    'reference_id'=>$trx->id,
                    'amount'=>$amount,
                    'currency'=>$account->currency->code,
                    'raw'=>'{"status":"Payment request"}',
                    'error'=>504
                ]);
            }
            else {
                $args = $request->all();
                $args["user"] = $user;
                $args["order_id"]=$res->id;
                $args["amount"]=$amount;
                $args["ip"]=$request->ip();
                $res = Gateway::sale($merchant->name,$args);
                $raw = [
                    "status"=>"Payment request",
                ];
                if(isset($res['data']))$raw=array_merge($raw,$res['data']);
                $i = Invoice::create([
                    'merchant_id'=>$trx->merchant_id,
                    'transaction_id'=>$trx->id,
                    'user_id'=>$trx->user_id,
                    'account_id'=>$trx->account_id,
                    'order_id'=>$trx->id,
                    'reference_id'=>$trx->id,
                    'amount'=>$amount,
                    'currency'=>$account->currency->code,
                    'raw'=>json_encode($raw),
                    'error'=>504
                ]);
            }
            UserHistory::create(['user_id'=>$user->id,'type'=>'deposit','object_id'=>$trx->id,'object_type'=>'transaction','description'=>'Make deposit' ]);
        }
        catch(PayException $e){
            $code = 500;
            $res = json_decode(json_encode([
                "error"=>$e->getCode(),
                "message"=>$e->getMessage(),
                "code"=>500
            ]));
        }
        return response()->json($res,$code,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $merchant, $status)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
