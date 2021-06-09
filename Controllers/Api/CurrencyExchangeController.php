<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use App\Models\Currency;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;



class CurrencyExchangeController extends Controller
{
    private $Client;
    private $Currency;
    private $token = "1f5a940f625b4cb9956f5bb2df544d58"; // сделать через env
    private $convertPath = "/convert";
    private $defaultPath = "/latest.json";
    private $url = "https://openexchangerates.org/api";
    private $siteResponse;
    private $exchangeAmount;
    private $options;
    private $from;
    private $defaultBase= "USD";
    private $to;
    private $value;
    private $comment;
    private $doubleConvert = false;
    public $error = [] ;
    public $status;
    //TODO $currArray from site
    private $currArray = ["AED","AFN","ALL","AMD","ANG","AOA","ARS","AUD","AWG","AZN","BAM","BBD","BDT","BGN","BHD","BIF","BMD","BND","BOB","BRL","BSD","BTC","BTN","BWP","BYN","BZD","CAD","CDF","CHF","CLF","CLP","CNH","CNY","COP","CRC","CUC","CUP","CVE","CZK","DJF","DKK","DOP","DZD","EGP","ERN","ETB","EUR","FJD","FKP","GBP","GEL","GGP","GHS","GIP","GMD","GNF","GTQ","GYD","HKD","HNL","HRK","HTG","HUF","IDR","ILS","IMP","INR","IQD","IRR","ISK","JEP","JMD","JOD","JPY","KES","KGS","KHR","KMF","KPW","KRW","KWD","KYD","KZT","LAK","LBP","LKR","LRD","LSL","LYD","MAD","MDL","MGA","MKD","MMK","MNT","MOP","MRO","MRU","MUR","MVR","MWK","MXN","MYR","MZN","NAD","NGN","NIO","NOK","NPR","NZD","OMR","PAB","PEN","PGK","PHP","PKR","PLN","PYG","QAR","RON","RSD","RUB","RWF","SAR","SBD","SCR","SDG","SEK","SGD","SHP","SLL","SOS","SRD","SSP","STD","STN","SVC","SYP","SZL","THB","TJS","TMT","TND","TOP","TRY","TTD","TWD","TZS","UAH","UGX","USD","UYU","UZS","VES","VND","VUV","WST","XAF","XAG","XAU","XCD","XDR","XOF","XPD","XPF","XPT","YER","ZAR","ZMW","ZWL"] ;



    public function __construct()
    {
        $this->Client = new Client();
        $this->Currency = new Currency();
        $this->options = [
            "app_id"=>$this->token
        ];
    }


    private function sendRequest($url,$options): bool
    {

        try{
            $response = $this->Client->get($url,array('query'=>$options));
            if ($response->getStatusCode() == 200){
                $this->siteResponse = json_decode($response->getBody()->getContents(),true);
                $this->status =200;
                $result = true;
            }
        }catch(\Exception $error){
            $this->status = $error->getCode();
            $this->error[] = $error->getMessage();
            Log::error($error->getMessage(),[$error->getCode(),__LINE__,__FILE__]);
            $result = false;
        }
        return $result;
    }

    /**
     *
     * @param $rate array курсов
     * @return float
     * Если $base != "USD" - т.е. нет курсов по данной валюте - пересчитавыем двойной конвертацией через USD
     * по которое есть данные из api
     */
    private function calculate($rates, $double):float
    {
        $result=0;
        if ($double){
            $result = $this->value/$rates[$this->from] * $rates[$this->to];
            $this->comment ="Двойная конвертация, т.к. запрос курса по валюте отличной от 'USD' для Вашего токена невозможен";

        }else{
            $result = $this->value * $rates[$this->to];
            $this->comment ="Все ОК";
            $this->status = 200;
        }
        return $result;

    }

    private function getRateFromSite()
    {
        $rate=[];
        if ($this->tryUserBaseConvert()) {
              $this->doubleConvert = false;
        }else{
            if($this->tryDefaultBaseConvert()){
                $this->doubleConvert = true;
            }
        }
        $rate = $this->siteResponse['rates'];
        return  $rate;
    }

    /**
     * Вызов convert на "https://openexchangerates.org/api"
     *
     * попытка  вызова  штатного api метода convert на сайте  "https://openexchangerates.org/api" для текущего токена
     * @return bool
     */
    private function trySiteConvert()
    {
        $url = $this->url.$this->convertPath."/".$this->value."/".$this->from."/".$this->to;  // ДЛЯ ЭТОГО токена - недоступна."?".$options."&base=".$base."&symbols=".$symbols."&amount=".$amount;
        if ($this->sendRequest($url, $this->options)){
            $this->exchangeAmount = $this->siteResponse['response'];
            return true;
        };
        return false;
    }

    /**
     * Вызов base  на "https://openexchangerates.org/api"
     *
     * попытка  вызова  штатного api метода latest с параметром base (базовой валютой) для получения курсов
     * на сайте  "https://openexchangerates.org/api" для текущего токена
     * @return bool
     */
    private function tryUserBaseConvert()
    {
        $url = $this->url.$this->defaultPath;  // ДЛЯ ЭТОГО токена - недоступна."?".$options."&base=".$base."&symbols=".$symbols."&amount=".$amount;
       if( $this->sendRequest($url, array_merge($this->options,["base" => $this->from]))){
         return true;
       };
       return false;
    }
    /**
     * Вызов latest  на "https://openexchangerates.org/api"
     *
     * попытка  вызова  дефолтного  api метода latest   для получения курсов
     * на сайте  "https://openexchangerates.org/api" для текущего токена - отдает всегда для USD
     * @return bool
     */
    private function tryDefaultBaseConvert()
    {
        $url = $this->url.$this->defaultPath;
        if ($this->sendRequest($url, $this->options)){
            return true;
        };
        return false;
    }

    /**
     * Валидация входящих параметров
     *
     * from, to входят в массив валют , которые поддерживает сайт "https://openexchangerates.org/api"
     *
     * @param array $validationArray
     * @return \Illuminate\Contracts\Validation\Validator
     */
    private function valid(array $validationArray = [])
    {
        $validator = Validator::make($validationArray,[
            'from' => 'bail|required|'.Rule::in($this->currArray),
            'to'   => 'bail|required|'.Rule::in($this->currArray),
            'value'=> 'bail|required|numeric|min:0|not_in:0'
        ]);
        return $validator;
    }

    private function getRateFromDB()
    {
        $rate = [];
        $Currency = Currency::where('base', $this->from)->first();
        if ($Currency){
            $rate = $Currency->latest;
        }
        return $rate;
    }

    /**
     * метод получает сконвертированную сумму
     *
     * Поэтапно для текущего токена
     * 1. Попытка сконвертировать суму средствами сервиса "https://openexchangerates.org/api"
     * 2. При неудаче - попытка сконвертировать сумму из курсов, сохраненных в БД
     * 3. При неудаче - Запрос курсов с "https://openexchangerates.org/api"
     * причем, если права для токена позволяют получить курсы валют по заданной Базовой валюте -  получаем курсы и конвертируем,
     * если нет - получаем текущий курс по валюте по умолчанию (USD)  и двойной конвертацией через  USD получаем искомую сумму
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    public  function convert (Request $request)
    {
        $validator = $this->valid($request->all());
        if ($validator->fails()) {
            $this->status = 400;
           return response ($validator->errors()->messages(),$this->status);
        }

        $this->from = $request->get('from');
        $this->to = $request->get('to');
        $this->value = floatval($request->get('value'));

        if (! $this->trySiteConvert()){
            $rate = $this->getRateFromDB();
            if(! $rate){
                $rate = $this->getRateFromSite();
                $this->updateRate($rate, $this->doubleConvert);
            }else{
                $this->doubleConvert = false;
            }
        }else{
            $this->exchangeAmount = $this->siteResponse['response'];
        }

        $this->exchangeAmount  = $this->calculate($rate, $this->doubleConvert);
        $result = [
            'base currency'=>$this->from,
            'base amount'  =>$this->value,
            'exchange currency' => $this->to,
            'exchange amount' => $this->exchangeAmount,
            'comment' => $this->comment,
        ];
        if($this->error){
            $result['error']= $this->error;
        }
        return response($result, $this->status);
    }

    /**
     * Если консольная команда через крон запускается на пустой БД - создем первичную запись
     * с валютой по умочанию
     */
    private function createFirstModel(){

        $this->Currency->latest = $this->getRateFromSite("USD");
        $this->Currency->base = "USD";
        $this->Currency->save();
    }
    /**
     * Обновление курсов в БД
     *
     * @var
     * если массив $rate пустой - то вызов произошел из крона - обновляем то, что в БД
     * иначе записываем текущий массив
     * @Currency
     */
    public function updateRate($rate = [],$is_defaultBase = false)
    {
        if ($rate){
            $base = $is_defaultBase ? $this->defaultBase: $this->from;
            $Currency = Currency::where('base',$base)->first();
            if ($Currency){
                $Currency->latest = $rate;
            }else{
                $this->Currency->base = $base;
                $this->Currency->historical = $this->Currency->latest;
                $this->Currency->latest = $rate;
                $this->Currency->save();
            }
        }else{
            $Currencies = Currency::all();
            if($Currencies->isNotEmpty()){
                foreach ($Currencies as $Currency){
                    $Currency->historical = $Currency->latest;

                    $latest = $this->getRateFromSite($Currency->base);
                    if($latest){
                        $Currency->latest = $latest;
                    }
                    $Currency->save();
                }
            }else{

                $this->createFirstModel();
            }
        }

    }
}
